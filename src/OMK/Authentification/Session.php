<?php class OMK_Authentification_Session extends OMK_Authentification_Adapter {
    
    function check(){
        return TRUE;
    }
    
    function getUserId() {
        return 1;
        // default
        return 0;
    }

} 
