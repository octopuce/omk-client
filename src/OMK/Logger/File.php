<?php 

class OMK_Logger_File extends OMK_Logger_Adapter {
    
    /** string Full path to log file */
    protected $log_file_path;
    /** int User defined logging level */
    protected $level;
    
    const FILE_UNKNOWN          = "?F";
    const LINE_UNKNOWN          = "?L";
    const CLASS_UNKNOWN         = "?C";
    const FUNCTION_UNKNOWN      = "?F";

    public function __construct( $options = NULL ) {
        
        if ( NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        if (array_key_exists("log_file_path", $options) && NULL != $options["log_file_path"]) {
            $this->log_file_path = $options["log_file_path"];
        } else {
            throw new OMK_Exception(_("Missing log file path."));
        }
        if (array_key_exists("level", $options) && NULL != $options["level"]) {
            $this->level = $options["level"];
        } else {
            $this->level = self::WARN;
        }
    }
    
    /**
     * A simple single file based logging function
     * 
     * @param array $options
     *   An associative array containing:
     *   - level: An int representing the error level.
     *   - message: A string containing the message to log.
     *   - exception: (optional) An exception related to the message..
     *   - data: (optional) An array containing data related to the message.
     *   Any further description - still belonging to the same param, but not part
     *   of the list.
     *  
     */
    public function log($options = null) {
        if ( NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        $message            =  "";

        if (array_key_exists("level", $options) && NULL != $options["level"]) {
            $level          = (int)$options["level"];
        } else {
            $level          = self::DEBUG;
        }
        if (array_key_exists("message", $options) && NULL != $options["message"]) {
            $message        .= (string)$options["message"];
        } else {
            $message        .= _("Default error message");
        }
        if (array_key_exists("exception", $options) && NULL != $options["exception"]) {
            $exception      = $options["exception"];
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data           = (array)$options["data"];
        } 
        if ($level < $this->level) {
            return;
        }
        $data_str           = "";
        $excp_str           = "";
        $log_str            = "";
        $user_id            = $this->getClient()->getAuthentificationAdapter()->getUserId();
        $file_handle        = fopen($this->log_file_path, "a");
        if( !$file_handle ){
            throw new OMK_Exception(_("Failed to open log file output."));
        }
        if( isset($exception) ){
            $excp_str       = "\nException: {";
            $excp_str       .= "\n  Message: ".$exception->getMessage();
            $excp_str       .= "\n  File: ".$exception->getFile()."+".$exception->getLine();
            $excp_str       .= "\n  Stack: ";
            foreach ($exception->getTrace() as $k => $stack) {
                $args = array();
                foreach( $stack["args"] as $arg_key => $arg_arr ){
                    $args = $this->exploreException( $arg_arr, $args);
                }
                $file       = ! empty($stack["file"])     ? $stack["file"]  : self::FILE_UNKNOWN;
                $line       = ! empty($stack["line"])     ? $stack["line"]  : self::LINE_UNKNOWN;
                $class      = ! empty($stack["class"])    ? $stack["class"] : self::CLASS_UNKNOWN;
                $function   = ! empty($stack["function"]) ? $stack["file"]  : self::FUNCTION_UNKNOWN;
                $excp_str   .= "\n    $k. {$file}+{$line} {$stack["class"]}::{$stack["function"]} [".implode("],[", $args)."]";
            }
            $excp_str       .= "\n}\n";
        }
        if( isset($data) ){
            $data_str       = "\nData: [";
            foreach($data as $k => $v){
                $data_str   .= "\n  {$k}: $v";
            }
            $data_str       .= "\n]\n";
        }
        
        $log_str            .= date("Y-m-d H:i:s ");
        $log_str            .= "{$_SERVER["REMOTE_ADDR"]} ";
        $log_str            .= "userId:{$user_id} ";
        $log_str            .= $this->getLogLevel($level);
        $log_str            .= ": {$message}\n";
        $log_str            .= $excp_str.$data_str;
        fwrite($file_handle, $log_str);
        fclose($file_handle);
    }
    
    
    private function exploreException($part, $return){
        $arg_tmp = array();
        if(is_array($part)){
            foreach ($part as $key => $val) {
                if(is_object($key)){
                    $key = get_class($key);
                }
                if(is_object($val)){
                    $val = get_class($val);
                }
                if(is_array($val)){
                    $arg_tmp[]= "{$key}:[".  str_replace("\n"," ",print_r($val,1))."]";
                }else{
                    $arg_tmp[]= "{$key} => {$val}";
                }
            }
            $return[] = implode(",", $arg_tmp);
        }elseif(is_string ($part)){
            $return[] = $part;
        }elseif( is_a($part, "Exception")){
            $return[] = $this->exploreException($part);
        }
        return $return;
    }
} 
