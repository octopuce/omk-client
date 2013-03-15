<?php
/**
 * This object manages a controller handling sub-process management.
 * 
 */
class OMK_Cron_Exec extends OMK_Cron_Abstract {

    // ERR CODE 225 -> 249
    const ERR_CONTROLLER_FILE_READ      = 225;
    const ERR_CONTROLLER_FILE_DELETE    = 226;
    
    /**
     * In seconds, delay until we consider the controller died
     */
    const CONTROLLER_DECAY_ALLOWED = 60;
    
    /**
     * The controller's file name
     */
    const CONTROLLER_FILE_NAME = "controller.txt";

    /**
     * Checks if a new controller is required and spawns it
     * 
     * @param type $options
     * @return array result[code,message,...]
     */
    public function run( $options = NULL ){
        
        // checks if childs are being processed
        $this->getResult($this->getControllerInfo());

        // Returns  in case of failure
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // Exits if controller already running
        if (array_key_exists("controller_pid", $options) && NULL != $options["controller_pid"]) {
            
            return array(
                "code"      => 0,
                "message"   => _("Cron controller already instanced.")
            );
        }
        
        // spawns controller
        $this->getResult( $this->startController() );
        
        // exits in every case
        return $this->getResult();
    }
    
                
    /**
     * Checks if the controller is still alive and kicking
     * 
     * If so, the return array will contain the controller's pid
     * 
     * @return array [code,message]
     */
    protected function getControllerInfo(){
        
        // Gets the controller file
        $tmp_cron_path              = OMK_Cron::getTmpPath();
        $controller_file_path       = $tmp_cron_path.self::CONTROLLER_FILE_NAME;
        if( !(file_exists($controller_file_path))){
            return array(
                "code"      => 0,
                "message"   => _("No cron controller running.")
            );
        }
        if( ! is_readable($controller_file_path) ){
            throw new Exception(_("Could not read the controller file"),self::ERR_CONTROLLER_FILE_READ);
        }
        $controller_file_contents   = file_get_contents($controller_file_path);
        $controllerData             = json_decode($controller_file_contents);
        
        // Deletes controller file if it messed up with no reason
        if( NULL == $controllerData || "NULL" == $controllerData || !count($controllerData) ){
            $this->recordResult( 
                    $this->deleteControllerFile($controller_file_path)
            );
            // overrides the result status; We want to make it try to override the content. 
            $this->status["code"] = 0;
            return $this->getResult();
            
        }
        
        // Reads controller's process id and last time stamp
        if (array_key_exists("controller_pid", $options) && NULL != $options["controller_pid"]) {
            $controller_pid = $options["controller_pid"];
        } else {
            throw new OMK_Exception(_("Missing controller_pid."));
        }
        if (array_key_exists("controller_ts", $options) && NULL != $options["controller_ts"]) {
            $controller_ts = $options["controller_ts"];
        } else {
            throw new OMK_Exception(_("Missing controller time stamp."));
        }
        
        // Returns if controller's process id is still alive
        if( is_dir("/proc/{$controller_pid}") && (time() + self::CONTROLLER_DECAY_ALLOWED) <= $controller_ts ){
            return array(
                "code"              => 0,
                "message"           => "Cron controller still running.",
                "controller_pid"    => $controller_pid
            );
        }
        
        $this->getClient()->getLoggerAdapter()->log(array(
            "level"         => OMK_Logger_Adapter::WARN,
            "message"       => "The cron controller died."
        ));
        
        $this->recordResult($this->deleteControllerFile($controller_file_path));
        
        // Failed to delete the controller file
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        return array(
            "code"      => 0,
            "message"   => "Cron controller died and got cleaned."
        );
        
    }
    
    public function startController(){
        
        $cron_path  = dirname(__FILE__);
        $cron_path  .= "exec.controller.php";
        if(file_exists("/usr/bin/php")){
            $php_path = "/usr/bin/php";
        }else{
            $php_path = "php";
        }
        $command    = "{$php_path} {$cron_path}";
        exec($command, $output, $return_var);
        // Failed to start controller
        if( 0 == $return_var){
            return array(
                "code"      => self::ERR_CONTROLLER_START,
                "message"   => _("Failed to start cron exec {$command}"),
                "data"      => $output,
            );
        }
        return array(
            "code"      => 0,
            "message"   => (_("Cron controller started."))
        );
        
    }
    
    public function deleteControllerFile($controller_file_path){
        
        $deleted        = unlink($controller_file_path);
        if( ! $deleted ){
            throw new OMK_Exception(_("Feiled to delete controller file."),self::ERR_CONTROLLER_FILE_DELETE);
        }
        return array(
            "code" => 0,
            "message" => "Cron controller file cleaned."
        );

                    
    }
}
