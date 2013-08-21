<?php

class OMK_Client_Settings_Strategy extends OMK_Client_Friend{

    
    public function getSettingsList( array $options = NULL ){
        
        if(is_null($options)){
            throw new OMK_Exception("Settings Manager missing options parameter");
        }
        
    }

 
    protected function video( array $options = NULL ){
        
    }
}
