<?php
/**
 * Cron is called by transcode
 * 
 * self::run()
 *  calls self::runTask()
 *    calls self::getCronTask()
 *      returns a cron task
 *    runs the task through OMK_Client::call()
 * while tasks are available
 * 
 */
class OMK_Cron extends OMK_Client_Friend{

    // ERR CODE 200 -> 224
    const ERR_TMP_PATH_NOT_WRITABLE = 200;
    const ERR_TMP_PATH_NOT_CREATED  = 201;
    const ERR_TASKS_MISSING         = 202;
    const ERR_INVALID_ACTION        = 203;
    const ERR_LOCK_QUEUE            = 204;
    const ERR_UNLOCK_QUEUE          = 205;
    
    const DELAY_REQUEST_DEFAULT     = 2; // in minutes
    const MAX_TRIES                 = 13; // number of times we try an action
    
    
    /**
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        $loops              = 0;
        $errors             = array();
        do {
            // Runs task, exceptions captured by method
            $loops++;
            $this->recordResult($this->runTask());
            
            // Handles failure
            if( ! $this->successResult()){
                $errors[] = array(
                    "code" => $this->result["code"],
                    "message" => $this->result["message"]
                );
            }
            // Handles end of loop
            if( array_key_exists("finished", $this->result) && $this->result["finished"] ){
                break;
            }
        } while ( TRUE );

        // Exits
        $this->recordResult( array(
            "code" => 0,
            "message"   => sprintf(_("Finished cron tasks, %s loops."), ($loops - 1)),
            "errors"    => $errors
        ));
        return $this->getResult();
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
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Retrieves a task and locks it
        $this->recordResult($this->getCronTask());
        
        // Exits if failed
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
                
        // Stores getCronTask result
        $tmpResult = $this->result;
        
        // Unlocks table
        $this->recordResult($this->getClient()->getDatabaseAdapter()->unlock(array("table" => "queue")));

        // Exits if failed
        if (!$this->successResult()) {
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Exits if finished
        if (array_key_exists("finished", $tmpResult) && NULL != $tmpResult["finished"]) {
            return $tmpResult;
        } 
        
        // Retrieves task
        if (array_key_exists("task", $tmpResult) && NULL != $tmpResult["task"]) {
            $task = $tmpResult["task"];
        } else {
            throw new OMK_Exception(_("Missing task."), self::ERR_MISSING_PARAMETER);
        }
        
        // Crashes if unlocked failed
        if( ! $this->successResult()){
            throw new OMK_Exception(_("Failed to lock queue table"), self::ERR_UNLOCK_QUEUE);
        }

        $id             = $task["id"];
        $action         = $task["action"];

        $this->getClient()->getLoggerAdapter()->log(array(
            "level"     => OMK_Logger_Adapter::INFO,
            "message"   => sprintf(_("Started cron queue item#%s action %s, object#%s"),$id,$action,$task["object_id"])
        ));
        
        // Runs the task. 
        try{                
            switch ($action){
                case "app_new_media":
                case "app_get_media":
                case "app_request_format":
                case "transcoder_send_format":
                case "transcoder_send_metadata":
                    $this->recordResult($this->getClient()->call($task));
                    break;
                default :
                    throw new OMK_Exception(_("Invalid action requested: ${action}"),self::ERR_INVALID_ACTION);
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
        
        // Gets the task status if defined by the response 
        if (array_key_exists("status", $this->result) && NULL != $this->result["status"]) {
            $status = $this->result["status"];
        } else if( $task["failed_attempts"] >= self::MAX_TRIES ) {
            // If failed too much, set status as failed
            $status = OMK_Queue::STATUS_ERROR;
        } else {
            // By default, consider the status is "IN PROGRESS"
            $status  = OMK_Queue::STATUS_IN_PROGRESS;
        }
        
        // Task failed: attempts to reset lock and save status. Handles failed attempts
        if( ! $this->successResult()){
            
            // Gets the delay before next visit
            $delay = $this->getDelayNextRequest( $task["delay_next_request"]);
            
            // Stores the error and message
            $storedResult = $this->getResult();
            
            $this->recordResult($this->unlockTask(array(
                "id"        => $id,
                "data"      => array(
                    'status'                => $status,
                    'failed_attempts'       => OMK_Database_Adapter::REQ_INCREMENT,
                    'delay_next_request'    => $delay
                )
            )));
            if( !$this->successResult()){
                throw new OMK_Exception($this->result["message"],$this->result["code"]);
            }
            // Returns the actual error, not a positive feedback from row update
            return $storedResult;
        }

        
        // Task achieved: attempts to reset lock and save status. 
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
    
/**
 * Returns the next delay before retry based on the current delay
 * 
 * @param int $current_delay
 * @return int delay
 * 
 */    
    protected function getDelayNextRequest( $current_delay = self::DELAY_REQUEST_DEFAULT ){
 
        // Default value
        if( (int) $current_delay < self::DELAY_REQUEST_DEFAULT ){
            return self::DELAY_REQUEST_DEFAULT;
        }
        return (int) ($current_delay * 1.618 );
    }
    
    function unlockTask( $options = NULL ){

        if (NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        if (array_key_exists("id", $options) && NULL != $options["id"]) {
            $id = $options["id"];
        } else {
            throw new OMK_Exception(_("Missing id."));
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data = $options["data"];
            $data["locked"] = 0;
        } else {
            throw new OMK_Exception(_("Missing data."));
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
        
        // Proxifies the db adapter
        $db         = $this->getClient()->getDatabaseAdapter();
        
        // Updates wrong individual locks, 
        $this->recordResult(
            $db->update(array(
               "table" => "queue",
                "data" => array(
                    "locked" => OMK_Queue::LOCK_UNLOCKED
                ),
                "where" => array(
                    "locked = ?" => OMK_Queue::LOCK_LOCKED,
                    "TIMESTAMPDIFF( SECOND, dt_last_request, NOW()) > 150" => OMK_Database_Adapter::REQ_NO_BINDING,
                    "status IN (".implode(",", array(OMK_Queue::STATUS_NULL,OMK_Queue::STATUS_IN_PROGRESS,OMK_Queue::STATUS_ERROR)).")" => OMK_Database_Adapter::REQ_NO_BINDING
                )
        )));
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Retrieves a single task
        $this->recordResult(
            $db->select( array(
                "table" => "queue",
                "where" => array(
                    "locked = ?" => OMK_Queue::LOCK_UNLOCKED,
                    "failed_attempts < ?" => self::MAX_TRIES,
                    "status IN (".implode(",",array(OMK_Queue::STATUS_NULL, OMK_Queue::STATUS_IN_PROGRESS,OMK_Queue::STATUS_ERROR)).")" => OMK_Database_Adapter::REQ_NO_BINDING,
                    "DATE_SUB( NOW(), INTERVAL delay_next_request MINUTE) >= dt_last_request" => OMK_Database_Adapter::REQ_NO_BINDING
                ),
                "order" => array(
                    "priority ASC",
                    "id ASC"
                ),
                "limit" => 1
        ))); 
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Validates task. Return in case it's not valid.
        $taskData       = current($this->result["rows"]);
        
        if (is_array($taskData) && array_key_exists("id", $taskData) && NULL != $taskData["id"]) {
            $task_id    = $taskData["id"];
        } else {
            return array(
                "code"      => 0,
                "message"   => _("No more task to run"),
                "finished"  => TRUE
            );
        }
        
        // Puts individual lock
        $this->recordResult(
            $db->update(array(
               "table" => "queue",
                "data" => array(
                    "dt_last_request" => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP,
                    "locked" => OMK_Queue::LOCK_LOCKED
                ),
                "where" => array(
                    "id = ?" => $task_id
                )
        )));      
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // - returns task
        return array(
            "code"      => 0,
            "message"   => _("Retrieved task."),
            "task"      => $taskData
        );
    }
    
}    
