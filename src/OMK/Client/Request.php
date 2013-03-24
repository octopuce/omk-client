<?php 

require_once 'HTTP/Request2.php';

class OMK_Client_Request extends OMK_Client_Friend{

    // ERR 200->225
    const ERR_HTTP              = 200;
    const ERR_MISSING_FILE_INFO = 201;
    const ERR_MISSING_FILE_ID   = 202;
    
    protected $requestObject;
    protected $queryParams = array();


    public function __construct(OMK_Client &$client) {
        $this->client = $client;
        $this->queryParams["version"] = $this->getClient()->getVersion();
        $this->queryParams["application"] = $this->getClient()->getApplicationName();
        $this->queryParams["key"] = $this->getClient()->getTranscoderKey();
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
                $method = Http_Request2::METHOD_GET;
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
         
        $this->result = array("code"=>0,"message"=>"ok");
        $this->recordResult(
            $this->send(array(
                "action" => "app_test_response"
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
            throw new Exception(_("Missing email."));
        }

        if (array_key_exists("api_transcoder_url", $_REQUEST) && NULL != $_REQUEST["api_transcoder_url"]) {
            $api_transcoder_url = $_REQUEST["api_transcoder_url"];
        } else {
            throw new Exception(_("Missing api_transcoder_url."));
        }
        
        $this->getRequestObject(array(
            "url"  => $api_transcoder_url
        ));

         
        // Gets response
        $this->recordResult($this->send(array(
            "action"=>"app_subscribe",
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
            $api_transcoder_key = $jsonArray["apikey"];
        } else {
            throw new Exception(_("Missing api key."));
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
                     "id"  => "api_transcoder_key",
                     "val"  => $api_transcoder_key
                 ),
                 "where"    => array(
                     "id = ?" => "api_transcoder_key"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                 "table"    => "variables",
                 "data"     => array(
                     "id"  => "api_transcoder_url",
                     "val"  => $api_transcoder_url
                 ),
                 "where"    => array(
                     "id = ?" => "api_transcoder_url"
                 )
             )            
        ));        
        if( ! $this->successResult()){
            return $this->getResult();
        }

        if (array_key_exists("settings", $jsonArray) && NULL != $jsonArray["settings"]) {
            $settings = $jsonArray["settings"];
        } else {
            throw new Exception(_("Missing settings."));
        }

        $settingsInstance   = new OMK_Settings();
        $settingsInstance->setClient($this->getClient());
        $this->recordResult($settingsInstance->receive(array(
            "settings"          => $settings,
            "name"              => $api_transcoder_url
            )));        
        if( ! $this->successResult()){
            return $this->getResult();
        }
        
        
        return $this->getResult();
         
     }
     
     protected function decodeResponse( ){
         
        $response       = $this->result["response"];
        $body           = $response->getBody();
        return $this->getClient()->jsonDecode($body);
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
            throw new Exception(_("Missing object id."));
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
        $this->queryParams["action"]    = "app_new_media";
        $this->queryParams["url"]       = $download_url;
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
                    "status"    => OMK_Database_Adapter::STATUS_METADATA_REQUESTED
                )
            ))
        );
        
        $this->result["status"] = OMK_Queue::STATUS_SUCCESS;
        return $this->getResult();
    }
   
    /*
     * 
app_request_format

Who : App -> Transcoder

When : Media metadata set

What : transcoder adds media formats to transcoding queue

Request Params

    id:int
    preset:format
    range

onSuccess

    App updates media status to FORMATS_REQUESTED
    App creates media_formats
    App updates media_format status to REQUESTED

onError : App logs error
     */
    protected function app_request_format(){
        
        // Retrieves formats list
        
        // Builds query params

        // Sends format to transcoder
        
        // Updates files status
        
    }
}