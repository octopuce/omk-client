<?php class OMK_Authentification_Session extends OMK_Authentification_Adapter {

    protected $lifetime;
    
    function __construct( $options = NULL) {
        parent::__construct($options = NULL);
        switch( session_status() ){
            case PHP_SESSION_DISABLED:
                throw new OMK_Exception(_("Sessions not available.",  self::ERR_ADAPTER_MISCONFIGURATION ));
                break;
            case PHP_SESSION_NONE:
                if( ! session_start()){
                    throw new OMK_Exception(_("Sessions not started.",  self::ERR_ADAPTER_MISCONFIGURATION ));
                }
                break;
            case PHP_SESSION_ACTIVE:
                break;
        }
        if( !count($options)){
            return;
        }
        if (array_key_exists("lifetime", $options) && NULL != $options["lifetime"]) {
            $this->lifetime = $options["lifetime"];
        } else {
            $session_params = session_get_cookie_params();
            $this->lifetime = $session_params["lifetime"];
        }
    }
    function check(){
        return TRUE;
    }
    
    function getUserId() {
        return 1;
        // default
        return 0;
    }

    function getToken(){
        $key = sha1(microtime());
        $_SESSION["omk_authentification_token_value"]   = $key;
        $_SESSION["omk_authentification_token_time"]    = time();
        return $key;
    }
    
    function checkToken( $options = NULL ){
        if (array_key_exists("token", $options) && NULL != $options["token"]) {
            $token = $options["token"];
        } else {
            throw new OMK_Exception(_("Missing token."));
        }
        if( $_SESSION["omk_authentification_token_value"] != $token){
            return array(
                "code"  => self::ERR_AUTHENTIFICATION_REJECTED,
                "messsage"  => _("Invalid token.")
            );
        }
        if( time() - $_SESSION["omk_authentification_token_time"] > $this->lifetime){
            return array(
                "code"  => self::ERR_AUTHENTIFICATION_REJECTED,
                "messsage"  => _("Session lost.")
            );
        }
        
        return array(
            "code"  => 0,
            "code"  => _("Valid token.")
        );
    }
} 
