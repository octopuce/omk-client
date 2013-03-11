<?php class OMK_Authentification_Session extends OMK_Authentification_Adapter {
    
    function check(){
        return TRUE;
    }
    
    function getOwnerId() {
        return 1;
    }

} 
