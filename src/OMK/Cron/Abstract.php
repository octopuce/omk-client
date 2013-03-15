<?php 
class OMK_Cron_Abstract extends OMK_Client_Friend {
    public function __construct( $options = NULL ) {
        if( NULL == $options ||!count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        
    }
}