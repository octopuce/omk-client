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
        $this->$action($options);
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
    preset:string
    url:string or list of strings (for thumbs)

onSuccess

    App updates media_format status to TRANSCODED or INVALID
    App updates media status to META_RECEIVED or META_INVALID

onError : Transcode logs error

      */
     protected function transcoder_send_format($options = null){
         
        // Checks transcoder's request sanity
        $this->recordResult( $this->validateTranscoderRequest() );

        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}

        // Retrieves file id - format - (option) upload_adapter
        if (array_key_exists("id", $_REQUEST) && NULL != $_REQUEST["id"]) {
            $object_id = $_REQUEST["id"];
        } else {
            throw new OMK_Exception(_("Missing id."), self::ERR_MISSING_REQUEST_PARAMETER);
        }

        if (array_key_exists("format", $_REQUEST) && NULL != $_REQUEST["format"]) {
            $format = $_REQUEST["format"];
        } else {
            throw new Exception(_("Missing format."));
        }
        
        if (array_key_exists("adapter", $_REQUEST) && NULL != $_REQUEST["adapter"]) {
            $adapter = $_REQUEST["adapter"];
        } else {
            $adapter = $this->getClient()->getUploadAdapter()->getTransportName();
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
        if( !$this->successResult()){return $this->getResult();}
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            return array(
                "code"     => OMK_File_Adapter::ERR_STATUS_INVALID,
                "message"  => _("Invalid status.")
            );
        }else{
            $fileData["database"] = current( $rows );
        }
        
        // Updates file
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"        => "files",
            "data"         => array(
                "metadata" => $metadata,
                "status"   => OMK_File_Adapter::STATUS_TRANSCODE_READY
            ),
            "where"        => array(
                "id = ?"   => $object_id
            )
        )) );

        // Exits if failed
       if( !$this->successResult()){ return $this->getResult(); }
         
        // Adds to queue
        $this->recordResult( $this->getClient()->getQueue()->push(array(
            "object_id"        => $object_id,
            "action"           => "app_get_format",
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
                "id = ?"        => $fileData["database"]["parent_id"],
                "status NOT ? " => OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY
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
        if( !$this->successResult()){return $this->getResult();}

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
                "id = ?"       => $parent_id,
                "status = ?"   => OMK_File_Adapter::STATUS_METADATA_REQUESTED
            )
        )) );

        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            return array(
                "code"     => OMK_File_Adapter::ERR_STATUS_INVALID,
                "message"  => _("Invalid status.")
            );
        }
        
        // Attempts to decode the JSON string
        $this->recordResult($this->getClient()->jsonDecode($metadata));

        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}

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
        if (array_key_exists("mime_type", $metadataObject) && NULL != $metadataObject["mime_type"]) {
            $mime_type = $metadataObject["mime_type"];
        } else {
            $this->recordResult(array(
                "code"     => self::ERR_INVALID_PARAMETER,
                "message"  => _("Missing mime type.")
            ));
            return $this->getResult();
        }
        
        // Checks mandatory elements
        if (!array_key_exists("type", $metadataObject) && NULL != $metadataObject["type"]) {
            $this->recordResult(array(
                "code"     => self::ERR_INVALID_PARAMETER,
                "message"  => _("Missing type.")
            ));
            return $this->getResult();
        }
        
        // Exits if the MIME type doesn't appear in the client's whitelist
        if( !in_array($mime_type, $this->getClient()->getMimeTypeWhitelist())){
            $this->recordResult(array(
                "code"     => OMK_Client::ERR_INVALID_FORMAT,
                "message"  => _("Invalid status.")
            ));
            return $this->getResult();
        }
        
        // Updates file 
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"        => "files",
            "data"         => array(
                "metadata" => $metadata,
                "status"   => OMK_File_Adapter::STATUS_METADATA_RECEIVED
            ),
            "where"        => array(
                "id = ?"   => $parent_id
            )
        )));

        // Exits if failed
       if( !$this->successResult()){ return $this->getResult(); }
         
        // Adds to queue
        $this->recordResult( $this->getClient()->getQueue()->push(array(
                 "object_id"    => $parent_id,
                 "priority"     => OMK_Queue::PRIORITY_MEDIUM,
                 "action"       => "app_get_format",
         )) );
        
        // Exit
        return $this->getResult();

     }
     
     /*
      * transcoder_cron
        Who : Transcoder -> App
        When : Transcoder cron ticks
        What : App executes cron tasks
        Request Params : null
        onSuccess : null
        onError : Transcoder logs error
      */
     protected function transcoder_cron($options = null){

         $this->recordResult( $this->validateTranscoderRequest() );
         
         // Exits if failed
         if (!$this->successResult()) {
             return $this->getResult();
         }
         
        // Runs the cron action
        $cron = new OMK_Cron( $options );
        $cron->setClient($this->getClient());
        $this->recordResult(
             $cron->run()
        );
         
     }
     
     // Manages upload
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
            "settings_id"          => OMK_Settings::SETTINGS_TYPE_ORIGINAL
        );
        
        // Attempts to insert file row into db
        $this->recordResult($this->getClient()->getDatabaseAdapter()->insert(array(
            "table" => "files",
            "data"  => $fileData["database"]
        )));
        
        // Exits if failed
        if (!$this->successResult()) {
            return $this->getResult();
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
            return $this->getResult();
        }
        
        // Update file record in db 
        $fileData["database"]["status"] =  OMK_File_Adapter::STATUS_STORED;
        $fileData["database"]["dt_updated"] = OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP;
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
            return $this->getResult();
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