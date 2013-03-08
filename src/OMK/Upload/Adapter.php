<?php
/*
UploadAdapter
    Description:
    Peut coexister avec d'autres UploadAdapter
    Il prend en charge les transferts des fichiers (originaux?) de l'utilisateur vers l'OMK. 
    Pour cela lorsqu'il reçoit un fichier, il demande un ID à l'OMK, il envoie le fichier au FileAdapter, et préviens l'OMK en fin d'upload.
    Méthodes:
    upload
    Config: path_tmp
    Vues: html+JS éventuels pour la conduite de l'upload et les événements
 * 
 */
class OMK_Upload_Adapter extends OMK_Client_Friend {
    
    public $tmp_path;
    public $name; // mandatory : sets a key name for this adapter
    function __construct($options = null) {
        if( null == $options || !count($options)){
            throw new OMK_Exception("Missing options", 1);
        }
        if(array_key_exists("tmp_path", $options) && null != $options["name"]){
            $this->tmp_path = $options["tmp_path"];
        }
        
        if(array_key_exists("name", $options) && null != $options["name"]){
            $this->name = $options["name"];
        }
        else{
            throw new OMK_Exception("Missing name", 1);
        }
    }

    function getName(){
        return $this->name;
    }
    
    function upload( $options = null){
 
        throw new OMK_Exception("Cannot use default upload method, you must override it.", 1);
        
    }
    
}