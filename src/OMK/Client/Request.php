<?php 

/**
 * Takes care of all request originating from omk client
 * 
 * It uses internally a PEAR Http Request object for making http calls
 * 
 * @see Http_Request2
 */
class OMK_Client_Request extends OMK_Client_Friend{

    // ERR 200->225
    const ERR_HTTP              = 200;
    const ERR_MISSING_FILE_INFO = 201;
    const ERR_MISSING_FILE_ID   = 202;
    const ERR_PARENT_FILE       = 203;
    const ERR_INVALID_STATUS    = 204;
    const ERR_TRANSCODEFILEDATA = 205;
    const ERR_METADATA          = 206;
    const ERR_MISSING_API_KEY   = 207;
    
    
    /**
     *
     * @var Http_Request2
     */
    protected $requestObject;
    protected $queryParams = array();
    protected $body;

    /**
     * Accepts additional target related parameters
     * 
     * @param OMK_Client $client
     */
    public function __construct(OMK_Client &$client) {
        $this->client = $client;
        $this->queryParams["version"] = $this->getClient()->getVersion();
        $this->queryParams["application"] = $this->getClient()->getApplicationName();
        $this->queryParams["transcoder_key"] = $this->getClient()->getTranscoderKey();
    }
    
    /**
     * Acts as dispatcher for a bunch of params, routing to the right method
     * 
     * @param array $params
     * @return result struct
     */
    public function run( $params ){
        $action = $params["action"];
        return $this->$action($params);
    }

     /**
      * Makes the glu with the Http Request library
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
    
    /**
     * Performs the actual HTTP call
     * 
     * @param array $options
     * @return type
     * @throws OMK_Exception
     */
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
//        if( $response->getStatus() >= 500){
//            // failed to reach url
//            $msg = sprintf(_("An error occured : %s"), $response->getReasonPhrase());
//            $this->getClient()->getLoggerAdapter()->log(array(
//               "level"          => OMK_Logger_Adapter::WARN,
//                "message"       => $msg,
//            )); 
//            return array(
//                "code"      => $response->getStatus(),
//                "message"   => $msg
//            );
//        }

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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
         

        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse($response));

        // Exits if failed
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}

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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse());

        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        if( empty($this->body)) {
            throw new OMK_Exception(_("Missing body."), self::ERR_MISSING_PARAMETER);
        }
        
        // Reads subscription result
        $jsonArray      = $this->result["result"];
        if (array_key_exists("apikey", $jsonArray) && NULL != $jsonArray["apikey"]) {
            $transcoder_key = $jsonArray["apikey"];
        } else {
            throw new OMK_Exception(_("Missing api key in {$this->body}"),self::ERR_MISSING_API_KEY);
        }
        
        // Checks transcoder response
        
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
        
        // Exits if failed
        if( ! $this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }

        if (array_key_exists("settings", $jsonArray) && NULL != $jsonArray["settings"]) {
            $settings = $jsonArray["settings"];
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }

        $settingsInstance   = new OMK_Settings_Manager();
        $settingsInstance->setClient($this->getClient());
        $this->recordResult($settingsInstance->receive(array(
            "settings"          => $settings,
            "name"              => $transcoder_url
            )));        
        if( ! $this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse());
        
        // Exits if failed
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}

        // Gets server response as array
        $response = $this->result["result"];
        
        // Exits if failed
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Updates file record in db 
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->update(array(
                "dt_updated"    => "NOW",
                "table"         => "files",
                "id"            => $options["id"],
                "data"  => array(
                    "status"    => OMK_File_Adapter::STATUS_METADATA_REQUESTED,
                    "dt_updated" => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
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
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}

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
        
        // Attempts to read media metadata
        if (array_key_exists("metadata", $fileData) && !is_null($fileData["metadata"])) {
            $metadata = $fileData["metadata"];
        } else {
            throw new OMK_Exception("Missing parameter metadata", self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve settings list from strategy
        $this->recordResult($this->getClient()->getSettingsStrategy()->getSettingsIdList(array(
            "metadata"  => $metadata,
            "type"      => $type
        )));
        
        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
       
        // Attempts to retrieve the Settings List
        if (array_key_exists("settingsList", $this->result) && is_array($this->result["settingsList"])) {
            $settingsList = $this->result["settingsList"];
        } else {
            throw new OMK_Exception("Missing parameter settingsList", self::ERR_MISSING_PARAMETER);
        }
        
        $settingsIdList = OMK_Settings_Manager::buildIdList($settingsList);
        
        $this->queryParams["settings_id_list"] = $settingsIdList;

        // Sends format to transcoder
        $this->queryParams["id"] = $object_id;
        $this->recordResult($this->send(array(
            "action"    => "app_request_format",
        )));
 
        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
       
        // Updates files status
        $this->recordResult($this->getClient()->getDatabaseAdapter()->update(array(
            "table" => "files",
            "data"  => array(
                "status"        => OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED,
                "dt_updated"    => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
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
        foreach ($settingsList as $theSetting){
            
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
    
    
    /**
     * 
     * @param type $options
     * @return type
     * @throws OMK_Exception
     */
    protected function app_get_media( $options ){
    
        // Validates mandatory parameters
        if (array_key_exists("object_id", $options) && NULL != $options["object_id"]) {
            $id = $options["object_id"];
        } else {
            throw new OMK_Exception(_("Missing object_id."),  self::ERR_MISSING_PARAMETER);
        }
        
        
        // Attemps to retrieve the file 
        $this->recordResult( $this->getClient()->getDatabaseAdapter()->select(array(
            "table"        => "files",
            "where"        => array(
                "id = ?"     => $id,
            )
        )) );
        
        // Exits if failed
        if( !$this->successResult()){ 
            $msg = sprintf(_("Couldn't load the file supposed to receive chunks, invalid id#%s",$id));
            throw new OMK_Exception($msg,self::ERR_INVALID_PARAMETER);
        }
        
        // Initialize the file data container
        $fileData = array(
            "storage"   => array(),
            "database"  => array()
        );
        
        // Asserts the existence of the record in database
        $rows          = $this->result["rows"];
        if( ! count( $rows) ){
            return array(
                "code"     => OMK_File_Adapter::ERR_STATUS_INVALID,
                "message"  => _("Invalid file id or settings id : couldn't find the a valid file request.")
            );
        }else{
            $fileData["database"]   = current( $rows );
        }
        

        // Attempts to retrieve metadata object
        if( array_key_exists("metadata",$fileData["database"]) && count($fileData["database"]["metadata"]) ){
            $metadata_string        = $fileData["database"]["metadata"];
        } else {
            throw new OMK_Exception(_("Missing metadata."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to convert back metadata string
        $this->recordResult( $this->getClient()->jsonDecode($metadata_string));
        
        // Exits if failed
        if( ! $this->successResult() ){ 
            $msg = sprintf(_("Failed to decode metadata string of transcode file#%s",$id)); 
            throw new OMK_Exception($msg,self::ERR_METADATA);
        }
        
        // Attempts to retrieve metadata string
        if( array_key_exists("result",$this->result) && NULL != $this->result["result"]){
            $metadataObject         = $this->result["result"];
        } else {
            throw new OMK_Exception(_("Missing result."), self::ERR_MISSING_PARAMETER);
        }
       
        
        //  Attempts to retrieve the setting id
        if (array_key_exists("settings_id", $fileData["database"]) && NULL != $fileData["database"]["settings_id"]) {
            $settings_id = $fileData["database"]["settings_id"];
        } else {
            throw new OMK_Exception(_("Missing settings_id."));
        }
        
        // Attempts to retrieve the type
        if( array_key_exists("type",$fileData["database"]) && NULL != $fileData["database"]["type"]){
            $type = $fileData["database"]["type"];
        } else {
            throw new OMK_Exception(_("Missing type."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve the optional adapter
        if( array_key_exists("upload_adapter",$fileData["database"]) && NULL != $fileData["database"]["upload_adapter"]){
            $adapter = $fileData["database"]["upload_adapter"];
        } else {
            $adapter = $this->getClient()->getUploadAdapter()->getName();
        }
        $uploadAdapter = $this->getClient()->getUploadAdapter(array("upload_adapter"=>$adapter));
        
        // Retrieves parent_id
        if( array_key_exists("parent_id",$fileData["database"]) && ! is_null( $fileData["database"]["parent_id"] )){$parent_id = $fileData["database"]["parent_id"];} 
        // Failed at retrieving variable $parent_id
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing parent_id."), self::ERR_MISSING_PARAMETER);}

        // Attempts to retrieve the optional cardinality 
        if (array_key_exists("cardinality", $metadataObject) && NULL != $metadataObject["cardinality"]) {
            $cardinality = $metadataObject["cardinality"];
        } else {
            $cardinality = NULL;
        }
        
        // Gets the parent file db record
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(array(
            "table"     => "files",
            "where"     => array(
                "id = ?" => $parent_id,
                "status != ?"  => OMK_File_Adapter::STATUS_TRANSCODE_COMPLETE
            )
        )));
        
        // Exits if failed
        if( !$this->successResult()){
            throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Failed to retrieve parent id."), self::ERR_PARENT_FILE);
        }
       
        // Checks the validity of the db result
        if (array_key_exists("rows", $this->result) && count($this->result["rows"]) ) {
            $parentFileData = current( $this->result["rows"] );
        } else {
            throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("The media parent is not expecting new transcoding format."), self::ERR_INVALID_STATUS);
        }
        
        // Gets the parent file name
        if (array_key_exists("file_name", $parentFileData) && NULL != $parentFileData["file_name"]) {
            $parent_file_name = $parentFileData["file_name"];
        } else {
            throw new Exception(__CLASS__."::".__METHOD__." = "._("Missing file name."),self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve transcoded file name, path, serial
        $this->recordResult($this->getClient()->getFileAdapter()->getTranscodedFileData(array(
            "id"            => $parent_id,
            "settings_id"   => $settings_id,
            "file_name"     => $parent_file_name,
            "cardinality"   => $cardinality
        )));

        // Exits if failed
        if( !$this->successResult()){
            $msg = sprintf(_("Failed to retrieve Transcode file data for file#%s settings_id#%s"),$id,$settings_id);
            throw new OMK_Exception($msg,self::ERR_TRANSCODEFILEDATA);
        }

        // Retrieves serial
        if( array_key_exists("serial",$this->result) && ! is_null( $this->result["serial"] )){$serial = $this->result["serial"];} 
        // Failed at retrieving variable $serial
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing serial."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves file_name
        if( array_key_exists("file_name",$this->result) && ! is_null( $this->result["file_name"] )){$fileData["storage"]["file_name"] = $this->result["file_name"];} 
        // Failed at retrieving variable $file_name
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing file_name."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves file_path
        if( array_key_exists("file_path",$this->result) && ! is_null( $this->result["file_path"] )){$fileData["storage"]["file_path"] = $this->result["file_path"];} 
        // Failed at retrieving variable $file_path
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing file_path."), self::ERR_MISSING_PARAMETER);}
       
        
        // Validates the file existence on disk
        if( ! file_exists($fileData["storage"]["file_path"]) ){
            $fileData["storage"]["file_size"] = 0;
            
            // Attempts to create the file on storage
            if( ! touch( $fileData["storage"]["file_path"] ) ){
                throw new Exception(__CLASS__."::".__METHOD__." = "._("File could not be initialized."),OMK_File_Adapter::ERR_STORAGE_FILE_PATH);
            }

        }else{
            $fileData["storage"]["file_size"] = $this->getClient()->getFileAdapter()->getFileSize($fileData["storage"]["file_path"]);
        }
        
        // Validates the file is writable
        if( ! is_writable($fileData["storage"]["file_path"]) ){
            throw new Exception(__CLASS__."::".__METHOD__." = "._("File is not writable."),OMK_File_Adapter::ERR_STORAGE_FILE_PATH);
        }
        
        // Updates database record 
        $fileData["database"]["file_path"]     = $fileData["storage"]["file_path"];
        $fileData["database"]["file_name"]     = $fileData["storage"]["file_name"];
        $fileData["database"]["status"]        = OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY;
        $fileData["database"]["dt_created"]    = OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP;

        $this->recordResult($this->getClient()->getDatabaseAdapter()->update(array(
            "table"     => "files",
            "id"        => $fileData["database"]["id"],
            "data"      => $fileData["database"]
        )));

        // Exits if failed
        if (!$this->successResult()) {
            throw new Exception(__CLASS__."::".__METHOD__." = "._("Failed to update transcoded file data: ".$this->result["message"]),$this->result["code"]);
        }

        // Retrieves file_size
        if( array_key_exists("file_size",$metadataObject) && ! is_null( $metadataObject["file_size"] )){$full_size = $metadataObject["file_size"];} 
        // Failed at retrieving variable $file_size
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing file_size."), self::ERR_MISSING_PARAMETER);}
        
        // Validates files is not already fully available on disk (NAS case for example)
        if( $fileData["storage"]["file_size"] >= $full_size ){
            
             $this->recordResult($this->getClient()->getFileAdapter()->onEndTranscodeAppend(array(
                 "fileData"     => $fileData,
                 "finished"     => TRUE
             )));
            
            return array(
                "code"      => self::ERR_OK,
                "message"   => sprintf(_("The file is already uploaded")),
                "status"    => OMK_Queue::STATUS_SUCCESS
            );
            
        }else{
            $fileData["storage"]["full_size"] = $full_size;
        }
        
        // Validates existing file record is awaiting more data
        if( ! in_array($fileData["database"]["status"], array( OMK_File_Adapter::STATUS_TRANSCODE_PARTIALLY,OMK_File_Adapter::STATUS_TRANSCODE_READY))){
            throw new Exception(__CLASS__."::".__METHOD__." = "._("This media is not expecting more data."),OMK_File_Adapter::ERR_STATUS_COMPLETE);
        }
        
        // Attempts to determine request range
        $this->recordResult( $uploadAdapter->getFileContentRange($fileData["storage"]));
        
        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
       
        // Retrieves content range string
        if( array_key_exists("content_range",$this->result)){
            $content_range = $this->result["content_range"];
        } else {
            throw new OMK_Exception(_("Missing content_range."), self::ERR_MISSING_PARAMETER);
        } 
        
        // Attempts to retrieve the final flag if last chunk
        if( array_key_exists("finished",$this->result) && NULL != $this->result["finished"]){
            $finished = $this->result["finished"];
        } else {
            $finished = FALSE;
        }
        
        // Sets query params
        $this->queryParams["content_range"] = $content_range;
        $this->queryParams["id"]            = $fileData["database"]["parent_id"];
        $this->queryParams["settings_id"]   = $fileData["database"]["settings_id"];
        $this->queryParams["adapter"]       = $uploadAdapter->getProtocol();
        $this->queryParams["serial"]        = $serial;
        
        
        // TODO : The upload (either partial|total|null) should be done by the RIGHT upload adapter 
        // TODO : The final storage should be handled by the file adapter
        
        // Attempts to load data
        $this->recordResult( $this->send(array(
            "action" => "app_get_media"
        )));
        
        /**
         * @var HTTP_Request2_Response
         */
        $response;
        
        // Attempts to retrieves response
        if( array_key_exists("response",$this->result) && $this->result["response"] instanceof HTTP_Request2_Response){
            $response = $this->result["response"];
        } else {
            throw new OMK_Exception(_("Missing response."), self::ERR_MISSING_PARAMETER);
        }
        
        // Fails if response is not a 200 
        if( $response->getStatus() != 200){
            throw new OMK_Exception(_("Transcode file data chunk request failed"),self::ERR_HTTP);
        }
       
        // Attempts to append new data to the file on disk
        $this->recordResult( $this->getClient()->getFileAdapter()->append(array(
            "file_path" => $fileData["storage"]["file_path"],
            "data"      => $response->getBody()
        )));
        
        // Exits if failed
        if( !$this->successResult()){
            throw new OMK_Exception(_("Failed to append chunk to transcode file: {$this->result["message"]}"),$this->result["code"]);
        }
       
        // Attempts to retrieve the new file size
        if (array_key_exists("file_size", $this->result) && !is_null($this->result["file_size"])) {
            $fileData["storage"]["file_size"] = $this->result["file_size"];
        } else {
            throw new OMK_Exception(_("Missing parameter file_size"), self::ERR_MISSING_PARAMETER);
        }
        
        // Runs operations linked to end of transfer of this file
        $this->recordResult($this->getClient()->getFileAdapter()->onEndTranscodeAppend(array(
            "fileData"      => $fileData,
            "finished"      => $finished
        )));
        
        // Attempts to unfold archive if required
        if( $finished && $cardinality > 1){
            
            $this->getClient()->getFileAdapter()->extractArchive(array(
                "file_path"   => $fileData["storage"]["file_path"]
            ));
        }
        
        return array(
            "code"      => 0,
            "message"   => _("Format received."),
            "status"    => OMK_Queue::STATUS_SUCCESS
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
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        // Attempts to convert response
        $this->recordResult($this->decodeResponse($response));
        
        // Exits if failed
        if( ! $this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Reads subscription result
        $jsonArray      = $this->result["result"];

        if (array_key_exists("settings", $jsonArray) && NULL != $jsonArray["settings"]) {
            $settingsList   = $jsonArray["settings"];
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }

        // Records settings
        $settingsInstance   = new OMK_Settings_Manager();
        $settingsInstance->setClient($this->getClient());
        $this->recordResult($settingsInstance->receive(array(
            "settings"          => $settingsList,
            "name"              => $transcoder_url
            )));        
        if( ! $this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        
        
        return $this->getResult();
                  
         
     }

}