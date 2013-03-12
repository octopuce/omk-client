<?php

/**
 * Description of Friend
 *
 * @author alban
 */
class OMK_Client_Friend {
    
    protected $client;
    protected $result = array();

    function setClient( OMK_Client $client){
        $this->client = $client;
    }
    
    /**
     * @return OMK_Client the friend Client 
     */
    function getClient(){
        if( NULL == $this->client){
            throw new OMK_Exception(_("Missing client."),1);
        }
        return $this->client;
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
        if(array_key_exists("message", $this->result) && NULL != $this->result["message"]){
            $previousResults[] = $this->result;
        }

        $result["class"] = get_class($this);

        $this->result = $result;
        $this->result["_previousResults"] = $previousResults;

        $this->getClient()->getLoggerAdapter()->log( array(
            "level"    => OMK_Logger_Adapter::DEBUG , 
            "message"  => $result["message"]
        ));
         
     }

     public function getResult($options = null){
         if (array_key_exists("format", $options) && NULL != $options["format"]) {
             $format = $options["format"];
         }
         if( ! $format){
             return $this->result;
         }
         if( NULL == $this->result || !count($this->result)){
             throw new Exception("Invalid result.");
         }
         if( "json" == $format){
             $return = $this->getClient()->jsonEncode($this->result);
         }
         
         if( NULL == $return || "null" == $return){
             throw new Exception(_("Null result returned"));
         }
         return $return;
     }

}
