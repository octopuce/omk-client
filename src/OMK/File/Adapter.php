<?php
/*
FileAdapter
    Description: 
    Le file Adapter est chargé du stockage des fichiers originaux et transcodés. 
    Il les ventile selon sa logique propre dans des dossiers.
    Methodes: 
    open read write seek(tell?) close size(stat?)
    Config: 
    path_origin, path_transcoded, path_temp
*/
class OMK_File_Adapter extends OMK_Client_Friend {
    
    // ERR CODE 50-74

    const ERR_STORAGE_AUTH                  = 50;
    const ERR_STORAGE_CREATE                = 51;
    const ERR_STORAGE_MOVE                  = 52;
    const ERR_STORAGE_PATH                  = 53;
    const ERR_STORAGE_FILE_PATH             = 54;
    const ERR_STORAGE_FILE_ID               = 55;
    const ERR_STORAGE_FILE_NAME             = 56;
    const ERR_STATUS_INVALID                = 57;
    const ERR_STATUS_COMPLETE               = 58;
    const STATUS_UPLOADED                   = 4;
    const STATUS_STORED                     = 8;
    const STATUS_METADATA_REQUESTED         = 12;
    const STATUS_METADATA_RECEIVED          = 16;
    const STATUS_TRANSCODE_REQUESTED        = 20;
    const STATUS_TRANSCODE_READY            = 24;
    const STATUS_TRANSCODE_PARTIALLY        = 28;
    const STATUS_TRANSCODE_COMPLETE         = 32;
    const STATUS_L_UPLOADED                 = "Uploaded";
    const STATUS_L_STORED                   = "Stored";
    const STATUS_L_METADATA_REQUESTED       = "Metadata requested";
    const STATUS_L_METADATA_RECEIVED        = "Metadata received";
    const STATUS_L_TRANSCODE_REQUESTED      = "Transcode requested";
    const STATUS_L_TRANSCODE_READY          = "Transcode ready";
    const STATUS_L_TRANSCODE_PARTIALLY      = "Transconde in reception";
    const STATUS_L_TRANSCODE_COMPLETE       = "Transconde received";

    /**
     * Associative array of status_int => status_string, lazy generated
     * 
     * @var array
     */
    protected $statusList = NULL;
    /**
     * 
     * @param string $options.base_url
     */
    public function create( $options){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function open( $options ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function read( $options ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function write( $options ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function seek( $options ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function size( $options ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    public function getDownloadUrl( $options = NULL ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);

    }
    
    public function append( $options = NULL ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);

    }
    /**
     * Converts int status to human legible string
     * 
     * @param int $status
     * @return string
     */
    public function getStatus( $status = NULL ){
        $status_str;
        // Builds the status List
        if(NULL === $this->statusList){
            $this->statusList = array(
                self::STATUS_UPLOADED               => self::STATUS_L_UPLOADED,
                self::STATUS_STORED                 => self::STATUS_L_STORED,
                self::STATUS_METADATA_REQUESTED     => self::STATUS_L_METADATA_REQUESTED,
                self::STATUS_METADATA_RECEIVED      => self::STATUS_L_METADATA_RECEIVED,
                self::STATUS_TRANSCODE_REQUESTED    => self::STATUS_L_TRANSCODE_REQUESTED,
                self::STATUS_TRANSCODE_READY        => self::STATUS_L_TRANSCODE_READY,
                self::STATUS_TRANSCODE_PARTIALLY    => self::STATUS_L_TRANSCODE_PARTIALLY,
                self::STATUS_TRANSCODE_COMPLETE     => self::STATUS_L_TRANSCODE_COMPLETE,        
            );
        }
        // Retrieves status
        if( array_key_exists($status,$this->statusList) && ! is_null( $this->statusList[$status] )){$status_str = $this->statusList[$status];} 
        // Failed at retrieving status
        else {throw new Exception(__CLASS__."::".__METHOD__." = "._("Missing status."), self::ERR_MISSING_PARAMETER);}
        // Successful status string return
        return $status_str;
    }
    
}