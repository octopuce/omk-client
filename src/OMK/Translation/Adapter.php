<?php
/*
TranslationAdapter
    Description
    Gère la traduction des chaînes
    Methodes
    _ : Retourne les chaînes traduites
    Config
    paramètres du système de traduction
 * 
*/
class OMK_Translation_Adapter extends OMK_Client_Friend {
    
    // ERR CODE 100-124
    
    /**
     * Placeholder interface for documentation only
     * 
     * @param string $string
     * @throws OMK_Exception
     */
    function translate( $string ){
        throw new OMK_Exception(_("You must override this method."));
    }
    /**
     * Shortcut for the translate function
     * 
     * @param string $string
     * @return string
     */
    function _( $string ){
        return $this->t($string);
    }
}