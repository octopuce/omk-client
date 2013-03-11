<?php
/*
LoggerAdapter
    Description
    Gère l'écriture des logs
    Methodes
    log : ajoute un élément de log avec en paramètre le niveau de log et le message
    Config
    paramètres du système de log
 */
class OMK_Logger_Adapter extends OMK_Client_Friend {
    
    // ERR CODE 75 - 99

    const DEBUG = "DEBUG";
    const INFO = "INFO";
    const WARN = "WARN";
    public function log( $options = null ){
        // error_level, $error_message, array $data = null, $exception = null 
    }
}