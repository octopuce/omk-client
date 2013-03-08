<?php

/**
 * Description of Friend
 *
 * @author alban
 */
class OMK_Client_Friend {
    
    protected $client;
    
    function setClient( OMK_Client $client){
        $this->client = $client;
    }
    
    function getClient(){
        if( null == $this->client){
            throw new OMK_Exception("Missing client.",1);
        }
        return $this->client;
    }
    
}

?>
