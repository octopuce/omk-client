<?php class OMK_Database_Dummy extends OMK_Database_Adapter {
    
     
    function insert($options = null){
        
        return array(
            "id"        => time(),
            "code"      => 0,
            "message"   => "Dummy DB insert response."
        );
        
    }
    
    function update($options = null ){
        
        return array(
            "code"      => 0,
            "message"   => "Dummy DB update response."
        );
        
    }

    
    function select($options = null){
        
        return array(
            "code"      => 0,
            "message"   => "Dummy DB select response."
        );
        
    }
    
    function delete($options = null){
        
        return array(
            "code"      => 0,
            "message"   => "Dummy DB delete response."
        );
        
    }
           
} 
