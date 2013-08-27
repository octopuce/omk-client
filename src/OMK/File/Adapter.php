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
    const STATUS_L_TRANSCODE_PARTIALLY      = "Transcode reception in progress";
    const STATUS_L_TRANSCODE_COMPLETE       = "Transcode received";
    const TYPE_VIDEO                        = "video";
    const TYPE_AUDIO                        = "audio";
    

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
    /**
     * Appends data to file_path
     * 
     * @param array $options file_path, data
     * @return array code, message, file_size
     * @throws OMK_Exception
     */
    public function append( $options = NULL ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);

    }
    
    /**
     * 
     * @param array $options
     *      - id
     *      - file_name
     *      - cardinality
     *      - settings_id
     * @return array An associative array containing:
     *   - code 
     *   - message 
     *   - file_name 
     *   - file_path
     *   - serial
     * 
     * @throws OMK_Exception
     */
    public function getTranscodedFileData( array $options ){
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
    
    
    /**
     * Sets status on files being received after transcode chunk reception
     * 
     * @todo move this to OMK_Media model
     * @param array $options finished,fileData,parent_id
     * @return array code, message
     * @throws OMK_Exception
     */
    function onEndTranscodeAppend($options = array()){
        
        // Retrieves finished
        if( array_key_exists("finished",$options) && ! is_null( $options["finished"] )){$finished = $options["finished"];} 
        // Failed at retrieving variable $finished
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing finished."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves fileData
        if( array_key_exists("fileData",$options) && ! is_null( $options["fileData"] )){$fileData = $options["fileData"];} 
        // Failed at retrieving variable $fileData
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing fileData."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves storage
        if (array_key_exists("storage", $fileData) && !is_null($fileData["storage"])) {
            $storage = $fileData["storage"];
        } else {
            throw new OMK_Exception(_("Missing parameter storage"), self::ERR_MISSING_PARAMETER);
        }
        
        // Retrieves file_size
        if (array_key_exists("file_size", $storage) && !is_null($storage["file_size"])) {
            $file_size = $storage["file_size"];
        } else {
            throw new OMK_Exception("Missing parameter file_size", self::ERR_MISSING_PARAMETER);
        }
        
        // Retrieves parent_id
        if( array_key_exists("parent_id",$fileData["database"]) && ! is_null( $fileData["database"]["parent_id"] )){$parent_id = $fileData["database"]["parent_id"];} 
        // Failed at retrieving variable $parent_id
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing parent_id."), self::ERR_MISSING_PARAMETER);}
        
        
        
        if( $finished == TRUE){
            
            // Attempts to retrieve transcode siblings requiring transfer
            $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
                "table"     => "files",
                "where"     => array(
                    "parent_id = ?" => $parent_id,
                    "status IN ?" => array( OMK_File_Adapter::STATUS_TRANSCODE_READY, OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY, OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED )
                )
            )));
            
            // Exits if failed
            if( ! $this->successResult() ){ 
                throw new OMK_Exception(_("Failed to count transcode siblings after end of transfer: {$this->result["message"]}"),$this->result["code"]);
            }
            
            // Attempts to retrieve db results
            if( array_key_exists("rows",$this->result) && NULL != $this->result["rows"]){
                $rows = $this->result["rows"];
            } else {
                throw new OMK_Exception(_("Missing rows."), self::ERR_MISSING_PARAMETER);
            }
            
            // Attempts to update parent if this is the last transcode
            if( count($rows) <= 1 ){
                
                $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
                    "table"     => "files",
                    "where"     => array(
                        "id = ?"        => $parent_id,
                    ),
                    "data"      => array(
                        "status"        => OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE,
                        "dt_updated"    => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
                    )

                )));
                
                // Exits if failed
                if( !$this->successResult()){
                    throw new OMK_Exception(_("Failed to update parent file status after end of transfer: {$this->result["message"]}"),$this->result["code"]);
                }
            }
            // Sets a final status
            $file_status            = OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE;
            
        }else{
            
            // Sets an ongoing status
            $file_status            = OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY;
            
        }
        
        // Updates the file record in database
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"     => "files",
            "where"     => array(
                "id = ?"        => $fileData["database"]["id"],
            ),
            "data"      => array(
                "file_size"     => $file_size,
                "status"        => $file_status,
                "dt_updated"    => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
            )

        )));        
        
        // Exits if failed
        if( !$this->successResult()){
            throw new OMK_Exception(_("Missing rows."), self::ERR_MISSING_PARAMETER);
        }

        return array(
            "code"      => self::ERR_OK,
            "message"   => _("Successfully updated the file transcode status.")
        );
    }
    
    /**
     * Extracts an archive
     * 
     * @param array $options filepath,...
     * @return array code,message,...
     * @throws OMK_Exception
     */
    public function extractArchive( array $options ){
        
        if (array_key_exists("file_path", $options) && !is_null($options["file_path"])) {
            $file_path      = $options["file_path"];
        } else {  
            throw new OMK_Exception("Missing parameter file_path", self::ERR_MISSING_PARAMETER); }
        
        if( !is_file($file_path)){
            throw new OMK_Exception("Invalid file name for archive extraction {$file_path}",self::ERR_STORAGE_FILE_PATH);}
            
        $dirname            = pathinfo($file_path, PATHINFO_DIRNAME);
        if( !is_dir($dirname)){
            throw new OMK_Exception("Invalid directory for archive extraction {$dirname}",self::ERR_STORAGE_PATH);}
          
        // Attempts to extract the archive
        $zipArchive         = new ZipArchive;
        if ($zipArchive->open($file_path) === TRUE) {
            $zipArchive->extractTo($dirname);
            $zipArchive->close();
        } else {
            throw new OMK_Exception("Failed to extract {$file_path}",self::ERR_EXTRACT);}
        
        return array(
            "code" => self::ERR_OK,
            "message" => "Successfully extracted archive file ${file_path} "
        );
    }
    
    /**
     * Encapsulates filesize
     * 
     * @param array $options file_path
     * @return int
     * @throws OMK_Exception
     */
    public function getFileSize( $options = null ){
                
        if (array_key_exists("file_path", $options) && !is_null($options["file_path"])) {
            $file_path = $options["file_path"];
        } else {
            throw new OMK_Exception("Missing parameter file_path", self::ERR_OK);
        }
        
        if( !is_file($file_path)){
            return 0;
        }
        
        if( !is_readable($file_path)){
            throw new OMK_Exception (_("Invalid file, cannot read size : $file_path"),self::ERR_STORAGE_AUTH);
        }
        
        clearstatcache();
        return filesize($file_path);
        
    }
}