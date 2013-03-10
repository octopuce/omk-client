<?php class OMK_Client_Response extends OMK_Client_Friend {

     protected $result;
     public function __construct(OMK_Client $client) {
         $this->setClient($client);
         $this->result["code"] = 0;
         $this->result["class"] = __CLASS__;
     }
     public function run( $params ){
         $action = $params["action"];
         $this->$action($params);
     }
     
     
     // undefined response for local test and availability checks
     private function app_test($options = null){
         
     }
     
     private function app_subscribe($options = null){
         
     }
     private function transcoder_send_format($options = null){
         
     }
     private function transcoder_cron($options = null){
         
     }
     private function upload($request = null){
         
        $this->recordResult( 
            $this->getClient()->getUploadAdapter($request)->upload($request) 
        );
        if( ! $this->successResult() ){
            return; // Failed to upload or upload in progress
        }
        $file_path = $this->result["file_path"];
        $file_name = $this->result["file_name"];
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->insert(
                array(
                    "table" => "files",
                    "data"  => array(
                        "owner_id" => $this->getClient()->getAuthentificationAdapter()->getOwnerId(),
                        "file_name" => $file_name
                    )
                )
            ) 
        );
        if(! $this->successResult() ){
           return; // Failed to include file into db
        }
        $file_id = $this->result["id"];
        $this->recordResult( 
            $this->getClient()->getFileAdapter()->create(
                array(
                    "file_id" => $file_id,
                    "file_name" => $file_name,
                    "file_path" => $file_path
                )
            )
        );
        if(! $this->successResult() ){
           return; // Failed to move file to final destination
        }
        // Adds file transcoding request to queue
        $this->recordResult( 
            $this->getClient()->getQueue()->push(
                array(
                    "origin"        => "app",
                    "handler"       => "transcoder",
                    "action"        => "app_new_media",
                    "object_id"     => $file_id,
                )
            )
        );
        
    }
         
     
     function successResult(){
         if( $this->result["code"] == 0 ){
             return TRUE;
         }
         return FALSE;
     }
     
     function recordResult( array $result){
         
         // Saves previously recorded results
         if( array_key_exists("_previousResults", $this->result) && count( $this->result["_previousResults"]) ){
             $previousResults = $this->result["_previousResults"];
             unset($this->result["_previousResults"]);
         }else{
             $previousResults = array();
         }
         
         // Saves previous result data
         if(array_key_exists("code", $this->result) && null != $this->result["code"]){
             $previousResults[] = $this->result;
         }
  
         $this->result = $result;
         $this->result["_previousResults"] = $previousResults;
         
         $this->getClient()->getLoggerAdapter()->log( OMK_Logger_Adapter::INFO , $result["message"]);
         
     }
     
     


     public function getResult($json = false){
         if( ! $json ){
             return $this->result;
         }
         if( null == $this->result || !count($this->result)){
             throw new Exception("Invalid result.");
         }
        return json_encode($this->result);
     }
}