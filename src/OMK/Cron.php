<?php
/**
 * This attempts to break the cron jobs by being as atomical as possible
 */
class OMK_Cron extends OMK_Client_Friend{

    // ERR CODE 200 -> 224
    const ERR_TMP_PATH_NOT_WRITABLE = 200;
    const ERR_TMP_PATH_NOT_CREATED  = 201;
    const ERR_STRATEGY_FAILED       = 202;
    
    /**
     * Records the local system availability of the exec function
     * 
     * @var boolean 
     */
    static $exec_available;
    
    
    /**
     * Records the local system availability of the curl function
     * 
     * @var boolean 
     */
    static $curl_available;
    
    /**
     * Path to store cron data
     * 
     * Ex : /tmp/omk
     * 
     * @var string
     */
    static $cron_tmp_path;
    
    /**
     * 
     * @return string directory path
     * @throws OMK_Exception
     */
    static function getCronTmpPath(){
    
        if( !isset(self::$cron_tmp_path)){
            
            $path           = sys_get_temp_dir();
            if( !is_writable($path)){
                throw new OMK_Exception(_("Cron tmp path is not writable."),self::ERR_TMP_PATH_NOT_WRITABLE);
            }
            // Get an unique MD5 from the local file
            $directory      = __FILE__;
            $directory      = str_replace("/", "_", $directory);
            $directory      = md5($directory.$this->getClient()->getAppKey());
            $directory      = "omk-".substr($directory, 4);
            $path           = "{$path}/{$directory}"; 
            if( !mkdir($path, 0777, TRUE)){
                throw new OMK_Exception(_("Could not create tmp path"),self::ERR_TMP_PATH_NOT_CREATED);
            }
            self::$cron_tmp_path = $path;
        }
        return self::$cron_tmp_path;
    }

    /**
     * 
     * @return boolean
     * @link http://stackoverflow.com/questions/3938120/check-if-exec-is-disabled
     */
    protected function isExecAvailable() {
        
        if (!isset(self::$exec_available)) {
            self::$exec_available = TRUE;
            if (ini_get('safe_mode')) {
                self::$exec_available = FALSE;
            } else {
                $d = ini_get('disable_functions');
                $s = ini_get('suhosin.executor.func.blacklist');
                if ("$d$s") {
                    $array = preg_split('/,\s*/', "$d,$s");
                    if (in_array('exec', $array)) {
                        self::$exec_available = FALSE;
                    }
                }
            }
        }
        return self::$exec_available;
    }
    
    /**
     * 
     * @return boolean 
     */
    protected function isCurlAvailable(){
        
        if( !isset(self::$curl_available)){
            self::$curl_available = TRUE;
            if(!extension_loaded("curl")){
                self::$curl_available = FALSE;
            }
        }
        return self::$curl_available;
    }

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
            $this->getClient()->getDatabaseAdapter()->update(array(
                "table"     => "activity",
                "data"      => array(
                    "dt_updated" => time()
                ),
                "where"     => array(
                    "key = ?"   => "last_cron_call"
                )
            ))
        );
        
        // Fetches tasks to be executed
        $this->recordResult( 
            $this->getClient()->getQueue()->fetchCronTasks()
        );
        if( !$this->successResult()){
            return;
        }
        
        // Return if tasks list empty
        if( ! count($this->result["tasks"]) ){
            return array(
                "code"      => 0,
                "message"   => _("No cron task to run.")
            );
        }
                
        // Determines which cron method are available
        if( $this->isExecAvailable()){
            $strategiesList[]   = "OMK_Cron_Exec"; 
        } 
        if( $this->isCurlAvailable() ){
            $strategiesList[]   = "OMK_Cron_Curl";
        } 
        $strategiesList[]       = "OMK_Cron_Self";

        // Attempts each strategy in order
        foreach ($strategiesList as $strategy) {
            $cronManager = new $strategy;
            $cronManager->setClient( $this->getClient() );
            try{
                $this->recordResult(
                    $cronManager->run()
                );
            }catch( OMK_Exception $e){
                $this->getClient()->getLoggerAdapter()->log(array(
                    "level"     => OMK_Logger_Adapter::WARN,
                    "message"   => sprintf( _("Failed to initialize %s cron strategy."), $strategy ),
                    "exception" => $e
                ));
                $this->result   = array(
                    "code"      => self::ERR_STRATEGY_FAILED,
                    "message"   => "Strategy failed."
                );
            }
            // Quit if successfull
            if( $this->successResult()){
                return $this->getResult();
            }
        }
        return $cronManager->run( $options );
        
    }
}    
