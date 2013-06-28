<?php
/*
Authentification Adapter
    Description
    Contrôle l'authentification des utilisateurs qui se connectent au client directement
    Methodes
    check : vérifie la conformité de la requête, retourne un objet
    Config
    Sans
*/
class OMK_Authentification_Adapter extends OMK_Client_Friend{
    
    const GROUP_ADMIN = 1;
    const GROUP_USER = 2;
    
    // ERR CODE 1 - 24
    const ERR_INVALID_USER = 2;
    
    public function __construct( $options = NULL ) {
    }
    
    function setCredentials(){
        
    }
    
    function getUserId(){
        
    }
    
    function check(){
        return FALSE;
    }
    
    function getToken(){
        
    }
    function checkToken( $options = NULL ){
       
    }
}