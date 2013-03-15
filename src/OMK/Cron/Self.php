<?php
/**
 * This class attempts to run the cron jobs by itself
 * 
 * This is a pretty bad situation as various timeouts might occur
 * - PHP timeout: in safe_mode, one cannot make it any longer
 * - APACHE timeout: depends on the server configuration
 * 
 */
class OMK_Cron_Self extends OMK_Cron_Abstract {
    
    public function run( $options = NULL ){
        
        // Attempts to set the php time limit to infinite
        if(  !ini_get('safe_mode') ){
            set_time_limit(36000); // Yes, 10 hours
        }else{
            // We won't probably be able to download anything by ourself.
        }
        
    }
    
}
