<?php class OMK_Client_Response extends OMK_Client_Friend {

     // ERR CODE : 300 -> 324
    const ERR_MISSING_REQUEST_PARAMETER     = 300;
    
    public function __construct(OMK_Client $client) {
        $this->setClient($client);
    }
     
     /**
      * Calls a method, stores internally the results to be displayed by the client
      * 
      * @param type $options
      * @return void 
      * @throws OMK_Exception
      */
    public function run( $options = NULL ){
        if (NULL == $options) {
             throw new OMK_Exception(_("Missing options."));
        }
        // Checks action
        if (array_key_exists("action", $options) && NULL != $options["action"]) {
            $action = $options["action"];
        } else {
            throw new OMK_Exception(_("Missing action."));
        }
        if( !method_exists($this, $action)){
            throw new OMK_Exception(_("Invalid action requested."));
        }

        // Call the method
        $this->recordResult($this->$action($options));
     }
     
     // always true response for local test and availability checks
     /**
      * 
      * @param type $options
      * @return array
      * 
      * 
app_test

Who : [app,transcoder] -> App

When : whenever

What : App returns current timestamp

Request Params : void

onSuccess : void

onError : void

répond à une sorte de ping : pratique pour l'installation (fonctionne) ou pour que le serveur vérifie qu'elle elle est up
      */
     protected function app_test($options = null){
         $this->result =  array(
             "code" => 0,
             "message" => _("Local instance is up.")
         );
     }
     
     /**
      * 
      * @param type $options
      * transcoder_send_format



Who : Transcoder -> App

When : Transcoder finished transcoding a format

What : App adds the media format to its download queue

Request Params

    id:int
    settings_id:int
    metadata: json hashset {type,mimetype,data,md5,size}
    cardinality:int (optional)
    adapter:string (optional)

Response : code, message

onSuccess

    App updates media status to STATUS_TRANSCODE_READY
    App adds to its queue the app_get_media actions for the tuples {parent_id, settings_id}

onError : Transcode logs error

      */
     protected function transcoder_send_format($options = null){
         
        // Checks transcoder's request sanity
        $this->recordResult( $this->validateTranscoderRequest() );

        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}

        // Retrieves id (the parent_id)
        if (array_key_exists("id", $_REQUEST) && NULL != $_REQUEST["id"]) {
            $parent_id = $_REQUEST["id"];
        } else {
            throw new OMK_Exception(_("Missing id."), self::ERR_MISSING_REQUEST_PARAMETER);
        }
        
        // Retrieves settings_id
        if( array_key_exists("settings_id",$_REQUEST) && ! is_null( $_REQUEST["settings_id"] )){$settings_id = $_REQUEST["settings_id"];} 
        // Failed at retrieving variable $settings_id
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing settings_id."), self::ERR_MISSING_PARAMETER);}
      
        // Retrieves metadata
        if( array_key_exists("metadata",$_REQUEST) && ! is_null( $_REQUEST["metadata"] )){$metadata = $_REQUEST["metadata"];} 
        // Failed at retrieving variable $metadata
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing metadata."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves cardinality
        if( array_key_exists("cardinality",$_REQUEST) && ! is_null( $_REQUEST["cardinality"] )){$cardinality = $_REQUEST["cardinality"];} 
        // Sets a false $cardinality
        else {$cardinality=FALSE;}
        
        // Retrieves adapter
        if( array_key_exists("adapter",$_REQUEST) && ! is_null( $_REQUEST["adapter"] )){$adapter = $_REQUEST["adapter"];} 
        // Set a default $adapter
        else {$adapter = $this->getClient()->getUploadAdapter()->getProtocol();}
        
        // Retrieves the file
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
            "table"        => "files",
            "where"        => array(
                "parent_id = ?"     => $parent_id,
                "settings_id = ?"   => $settings_id
            )
        )) );

        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            return array(
                "code"     => OMK_File_Adapter::ERR_STATUS_INVALID,
                "message"  => _("Invalid file id or settings id : couldn't find the a valid file request.")
            );
        }else{
            $result = current( $rows );
            $object_id = $result["id"];
        }
        
        
        // Checks the file is awaiting format
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
            "table"        => "files",
            "where"        => array(
                "id = ?"       => $object_id,
                "status = ?"   => OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED
            )
        )) );

        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            $msg = sprintf(_("Invalid status: Transcode not expected for file#%s with setting#%s."),$object_id, $settings_id);
            throw new OMK_Exception($msg,OMK_File_Adapter::ERR_STATUS_INVALID);
        }else{
            $fileData["database"] = current( $rows );
        }

        
        // Checks the upload adapter doesn't already own the file (NAS adapter)
        $this->recordResult(
            $this->getClient()->getUploadAdapter($adapter)->isTransferRequired( array(
            "object_id" => $object_id )
            )
        );
        
        // Exits if failed
        if( !$this->successResult()){ 
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Checks return validity
        if (array_key_exists("transfer_required", $this->result) && !is_null($this->result["transfer_required"])) {
            $transfer_required = $this->result["transfer_required"];
        } else {
            throw new OMK_Exception("Missing parameter transfer_required", self::ERR_MISSING_PARAMETER);
        }
        
        // Defines status
        $status = $transfer_required ? OMK_File_Adapter::STATUS_TRANSCODE_READY:OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE;
                 
        // Updates file status to TRANSCODE READY or COMPLETE
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"        => "files",
            "data"         => array(
                "metadata" => $metadata,
                "status"   => $status
            ),
            "where"        => array(
                "id = ?"   => $object_id
            )
        )) );

        // Exits if failed
       if( !$this->successResult()){ 
            $msg = sprintf(_("Couldn't update Transcode_Ready status for file#%s with setting#%s metadata %s."),$object_id, $settings_id, $metadata);
            throw new OMK_Exception($msg,$this->result["code"]);
        }

        // Returns if no transfer requested
        if( ! $transfer_required ){
            $this->recordResult( $this->getClient()->getFileAdapter()->onEndTranscodeAppend(
                array(
                "finished"  => true,
                "fileData"  => $fileData,
                "parent_id" => $parent_id
                )
            ));
            return $this->getResult();
        }
        
        
        // Add transfer to queue
        $this->recordResult( $this->getClient()->getQueue()->push(array(
            "object_id"        => $object_id,
            "action"           => "app_get_media",
            "dt_created"       => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP,
            "dt_last_request"  => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
         )));
        
        // Updates the file parent 
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"        => "files",
            "data"         => array(
                "status"   => OMK_File_Adapter::STATUS_TRANSCODE_READY
            ),
            "where"        => array(
                "id = ?"        => $object_id,
                "status != ? "  => OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY
            )
        )));
        
        // Exit
        return $this->getResult();
        
     }
     /**
      * 
      * @param type $options
      * 
transcoder_send_metadata

Who : Transcoder -> App

When : media meta decoded by transcoder

What : App sets media metadata

Request Params

    id:int
    meta:hashset{media_type:ENUM,media_meta:hashset{...}}

onSuccess : App updates media status to META_RECEIVED or META_INVALID
      */
     protected function transcoder_send_metadata($options = null){

        // Checks transcoder's request sanity
        $this->recordResult( $this->validateTranscoderRequest() );

        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}

        if (array_key_exists("id", $_REQUEST) && NULL != $_REQUEST["id"]) {
            $parent_id = $_REQUEST["id"];
        } else {
            throw new OMK_Exception(_("Missing id."), self::ERR_MISSING_REQUEST_PARAMETER);
        }
        
        if (array_key_exists("metadata", $_REQUEST) && NULL != $_REQUEST["metadata"]) {
            $metadata = $_REQUEST["metadata"];
        } else {
            throw new OMK_Exception(_("Missing metadata."), self::ERR_MISSING_REQUEST_PARAMETER);
        }

        // Checks file is awaiting meta
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
            "table"        => "files",
            "where"        => array(
                "id = ?"        => $parent_id,
                "status IN ?"      => array( OMK_File_Adapter::STATUS_STORED, OMK_File_Adapter::STATUS_METADATA_REQUESTED)
            )
        )) );

        // Exits if failed
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            $this->recordResult(array(
                "code"     => OMK_File_Adapter::ERR_STATUS_INVALID,
                "message"  => sprintf( _("This file is not awaiting metadata. File id: %s"), $parent_id)
            ));
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Attempts to decode the JSON string
        $this->recordResult($this->getClient()->jsonDecode($metadata));

        // Exits if failed
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);}

        // Checks the received metadata JSON string can be decoded
        if (array_key_exists("result", $this->result) && NULL != $this->result["result"]) {
            $metadataObject = $this->result["result"];
        } else {
            return array(
                "code"     => OMK_Client::ERR_JSON_INVALID,
                "message"  => _("Invalid status.")
            );
        }

        // Checks the MIME type of the document uploaded
        if (array_key_exists("mime", $metadataObject) && NULL != $metadataObject["mime"]) {
            $mime = $metadataObject["mime"];
        } else {
            throw new OMK_Exception(_("Missing mime type."), self::ERR_MISSING_PARAMETER);
        }
        
        // Checks mandatory elements
        if (array_key_exists("type", $metadataObject) && NULL != $metadataObject["type"]) {
            $type = $metadataObject["type"];
        } else {
            throw new OMK_Exception(_("Missing type."), self::ERR_MISSING_PARAMETER);
        }
        
        // Exits if the MIME type doesn't appear in the client's whitelist
        if( !in_array($mime, $this->getClient()->getMimeTypeWhitelist())){
            $this->recordResult(array(
                "code"     => OMK_Client::ERR_INVALID_FORMAT,
                "message"  => _("Invalid status.")
            ));
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Updates file status to METADATA_RECEIVED
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"        => "files",
            "data"         => array(
                "type" => $type,
                "metadata" => $metadata,
                "status"   => OMK_File_Adapter::STATUS_METADATA_RECEIVED
            ),
            "where"        => array(
                "id = ?"   => $parent_id
            )
        )));

        // Exits if failed
       if( !$this->successResult()){
           throw new OMK_Exception($this->result["message"],$this->result["code"]); }
         
        // Adds to queue
        $this->recordResult( $this->getClient()->getQueue()->push(array(
                 "object_id"    => $parent_id,
                 "priority"     => OMK_Queue::PRIORITY_MEDIUM,
                 "action"       => "app_request_format",
                 "params"       => $metadata
         )) );
        
        // Exit
        return $this->getResult();

     }
     
     /**
      * Response to transcoder_cron API call
      * 
      * Who : Transcoder -> App
      * When : Transcoder cron ticks
      * What : App executes cron tasks
      * 
      * @param type $options
      * @return result struct
      * @throws OMK_Exception
      */
     protected function transcoder_cron($options = null){

         $this->recordResult( $this->validateTranscoderRequest() );
         
         // Exits if failed
         if (!$this->successResult()) {
             throw new OMK_Exception($this->result["message"],$this->result["code"]);
         }
         
         $this->getClient()->getLoggerAdapter()->log(array(
             "level"    => OMK_Logger_Adapter::INFO,
             "message"  => "Cron request at ".date("Y/m/d H:i:s")
         ));
         
        // Runs the cron action
        $cron = new OMK_Cron( $options );
        $cron->setClient($this->getClient());
        return $cron->run();
         
     }
     
     /**
      * 
      * Handles files uploads
      * 
      * @param type $request
      * @return type
      * @throws OMK_Exception
      */
     protected function upload($request = null){
         
        $this->recordResult( 
            $this->getClient()->getUploadAdapter($request)->upload($request) 
        );
        if( ! $this->successResult() ){
            return; // Failed to upload or upload in progress
        }
        $file_path      = $this->result["file_path"];
        $file_name      = $this->result["file_name"];
        $upload_adapter = $this->result["upload_adapter"];
        $fileData               = array();
        $fileData["database"]   = array(
            "owner_id"          => $this->getClient()->getAuthentificationAdapter()->getUserId(),
            "file_name"         => $file_name,
            "file_path"         => $file_path,
            "upload_adapter"    => $upload_adapter,
            "status"            => OMK_File_Adapter::STATUS_UPLOADED,
            "settings_id"          => OMK_Settings_Manager::SETTINGS_TYPE_ORIGINAL
        );
        
        // Attempts to insert file row into db
        $this->recordResult($this->getClient()->getDatabaseAdapter()->insert(array(
            "table" => "files",
            "data"  => $fileData["database"]
        )));
        
        // Exits if failed
        if (!$this->successResult()) {
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Attempts to create file on storage
        $fileData["database"]["id"] = $this->result["id"];
        $this->recordResult( 
            $this->getClient()->getFileAdapter()->create(
                    array(
                    "file_id"   => $fileData["database"]["id"],
                    "file_name" => $file_name,
                    "file_path" => $file_path
                )
            )
        );
        
        // Exits if failed
        if (!$this->successResult()) {
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Update file record in db 
        $fileData["database"]["status"] =  OMK_File_Adapter::STATUS_STORED;
        $fileData["database"]["dt_updated"] = OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP;
        $fileData["database"]["file_path"] = $this->result["file_path"];
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->update(array(
                "table" => "files",
                "where" => array(
                    "id = ?"    => $fileData["database"]["id"]
                    ),
                "data"  => $fileData["database"]
            ))
        );
        
        // Exits if failed
        if (!$this->successResult()) {
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Adds file transcoding request to queue
        $this->recordResult( 
            $this->getClient()->getQueue()->push(
                array(
                    "priority"      => OMK_Queue::PRIORITY_HIGH,
                    "action"        => "app_new_media",
                    "object_id"     => $fileData["database"]["id"],
                )
            )
        );
        
        // Exits 
        return $this->getResult();

    }
    
    /**
     * 
     * Validates the conformity of transcoder request
     * 
     * @return array [code,message]
     */
    protected function validateTranscoderRequest(){
        
        // Exits if key parameter missing
        if (array_key_exists("app_key", $_REQUEST) && NULL != $_REQUEST["app_key"]) {
            $app_key = $_REQUEST["app_key"];
        } else {
            return array(
                "code"          => self::ERR_MISSING_REQUEST_PARAMETER,
                "message"       => _("Invalid transcoder request : missing api transcoder key.")
            );
        }
        
        // Retrieves valid keys. For future multiple transcoder options, put in array form
        $valid_app_key = $this->getClient()->getAppKey();
        
        // Exits if key not in valid keychain
        if( $app_key != $valid_app_key ){
            return array(
                "code"          => self::ERR_INVALID_KEY,
                "message"       => _("Invalid transcoder request : invalid api transcoder key.")
            );
        }
        
        // Exits with success
        return array(
            "code"      => 0,
            "message"   => _("Valid transcoder request.")
        );
        
    }
}