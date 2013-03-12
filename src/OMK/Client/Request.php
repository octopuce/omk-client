<?php 

require_once 'HTTP/Request2.php';

class OMK_Client_Request extends OMK_Client_Friend{

    // ERR 200->225
    const ERR_HTTP = 200;
    
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
        $this->$action($params);
    }

     
    protected function getRequestObject($options = null) {
        
        if( NULL == $this->requestObject || count($options)){
            if( !is_array($options)){
                $options = array();
            }
            if(array_key_exists("url", $options)){
                $url = $options["url"];
            }else{
                $url = $this->client->getAppUrl();
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
    
    public function send($options = null){
        
        $query          = array();
        if( NULL == $options || !count($options)){
            throw new OMK_Exception(_("Missing options"));
        }
        if (array_key_exists("action", $options) && NULL != $options["action"]) {
            $this->queryParams["action"] = $options["action"];
        } else {
            throw new OMK_Exception(_("Missing action."));
        }
        if (array_key_exists("url", $options) && NULL != $options["url"]) {
            $url = $options["url"];
        } else {
            $url = $this->getRequestObject()->getUrl();
        }
        try{
            
            $url        .= "?".http_build_query($this->queryParams);
            echo $url;
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
            "message"   => _("Successfully sent request {$this->queryParams["action"]}.")
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
     protected function app_discovery($options = null){
         
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
         
        // Update file record in db 
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->update(array(
                "dt_updated"    => TRUE,
                "table"         => "files",
                "id"            => $file_id,
                "data"  => array(
                    "status"    => OMK_Database_Adapter::STATUS_METADATA_REQUESTED
                )
            ))
        );
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

onSuccess

    App updates media status to FORMATS_REQUESTED
    App creates media_formats
    App updates media_format status to REQUESTED

onError : App logs error
     */
    protected function app_request_format(){
        
    }
}