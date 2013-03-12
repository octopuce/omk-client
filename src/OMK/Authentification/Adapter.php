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
    
    // ERR CODE 0 - 24
    
    function setCredentials(){
        
    }
    
    function getUserId(){
        
    }
    
    function check(){
        return FALSE;
    }
}