<?php class OMK_Client_Response extends OMK_Client_Friend {

     public function __construct(OMK_Client $client) {
         $this->setClient($client);
         $this->_result["code"] = 0;
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
         
         $this->getClient()->getUploadAdapter($request)->upload($request);
         
     }
     public function getResult(){
         return json_encode($this->_result);
     }
}