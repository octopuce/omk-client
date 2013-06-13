<?php 


class OMK_Client_Request extends OMK_Client_Friend{

    // ERR 200->225
    const ERR_HTTP              = 200;
    const ERR_MISSING_FILE_INFO = 201;
    const ERR_MISSING_FILE_ID   = 202;
    
    protected $requestObject;
    protected $queryParams = array();
    protected $body;


    public function __construct(OMK_Client &$client) {
        $this->client = $client;
        $this->queryParams["version"] = $this->getClient()->getVersion();
        $this->queryParams["application"] = $this->getClient()->getApplicationName();
        $this->queryParams["transcoder_key"] = $this->getClient()->getTranscoderKey();
    }
    
    public function run( $params ){
        $action = $params["action"];
        return $this->$action($params);
    }

     /**
      * 
      * @param array $options
      * @return Http_Request2
      */
    protected function getRequestObject($options = array()) {
        
        if( NULL == $this->requestObject || count($options) ){
            if(array_key_exists("url", $options)){
                $url = $options["url"];
            }else{
                $url = $this->getClient()->getTranscoderUrl();
            }
            if(array_key_exists("method", $options)){
                $method = $options["method"];
            }else{
                $method = HTTP_Request2::METHOD_GET;
            }
            if(array_key_exists("config", $options)){
                $config = $options["config"];
            }  else {
                $config = array();
            }
            $this->requestObject = new HTTP_Request2($url, $method, $config);
        }
        return $this->requestObject;
        
    }
    
    public function send($options = array()){
        
        $query          = array();
        if (array_key_exists("action", $options) && NULL != $options["action"]) {
            $this->queryParams["action"] = $options["action"];
        } else if( !array_key_exists("action", $this->queryParams)) {
            throw new OMK_Exception(_("Missing action."));
        }
        if (array_key_exists("url", $options) && NULL != $options["url"]) {
            $url = $options["url"];
        } else {
            $url = $this->getRequestObject()->getUrl();
        }
        if (array_key_exists("params", $options) && NULL != $options["params"]) {
            $this->queryParams = array_merge( $options["params"], $this->queryParams );
        }
        try{
            
            $url        .= "?".http_build_query($this->queryParams);
            $response   = $this->getRequestObject()
                                ->setUrl($url)
                                ->send();
            
        } catch (HTTP_Request2_Exception $e) {
            $msg = sprintf(_("An error occured : %s"), $e->getMessage());
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"          => OMK_Logger_Adapter::WARN,
                "message"       => $msg,
                "exception"     => $e
            )); 
            return array(
                "code"      => self::ERR_HTTP,
                "message"   => $msg
            );
        }    
        if( $response->getStatus() >= 400){
            // failed to reach url
            $msg = sprintf(_("An error occured : %s"), $response->getReasonPhrase());
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"          => OMK_Logger_Adapter::WARN,
                "message"       => $msg,
            )); 
            return array(
                "code"      => $response["code"],
                "message"   => $msg
            );
        }

        return array(
            "code"      => 0,
            "message"   => sprintf(_("Successfully sent request %s."),$this->queryParams["action"]),
            "response"  => $response
        );
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
    protected function app_test_request($options = null){
         
        $this->recordResult(
            $this->send(array(
                "action" => "app_test"
            ))
        );
        return $this->getResult();
     }
     
 
    /*
     * 
Who : App -> tracker
Who : Transcoder -> App
When : whenever
What : Transcoder returns available presets
Request Params : void
onSuccess : app records available formats to make a selection
onError : void

    http://discover.openmediakit.org/ > fournit une liste de transcoders publics sous forme json
    les valeurs suivantes sont retournées pour chaque transcoder : URL racine de l'API, Version de l'API, Nom (string)
    options (array of array...): [ Pays (iso), Liste des settings supportés (cf Settings) ]

     */
     protected function tracker_autodiscovery($options = null){
         
        // Sets query params
        $this->getRequestObject(array("url" => "http://discovery.open-mediakit.org"));

        // Attempts to retrieve trackers list
        $this->recordResult($this->send(array("action"=>"autodiscovery")));
        if( !$this->successResult()){
            return $this->getResult();
        }
         

        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse($response));

        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}

        $list = $this->result["result"];
        // Inserts / Updates data
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_discovery",
                     "val"  => $body
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_discovery"
                 )
             )
        ));
         
        return $this->getResult();
         
     }
     
     /*
      * app_subscribe

Who : App -> transcoder

Who : Transcoder -> App

When : whenever

What : Transcoder returns available presets

Request Params : void

onSuccess : app records available formats to make a selection

onError : void

    Fournit les informations de l'app (URL + email de contact + nom d'appli + Version X.Y + Version API )
    Le serveur répond avec un code d'erreur + Key

      */
     protected function app_subscribe($options = null){

        if (array_key_exists("email", $_REQUEST) && NULL != $_REQUEST["email"]) {
            $email = $_REQUEST["email"];
        } else {
            throw new OMK_Exception(_("Missing email."));
        }

        if (array_key_exists("transcoder_url", $_REQUEST) && NULL != $_REQUEST["transcoder_url"]) {
            $transcoder_url = $_REQUEST["transcoder_url"];
        } else {
            throw new OMK_Exception(_("Missing transcoder_url."));
        }
        
        $this->getRequestObject(array(
            "url"  => $transcoder_url
        ));

         
        // Gets response
        $this->recordResult($this->send(array(
            "action"=>"app_subscribe",
            "params"    => array(
                "url"       => $this->getClient()->getAppUrl(),
                "email"     => $email,
                "app_key"   => $this->getClient()->getAppKey()
                )
            )));
        
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse());

        if( !$this->successResult()){
            return $this->getResult();
        }
        
        if( empty($this->body)) {
            throw new OMK_Exception(_("Missing body."), self::ERR_MISSING_PARAMETER);
        }
        
        // Reads subscription result
        $jsonArray      = $this->result["result"];
        if (array_key_exists("apikey", $jsonArray) && NULL != $jsonArray["apikey"]) {
            $transcoder_key = $jsonArray["apikey"];
        } else {
            throw new OMK_Exception(_("Missing api key."));
        }
        // Records new transcoder
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_data",
                     "val"  => $this->body
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_data"
                 )
             )            
        ));
        if( ! $this->successResult()){
            return $this->getResult();
        }

        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_key",
                     "val"  => $transcoder_key
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_key"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_url",
                     "val"  => $transcoder_url
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_url"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }

        if (array_key_exists("settings", $jsonArray) && NULL != $jsonArray["settings"]) {
            $settings = $jsonArray["settings"];
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }

        $settingsInstance   = new OMK_Settings();
        $settingsInstance->setClient($this->getClient());
        $this->recordResult($settingsInstance->receive(array(
            "settings"          => $settings,
            "name"              => $transcoder_url
            )));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        
        return $this->getResult();
         
     }
     
     protected function decodeResponse( ){
         
        $response       = $this->result["response"];
        $this->body     = $response->getBody();
        $result         = $this->getClient()->jsonDecode($this->body);
        return $result;
     }


     /*
      * 
app_new_media
Who : App -> Transcoder
When : media completely uploaded by user
What : transcoder media adds to download and metadata queue
Request Params
    set[media_id:int, media_url:string]
onSuccess : App updates media status to META_REQUESTED
onError : App logs error
      */
     protected function app_new_media($options = null){
         
        // Builds query params
        if (array_key_exists("object_id", $options) && NULL != $options["object_id"]) {
            $object_id = $options["object_id"];
        } else {
            throw new OMK_Exception(_("Missing object id."));
        }
        
        // Retrieves file information
        $this->recordResult(
            $this->getClient()->getDatabaseAdapter()->select(array(
                "table" => "files",
                "where" => array(
                    "id = ?" => $object_id
                )
            ))
        );
        
        // Exits if failed
        if( ! $this->successResult()){
            return $this->getResult();
        }
        if( ! array_key_exists( "rows", $this->result) || !count($this->result["rows"])){
            throw new OMK_Exception(_("Missing file info."), self::ERR_MISSING_FILE_INFO);
        }
        $fileData = current($this->result["rows"]);
        if ( ! array_key_exists("id", $fileData) && NULL != $fileData["id"]) {
            throw new OMK_Exception(_("Missing id."), self::ERR_MISSING_FILE_ID);
        }
        
        // Retrieves the download url
        $download_url =  $this->getClient()->getFileAdapter()->getDownloadUrl($fileData);
   
        // Builds query. Sends request
        $this->queryParams["adapter"]   = $this->getClient()->getUploadAdapter()->getProtocol();
        $this->queryParams["action"]    = "app_new_media";
        $this->queryParams["url"]       = $download_url;
        $this->queryParams["id"]        = $object_id;
        $this->recordResult($this->send(array()));
        
        // Exits if failed
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse());
        
        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}

        // Gets server response as array
        $response = $this->result["result"];
        
        // Checks transcoder response
        $this->recordResult($response);
        
        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}
        
        // Updates file record in db 
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->update(array(
                "dt_updated"    => "NOW",
                "table"         => "files",
                "id"            => $options["id"],
                "data"  => array(
                    "status"    => OMK_File_Adapter::STATUS_METADATA_REQUESTED
                )
            ))
        );
        
        $this->result["status"] = OMK_Queue::STATUS_SUCCESS;
        return $this->getResult();
    }
   
    /*
     * 
Who : App -> Transcoder
When : Media metadata set
What : transcoder adds media formats to transcoding queue
Request : JSON object ( id: int, settings: array[ int, int, ...] }
Response : code, message
onSuccess
    App updates media status to FORMATS_REQUESTED
    App creates media_formats
    App updates media_format status to REQUESTED

onError : App logs error
     */
    protected function app_request_format( $options = NULL ){

        if (NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."),self::ERR_MISSING_PARAMETER);
        }
        
        if (array_key_exists("object_id", $options) && NULL != $options["object_id"]) {
            $object_id = $options["object_id"];
        } else {
            throw new OMK_Exception(_("Missing object_id."),self::ERR_MISSING_PARAMETER);
        }
        
        if (array_key_exists("params", $options) && NULL != $options["params"]) {
            $params = $options["params"];
        } else {
            throw new OMK_Exception(_("Missing params."),self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to load file data
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(array(
            "table"     => "files",
            "where"     => array(
                "id = ?" => $object_id
            )
        )));

        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}

        if( array_key_exists("rows", $this->result) && NULL != $this->result["rows"] && count($this->result["rows"] )) {
            $fileData  = current( $this->result["rows"] );
        } else {
            return array(
                "code"      => self::ERR_INVALID_PARAMETER,
                "message"   => _("Invalid file data")
            );
        }
        
        // Attempts to read media type
        if (array_key_exists("type", $fileData) && NULL != $fileData["type"]) {
            $type = $fileData["type"];
        } else {
            throw new OMK_Exception(_("Missing type."),self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to load settings to be requested
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(array(
            "table" => "settings",
            "where" => array(
                "type = ?"      => $type,
                "checked = ?"   => OMK_Settings::CHECKED,
                "available = ?" => OMK_Settings::AVAILABLE_TRUE
            )
        )));
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
        
        // Checks the validity of the db result
        if (array_key_exists("rows", $this->result) && count($this->result["rows"]) ) {
            $settings = $this->result["rows"];
        } else {
            $this->recordResult(array(
                "code"      => self::ERR_MISSING_PARAMETER,
                "message"   => sprintf( _("No settings for the %s media type."), $fileData["type"])
            ));
            return $this->getResult();
        }

        // Builds query params
        $settingsIdList = array();
        foreach( $settings as $theSetting){
            $settingsIdList[] = $theSetting["id"];
        }
        $this->queryParams["settings_id_list"] = $settingsIdList;

        // Sends format to transcoder
        $this->queryParams["id"] = $object_id;
        $this->recordResult($this->send(array(
            "action"    => "app_request_format",
        )));
 
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        // Updates files status
        $this->recordResult($this->getClient()->getDatabaseAdapter()->update(array(
            "table" => "files",
            "data"  => array(
                "status"    => OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED,
            ),
            "where" => array(
                "id = ?"    => $object_id,
            )
        )));
        
        $newFileData = array(
             "parent_id"     => $fileData["id"],
             "owner_id"      => $fileData["owner_id"],
             "status"        => OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED
         );

        // Inserts childs records
        foreach ($settings as $theSetting){
            
            $newFileData["settings_id"] = $theSetting["id"];
            $newFileData["type"]        = $theSetting["type"];
            $this->recordResult($this->getClient()->getDatabaseAdapter()->insert(array(
                "table"     => "files",
                "data"      => $newFileData
            )));
            
        }
        $this->result["status"] = OMK_Queue::STATUS_SUCCESS;
        
        return $this->getResult();
    }
    
    
    protected function app_get_format(){
    
        // Validates mandatory parameters
        if (array_key_exists("object_id", $options) && NULL != $options["object_id"]) {
            $parent_id = $options["object_id"];
        } else {
            throw new OMK_Exception(_("Missing object_id."),  self::ERR_MISSING_PARAMETER);
        }
        
        if (array_key_exists("params", $options) && NULL != $options["params"]) {
            $params = $options["params"];
            $this->recordResult( $this->getClient()->jsonDecode($params));
            // Exits if failed
            if( ! $this->successResult() ){ return $this->getResult(); }
            
            if( array_key_exists("result",$this->result) && NULL != $this->result["result"]){
                $params = $this->result["result"];
            } else {
                throw new OMK_Exception(_("Missing result."), 1);
            }
        } else {
            throw new OMK_Exception(_("Missing paramseters."),  self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve metadata object
        if( array_key_exists("metadata",$params) && count($params["metadata"]) ){
            $metadata = $params["metadata"];
        } else {
            throw new OMK_Exception(_("Missing metadata."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to convert back metadata string
        $this->recordResult( $this->getClient()->jsonEncode($metadata));
        
        // Exits if failed
        if( ! $this->successResult() ){ return $this->getResult(); }
        
        // Attempts to retrieve metadata string
        if( array_key_exists("result",$this->result) && NULL != $this->result["result"]){
            $metadata_string = $this->result["result"];
        } else {
            throw new OMK_Exception(_("Missing result."), self::ERR_MISSING_PARAMETER);
        }
       
        //  Attempts to retrieve the setting id
        if (array_key_exists("settings_id", $params) && NULL != $params["settings_id"]) {
            $settings_id = $params["settings_id"];
        } else {
            throw new Exception(_("Missing settings_id."));
        }
        
        // Attempts to retrieve the type
        if( array_key_exists("type",$params) && NULL != $params["type"]){
            $type = $params["type"];
        } else {
            throw new OMK_Exception(_("Missing type."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve the optional cardinality 
        if (array_key_exists("cardinality", $params) && NULL != $params["cardinality"]) {
            $cardinality = $params["cardinality"];
        } else {
            $cardinality = NULL;
        }
        
        // Attempts to retrieve the optional adapter
        if( array_key_exists("adapter",$params) && NULL != $params["adapter"]){
            $adapter = $params["adapter"];
        } else {
            $adapter = NULL;
        }
        
        // Gets the parent file db record
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(array(
            "table"     => "files",
            "where"     => array(
                "id = ?"        => $object_id,
                "status NOT ?"  => OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE
            )
        )));
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        // Checks the validity of the db result
        if (array_key_exists("rows", $this->result) && count($this->result["rows"]) ) {
            $parentFileData = current( $this->result["rows"] );
        } else {
            return array(
                "code"      => self::ERR_MISSING_PARAMETER,
                "message"   => _("The media parent is not expecting new transcoding format.")
            );
        }
        
        // Gets the parent file name
        if (array_key_exists("file_name", $parentFileData) && NULL != $parentFileData["file_name"]) {
            $parent_file_name = $parentFileData["file_name"];
        } else {
            throw new Exception(_("Missing file name."));
        }
        
        // Validates the owner id
        if( array_key_exists("owner_id",$parentFileData) && NULL != $parentFileData["owner_id"]){
            $owner_id = $parentFileData["owner_id"];
        } else {
            throw new omk(_("Missing owner id."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve transcoded file name
        $this->recordResult($this->getClient()->getFileAdapter()->getTranscodedFileData(array(
            "parent_id"     => $parent_id,
            "settings_id"   => $settings_id,
            "file_name"     => $parent_file_name,
            "cardinality"   => $cardinality
        )));

        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}

        // Initialize the file data container
        $fileData = array(
            "storage"   => array(),
            "database"  => array()
        );

        // Gets the resulting filename
        if (array_key_exists("file_name", $this->result) && NULL != $this->result["file_name"]) {
            $fileData["storage"]["file_name"] = $this->result["file_name"];
        } else {
            throw new OMK_Exception(_("Missing file name."), self::ERR_MISSING_PARAMETER);
        }
        if (array_key_exists("file_path", $this->result) && NULL != $this->result["file_path"]) {
            $fileData["storage"]["file_path"] = $this->result["file_path"];
        } else {
            throw new OMK_Exception(_("Missing file path.",self::ERR_MISSING_PARAMETER));
        }
        
        // Validates the file existence on disk
        if( ! file_exists($fileData["storage"]["file_path"]) ){
            $fileData["storage"]["size"] = 0;
            
            // Attempts to create the file on storage
            if( ! touch( $fileData["storage"]["file_path"] ) ){
                return array(
                    "code"      => OMK_File_Adapter::ERR_STORAGE_FILE_PATH,
                    "message"   => _("File could not be initialized.")
                );
            }

        }else{
            $fileData["storage"]["size"] = filesize($fileData["storage"]["file_path"]);
        }
        
        // Validates the file is writable
        if( ! is_writable($fileData["storage"]["file_path"]) ){
            return array(
                "code"      => OMK_File_Adapter::ERR_STORAGE_FILE_PATH,
                "message"   => _("File is not writable.")
            );
        }
        
        // Validates the file existence in database
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
            "table" => "files",
            "where" => array(
                "parent_id = ?" => $parent_id,
                "settings_id = ?" => $settings_id
            )
        )));
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        // Inserts new files records in db
        if ( ! array_key_exists("rows", $this->result) || NULL == $this->result["rows"]) {
            $fileData["database"] = array(
                "parent_id"     => $parent_id,
                "owner_id"      => $owner_id,
                "settings_id"   => $settings_id,
                "file_path"     => $fileData["storage"]["file_path"],
                "file_name"     => $fileData["storage"]["file_name"],
                "type"          => $type,
                "metadata"      => $metadata_string,
                "status"        => OMK_File_Adapter::STATUS_TRANSCODE_READY
            );
            
            $this->recordResult($this->getClient()->getDatabaseAdapter()->insert(array(
                "table"     => "files",
                "data"      => $fileData["database"]
            )));
            
            // Exits if failed
            if (!$this->successResult()) {
                return $this->getResult();
            }
            
            // Sets the new id on the file Data record
            if (array_key_exists("id", $this->result) && NULL != $this->result["id"]) {
                $fileData["database"]["id"] = $this->result["id"];
            } else {
                throw new Exception(_("Missing id."),  self::ERR_MISSING_PARAMETER);
            }
        } 
        else {
            $fileData["database"] = current( $this->result["rows"] );
        }


        // Validates files is not already fully available on disk (NAS case for example)
        if( $fileData["storage"]["size"] == $params["metadata"]["size"] ){
            
            // TODO : update files records
            return array(
                "code"      => OMK_Upload_Adapter::ERR_FILE_FULL_SIZE,
                "message"   => sprintf(_("The file is already uploaded"))
            );
        }else{
            $fileData["storage"]["full_size"] = $params["metadata"]["size"];
        }
        
        // Validates existing file record is awaiting more data
        if( ! in_array($fileData["database"]["status"], array( OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY,OMK_File_Adapter::STATUS_TRANSCODE_READY))){
            return array(
                "code"      => OMK_File_Adapter::ERR_STATUS_COMPLETE,
                "message"   => sprintf(_("This media is not expecting more data."))
            );
        }
        
        // Attempts to determine request range
        $this->recordResult( $this->getClient()->getUploadAdapter(array("adapter"=>$adapter))->getFileContentRange($fileData["storage"]));
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        // Retrieves content range string
        if( array_key_exists("content_range",$options) && NULL != $options["content_range"]){
            $content_range = $options["content_range"];
        } else {
            throw new OMK_Exception(_("Missing content_range."), self::ERR_MISSING_PARAMETER);
        }
        if( array_key_exists("finished",$this->result) && NULL != $this->result["finished"]){
            $finished = $this->result["finished"];
        } else {
            $finished = NULL;
        }
        
        // Sets query params
        $this->queryParams["content_range"] = $content_range;
        $this->queryParams["id"]            = $fileData["database"]["id"];
       
        // Attempts to load data
        $this->recordResult( $this->send());
        
        // Attempts to retrieves response
        if( array_key_exists("response",$this->result) && NULL != $this->result["response"]){
            $response = $this->result["response"];
        } else {
            throw new omk(_("Missing response."), self::ERR_MISSING_PARAMETER);
        }
       
        // Attempts to update the file on disk
        $this->recordResult( $this->getClient()->getFileAdapter()->append(array(
            "file_path" => $fileData["storage"]["file_path"],
            "data"      => $response->getBody()
        )));
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        // Asserts the status of the files records
        if( $finished == TRUE){
            $file_status = OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY;
            $this->recordResult( $this->getClient()->getDatabaseAdapter()->count(array(
                "table"     => "files",
                "where"     => array(
                    "parent_id = ?" => $parent_id,
                    "status IN ?" => array( OMK_File_Adapter::STATUS_TRANSCODE_READY, OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY, OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED )
                )
            )));
            
            // Exits if failed
            if( ! $this->successResult() ){ return $this->getResult(); }
            
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
                        "id = ?" => $parent_id,
                    ),
                    "data"      => array(
                        "status" => OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE
                    )

                )));
            }
            
        }else{
            $file_status = OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE;
        }
        
        // Updates the file record in database
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->update(array(
            "table"     => "files",
            "where"     => array(
                "id = ?" => $fileData["database"]["id"],
            ),
            "data"      => array(
                "status" => $file_status
            )

        )));        
        
        // Exits if failed
        if( !$this->successResult()){return $this->getResult();}
       
        return array(
            "code"      => 0,
            "message"   => _("Format received.")
        );
    }

    
     /**
      * 
      * @param type $options
      * app_get_settings

Pas d'authentification requise, méthode publique

Who : Transcoder -> App

When : whenever

What : Transcoder returns available presets

Request Params : void

onSuccess : app records available formats to make a selection

onError : void
      */
     protected function app_get_settings($options =null){
         
        if (array_key_exists("email", $_REQUEST) && NULL != $_REQUEST["email"]) {
            $email = $_REQUEST["email"];
        } else {
            throw new OMK_Exception(_("Missing email."));
        }

        if (array_key_exists("transcoder_url", $_REQUEST) && NULL != $_REQUEST["transcoder_url"]) {
            $transcoder_url = $_REQUEST["transcoder_url"];
        } else {
            throw new OMK_Exception(_("Missing transcoder_url."));
        }
        
        $this->getRequestObject(array(
            "url"  => $transcoder_url
        ));

         
        // Gets response
        $this->recordResult($this->send(array(
            "action"=>"app_get_settings",
            "params"    => array(
                "url"       => $this->getClient()->getAppUrl(),
                "email"     => $email
                )
            )));
        
        if( !$this->successResult()){
            return $this->getResult();
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse($response));
        
        // Exits if failed
        if( ! $this->successResult()){return $this->getResult();}
        
        // Reads subscription result
        $jsonArray      = $this->result["result"];
        if (array_key_exists("apikey", $jsonArray) && NULL != $jsonArray["apikey"]) {
            $transcoder_key = $jsonArray["apikey"];
        } else {
            throw new OMK_Exception(_("Missing api key."));
        }
        
        // Records new transcoder
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_data",
                     "val"  => $body
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_data"
                 )
             )            
        ));
        if( ! $this->successResult()){
            return $this->getResult();
        }

        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_key",
                     "val"  => $transcoder_key
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_key"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "transcoder_url",
                     "val"  => $transcoder_url
                 ),
                 "where"    => array(
                     "id = ?" => "transcoder_url"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }

        if (array_key_exists("settings", $jsonArray) && NULL != $jsonArray["settings"]) {
            $settings = $jsonArray["settings"];
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }

        $settingsInstance   = new OMK_Settings();
        $settingsInstance->setClient($this->getClient());
        $this->recordResult($settingsInstance->receive(array(
            "settings"          => $settings,
            "name"              => $transcoder_url
            )));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        
        return $this->getResult();
                  
         
     }

}