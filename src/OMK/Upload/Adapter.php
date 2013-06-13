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
    
    // ERR CODE 125-149
    const ERR_OUTPUT_STREAM = 125;
    const ERR_MOVE_UPLOADED = 126;
    const ERR_INPUT_STREAM  = 127;
    const ERR_CHUNK_PART    = 128;
    const ERR_MISSING_FILE  = 129;
    
    
    protected $tmp_path;
    protected $protocol   = "http"; 
    protected $name   = "default"; // mandatory : sets a key name for this adapter
    protected $upload_complete = false;
    
    function __construct($options = null) {
        if( NULL == $options || !count($options)){
            throw new OMK_Exception(_("Missing options"), 1);
        }
        if(array_key_exists("tmp_path", $options) && NULL != $options["tmp_path"]){
            $this->tmp_path = $options["tmp_path"];
        }
        
        if(array_key_exists("name", $options) && NULL != $options["name"]){
            $this->name = $options["name"];
        }
        else{
            throw new OMK_Exception(_("Missing name"), 1);
        }
    }

    function getName(){
        if (NULL == $this->name ) {
            throw new OMK_Exception(_("Missing adapter name."));
        } 
        return $this->name;
    }
    

    function getProtocol(){
        if (NULL == $this->protocol ) {
            throw new OMK_Exception(_("Missing protocol."));
        } 
        return $this->protocol;
    }
    
    function upload( $options = null){
 
        throw new OMK_Exception(_("Cannot use default upload method, you must override it."), 1);
        
    }
    
    /**
     * Determines which file range has to be downloaded. Can skip in case like NAS storage 
     * 
     * @param type $options
     * @throws OMK_Exception
     * @return array[code,message,skip,(option)range)
     */
    public function getFileContentRange( $options = NULL ){
        
        throw new OMK_Exception(_("Cannot use default upload method, you must override it."), 1);

    }
    
    public function isUploadComplete(){
        return $this->upload_complete;
    }
    
    
}