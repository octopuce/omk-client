<?php
/**
 * This attempts to break the cron jobs by being as atomical as possible
 */
class OMK_Cron extends OMK_Client_Friend{

    // ERR CODE 200 -> 224
    const ERR_TMP_PATH_NOT_WRITABLE = 200;
    const ERR_TMP_PATH_NOT_CREATED  = 201;
    const ERR_TASKS_MISSING         = 202;
    const ERR_INVALID_ACTION        = 203;
    const ERR_LOCK_QUEUE            = 204;
    const ERR_UNLOCK_QUEUE          = 205;
    const ERR_NO_TASKS              = 206;
    
    
    /**
     * Uses one of many (if available) strategies to run cron tasks 
     * 
     * best strategy is exec: no time limits
     * med strategy is curl: timeout/async
     * low strategy is self: timeout/async
     * 
     * @param array $options
     *   An associative array containing:
     *   - : .
     * 
     * @return array result[code,message]
     */
    public function run( $options = NULL ){
    
        // Records last cron
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->save(array(
                "table"     => "variables",
                "data"      => array(
                    "id"    => "last_cron_call",
                    "val"   => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
                ),
                "where"     => array(
                    "id = ?"   => "last_cron_call"
                )
            ))
        );
        
        if( !$this->successResult()){
            return $this->getResult();
        }
        do {
            // Runs task 
            $this->recordResult($this->runTask());
            if( ! $this->successResult()){
                return $this->getResult();
            }
            if( array_key_exists("finished", $this->result) && $this->result["finished"] ){
                return $this->getResult();
            }
        } while ( TRUE );

        // Exits
        return array(
            "code" => 0,
            "message"   => _("Finished cron tasks.")
        );
        
    }
    
    
    /**
     * Runs atomic tasks
     * 
     * @return array [code,message]
     */
    protected function runTask(){
        
            
        // Locks table
        $this->recordResult(
            $this->getClient()->getDatabaseAdapter()->lock(array(
                    "table" => "queue"
            )
        ));
        
        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}
        
        // Retrieves a task and locks it
        $this->recordResult($this->getCronTask());
        
        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}
        
        // Exits if finished
        if (array_key_exists("finished", $this->result) && NULL != $this->result["finished"]) {
            return $this->getResult();
        } 
        
        // Retrieves task
        $task            = $this->result["task"];
        
        // Unlocks table
        $this->recordResult($this->getClient()->getDatabaseAdapter()->unlock(array("table" => "queue")));
        
        // Crashes if unlocked failed
        if( ! $this->successResult()){
            throw new OMK_Exception(_("Failed to lock queue table"), self::ERR_UNLOCK_QUEUE);
        }

        $id             = $task["id"];
        $action         = $task["action"];

        // Runs the task. 
        try{                
            switch ($action){
                case "app_new_media":
                case "app_request_format":
                case "transcoder_send_format":
                case "transcoder_send_metadata":
                    $this->recordResult($this->getClient()->call($task));
                    break;
                default :
                    throw new OMK_Exception(_("Invalid action requested"),self::ERR_INVALID_ACTION);
                    break;
            }

        } catch (OMK_Exception $e){

            // Logs warning
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => sprintf(_("Task %s failed to run."),$action),
                "exception" => $e
            ));

            // Sets failure result
            $this->result["code"]       = $e->getCode();
            $this->result["message"]    = $e->getMessage();

        }
        
        // Gets the task status if defined by the response, probably result
        if (array_key_exists("status", $this->result) && NULL != $this->result["status"]) {
            $status = $this->result["status"];
        } else if( $task["failed_attempts"] > 10 ) {
            // If failed too much, set status as failed
            $status = OMK_Queue::STATUS_FAILURE;
        } else {
            // By default, consider the status is "IN PROGRESS"
            $status  = OMK_Queue::STATUS_IN_PROGRESS;
        }
        
        // Task failed: attempts to reset lock and save status. Handles failed attempts
        if( ! $this->successResult()){
            $this->recordResult($this->unlockTask(array(
                "id"        => $id,
                "data"      => array(
                    'status' => $status,
                    'failed_attempts' => OMK_Database_Adapter::REQ_INCREMENT
                )
            )));
            return $this->getResult();
        }

        // Task succeeded : attempts to reset lock and save status. 
        try{

            $this->recordResult($this->unlockTask(array(
                "id"        => $id,
                "data"      => array(
                    'status'            => $status,
                    'dt_last_request'   => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
                )
            )));
        } catch(OMK_Exception $e){

            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => sprintf(_("Task %s failed to unlock."),$action),
                "exception" => $e
            ));
        } 

        return $this->getResult();

    }
    
    
    function unlockTask( $options = NULL ){

        if (NULL == $options || !count($options)) {
            throw new Exception(_("Missing options."));
        }
        if (array_key_exists("id", $options) && NULL != $options["id"]) {
            $id = $options["id"];
        } else {
            throw new Exception(_("Missing id."));
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data = $options["data"];
            $data["locked"] = 0;
        } else {
            throw new Exception(_("Missing data."));
        }
        $params             = array(
            "table"     => "queue",
            "where"     => array(
                "id = ?" => $id
            ),
            "data"      => $data
         );
        
        try{
            // Unlocks the task
            $lock_result = $this->getClient()->getDatabaseAdapter()->update($params);
            
        } catch (OMK_Exception $e){
            // Not unlocked, serious fail. 
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => sprintf(_("Task %s failed to unlock."),$action),
                "exception" => $e
            ));
        }
        return $lock_result;
    }


    /**
     * Retrives a single task to execute and locks it
     * 
     * Handles the tasks queue : 
     * - searches bad individual locks, 
     * - fetch single task
     * - puts individual lock
     * - returns task
     * 
     * @return array [code,message,(optional)task,(optional)finished]
     */
    public function getCronTask(){
        
        // proxy the db adapter
        $db         = $this->getClient()->getDatabaseAdapter();
        
        // - updates wrong individual locks, 
        $this->recordResult(
            $db->update(array(
               "table" => "queue",
                "data" => array(
                    "locked" => OMK_Queue::LOCK_UNLOCKED
                ),
                "where" => array(
                    "locked = ?" => OMK_Queue::LOCK_LOCKED,
                    "TIMESTAMPDIFF( SECOND, dt_last_request, NOW()) > 150" => OMK_Database_Adapter::REQ_NO_BINDING,
                    "status IN (".implode(",", array(OMK_Queue::STATUS_NULL,OMK_Queue::STATUS_IN_PROGRESS)).")" => OMK_Database_Adapter::REQ_NO_BINDING
                )
        )));
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // Retrieves a single task
        $this->recordResult(
            $db->select( array(
                "table" => "queue",
                "where" => array(
                    "locked = ?" => OMK_Queue::LOCK_UNLOCKED,
                    "status IN (".implode(",",array(OMK_Queue::STATUS_NULL, OMK_Queue::STATUS_IN_PROGRESS)).")" => OMK_Database_Adapter::REQ_NO_BINDING
                ),
                "order" => array(
                    "priority ASC",
                    "id ASC"
                ),
                "limit" => 1
        ))); 
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // Validates task. Return in case it's not valid.
        $taskData       = current($this->result["rows"]);
        
        if (is_array($taskData) && array_key_exists("id", $taskData) && NULL != $taskData["id"]) {
            $task_id    = $taskData["id"];
        } else {
            return array(
                "code"      => self::ERR_NO_TASKS,
                "message"   => _("No more task to run"),
                "finished"  => TRUE
            );
        }
        
        // Puts individual lock
        $this->recordResult(
            $db->update(array(
               "table" => "queue",
                "data" => array(
                    "locked" => OMK_Queue::LOCK_LOCKED
                ),
                "where" => array(
                    "id = ?" => $task_id
                )
        )));      
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // - returns task
        return array(
            "code"      => 0,
            "message"   => _("Retrieved task."),
            "task"      => $taskData
        );
    }
    
    protected function app_request_format( $options = NULL ){
        
    }
    
    protected function transcoder_send_format( $options = NULL ){
        
    }
    protected function transcoder_send_metadata( $options = NULL ){
        
    }
}    
