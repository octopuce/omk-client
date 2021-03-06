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

    const DEBUG     = "1";
    const INFO      = "10";
    const WARN      = "100";
    
    /**
     * Adds a log entry
     * 
     * @param array $options
     *   An associative array containing:
     *   - level: int self::DEBUG|INFO|WARN 
     *   - message: string
     *   - data: (optional) array.
     *   - exception: (optional) exception.
     */
    public function log( $options = NULL ){
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
    }
    
    /**
     * Human readable error level
     * 
     * @param int $level
     * @return string level
     * @throws OMK_Exception
     */
    public function getLogLevel( $level = NULL ){
        if (NULL == $level) {
            throw new OMK_Exception(_("Missing level."));
        }
        switch ((int)$level) {
            case self::DEBUG:
                return _("DEBUG");
                break;
            case self::INFO:
                return _("INFO");
                break;
            case self::WARN:
                return _("WARN");
                break;
            default:
                return _("UNKNOWN");
                break;
        }
    }
}