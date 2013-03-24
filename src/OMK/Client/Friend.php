<?php

/**
 * Description of Friend
 *
 * @author alban
 */
class OMK_Client_Friend {
    
    // ERR CODE 250 - 274
    const ERR_METHOD_OVERRIDE_REQUIRED      = 250;
    
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
    
    /**
     * Reads the code error 
     * 
     * @param void
     * @return true|false
     * @see getResult() the method used to get the resulting object
     * @see recordResult() used to set the result internally
     */
    function successResult(){
        if (NULL == $this->result || !count($this->result)) {
            throw new OMK_Exception(_("Missing object result."));
        }
        if ( ! array_key_exists("code", $this->result) && NULL != $this->result["code"]) {
            throw new OMK_Exception(_("Missing code."));
        }
        if( $this->result["code"] == 0 ){
            return TRUE;
        }
        return FALSE;
     }
     
     
    /**
    * Gateway for recording to self the results of friend classes operations
    *
    * Use example : $this->recordResult( $this->getClient()->getQueue()->fetchAll() );
    *
    * @param array $options the result of the method called
    *   An associative array containing:
    *   - code: error code (0 for success, >0 for failure).
    *   - message: message associated to result.
    *   - [more] : results variables depending on context.
    * 
    * @see getResult() the method used to get the resulting object
    * @see successResult() the method used to get the resulting object
    */
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

     /**
      * Gateway for returning operations result
      * 
      * @param array $options
      *   An associative array containing:
      *   - format: (optional) if json, returns a json string.
      * @see recordResult() used to set the result internally
      * @return array|string, depending on format requested
      * @throws Exception
      */
     public function getResult($options = array() ){
         
         if (array_key_exists("format", $options) && NULL != $options["format"]) {
             $format = $options["format"];
         }else{
             $format = FALSE;
         }
         if( ! $format){
             return $this->result;
         }
         // Rejects empty results beyond this point
         if( NULL == $this->result || !count($this->result)){
             throw new Exception("Invalid result.");
         }
         // Json output requested
         if( "json" == $format){
             $return = $this->getClient()->jsonEncode($this->result);
         }else{
             throw new OMK_Exception(_("Unknown format requested"));
         }
         // If JSON failed, throws exception
         if( NULL == $return || "null" == $return){
             throw new Exception(_("Null result returned"));
         }
         // returns string
         return $return;
     }
     
     public function _($string){
         return $this->getClient()->getTranslationAdapter()->translate($string);
     }
     
     
     private function checkApiAppKey(){
         
         // Secures the calls
         if (array_key_exists("api_app_key", $_REQUEST) && NULL != $_REQUEST["api_app_key"]) {
             $api_app_key = $_REQUEST["api_app_key"];
         } else {
             throw new Exception(_("Missing api app key."));
         }
         if( $this->getClient()->getAppKey() != $api_app_key ){
             throw new OMK_Exception(_("Invalid app key."));
         }
         
     }
     

}
