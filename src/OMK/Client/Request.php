<?php 

require_once 'HTTP/Request2.php';

class OMK_Client_Request {

    protected $_requestObject;
     public function __construct(OMK_Client &$client) {
         $this->_client = $client;
     }
     
     public function run( $options ){
         
        
         $this->getRequestObject($options);
     }
     
    protected function getRequestObject($options) {
        
        if( null == $this->_requestObject){
            if(array_key_exists("url", $options)){
                $url = $options["url"];
            }else{
                $url = $this->_client->getUrl();
            }
            if(array_key_exists("method", $options)){
                $method = $options["method"];
            }else{
                $method = Http_Request2::METHOD_GET;
            }
            if(array_key_exists("config", $options)){
                $config = $options["config"];
            }
            $this->_requestObject = new HTTP_Request2($url, $method, $config);
        }
        return $this->_requestObject;
        
    }
}