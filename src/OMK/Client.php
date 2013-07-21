<?php 
/*
 * 
    OpenMediaKit [OMK] - A free transcoder/client software
    Copyright (C) 2013 Octopuce

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 */

// Adds to path the folder containing the client library for dependencies loading
set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__)."/../");

/*
 * APIClient
    Description:
    Contrôle les échanges entre le transcoder et le système local
    Gère l'authentification OMK.
    Methodes: 
    ping : retourne le timestamp courant du système
    readFile : renvoie un fichier
    getSettings : reçoit les formats acceptés par le transcoder
    cf. fichiers sur l'API existant
    Config : 
    Clef d'API locale
    URL locale
    Clef d'API transcoder
    URL transcoder
 */
class OMK_Client {
    
    // ERR Codes 225-249
    const ERR_EXCEPTION             = 225;
    const ERR_UNKNOWN_ACTION        = 226;
    const ERR_JSON_NONE             = 230; // original JSON_ERROR_NONE = 0
    const ERR_JSON_DEPTH            = 231; // original JSON_ERROR_DEPTH = 1
    const ERR_JSON_STATE_MISMATCH   = 232; // original JSON_ERROR_STATE_MISMATCH = 2
    const ERR_JSON_CTRL_CHAR        = 233; // original JSON_ERROR_CTRL_CHAR = 3
    const ERR_JSON_SYNTAX           = 234; // original JSON_ERROR_SYNTAX = 4
    const ERR_JSON_UTF8             = 235; // original JSON_ERROR_UTF8 = 5
    const ERR_JSON_INVALID          = 236; 
    const ERR_INVALID_FORMAT        = 237; 
    const ERR_INVALID_STRING        = 238; 
    protected $authentificationAdapter;
    protected $databaseAdapter;
    protected $fileAdapter;
    protected $loggerAdapter;
    protected $translationAdapter;
    protected $queue;
    protected $uploadAdapterContainer = array();
    protected $cron_context         = FALSE;
    public $application_name;
    public $client_key;
    public $client_url;
    public $transcoder_key;
    public $transcoder_url;
    public $css_url_path;
    public $js_url_path;
    public $view_path;
    public $version                 = "0.1";
    public $no_json                 = FALSE;

    public function __construct( $options= array() ){
        $this->configure($options);
    }
    
    public function configure( $options = array() ){
        
        if (array_key_exists("authentificationAdapter", $options) && NULL != $options["authentificationAdapter"]) {
            $this->setAuthentificationAdapter( $options['authentificationAdapter'] );
        } 
     
        if (array_key_exists("databaseAdapter", $options) && NULL != $options["databaseAdapter"]) {
            $this->setDatabaseAdapter( $options['databaseAdapter'] );
        } 
        
        if (array_key_exists("fileAdapter", $options) && NULL != $options["fileAdapter"]) {
            $this->setFileAdapter( $options['fileAdapter'] );
        }
        
        if (array_key_exists("uploadAdapter", $options) && NULL != $options["uploadAdapter"]) {
            if( !is_array($options["uploadAdapter"])){
                $options["uploadAdapter"] = array($options["uploadAdapter"]);
            }
            foreach ($options["uploadAdapter"] as $uploadAdapter) {
                $this->setUploadAdapter($uploadAdapter);
            }
        } 
        
        if (array_key_exists("loggerAdapter", $options) && NULL != $options["loggerAdapter"]) {
            $this->setLoggerAdapter( $options['loggerAdapter'] );
        }
        
        if (array_key_exists("translationAdapter", $options) && NULL != $options["translationAdapter"]) {
            $this->setTranslationAdapter( $options['translationAdapter'] );
        }
        
        if (array_key_exists("client_key", $options) && NULL != $options["client_key"]) {
            $this->client_key = $options['client_key'];
        } 
        
        if (array_key_exists("client_url", $options) && NULL != $options["client_url"]) {
            $this->client_url = $options['client_url'];
        } 
        
        if (array_key_exists("transcoder_key", $options) && NULL != $options["transcoder_key"]) {
            $this->transcoder_key = $options['transcoder_key'];
        } 
        
        if (array_key_exists("application_name", $options) && NULL != $options["application_name"]) {
            $this->application_name = $options["application_name"];
        } 
        
        if (array_key_exists("transcoder_url", $options) && NULL != $options["transcoder_url"]) {
            $this->transcoder_url = $options['transcoder_url'];
        } 
        
        if (array_key_exists("css_url_path", $options) && NULL != $options["css_url_path"]) {
            $this->css_url_path = $options['css_url_path'];
        } 
        
        if (array_key_exists("js_url_path", $options) && NULL != $options["js_url_path"]) {
            $this->js_url_path = $options['js_url_path'];
        }
        
        if (array_key_exists("view_path", $options) && NULL != $options["view_path"]) {
            $this->view_path = $options['view_path'];
        }else{
            $this->view_path = ".";
        }
        if (array_key_exists("no_json", $options) && NULL != $options["no_json"]) {
            $this->no_json = $options["no_json"];
        } 
        if (array_key_exists("mime_type_whitelist", $options) && NULL != $options["mime_type_whitelist"]) {
            $this->mime_type_whitelist = $options["mime_type_whitelist"];
        } 
    }
    
    /**
     * Returns the list of MIME types allowed by the client
     * 
     * @return array
     * @throws OMK_Exception
     */
    function getMimeTypeWhitelist(){

        if( NULL == $this->mime_type_whitelist ){
            throw new OMK_Exception(_("Missing mime type whitelist."));
        }
        return $this->mime_type_whitelist;
        
    }
    
    /**
     * Sets the list of MIME types allowed by the client
     * 
     * @param array $mime_type_whitelist
     * @throws OMK_Exception
     */
    function setMimeTypeWhitelist( $mime_type_whitelist ){

        if( !is_array( $mime_type_whitelist ) ){
            throw new OMK_Exception(_("Missing mime type whitelist."));
        }
        $this->mime_type_whitelist= $mime_type_whitelist;
        
    }
    
    /**
     * Retrieves the Client "Server" URL for requests emitted by a Transcoder
     * 
     * @return string
     * @throws OMK_Exception
     */
    public function getAppUrl(){
        if( NULL == $this->client_url ){
            throw new OMK_Exception(_("Missing api local url."));
        }
        return $this->client_url;
    }

    /**
     * Returns the Client "Server" URL for requests emitted by a Transcoder
     * 
     * @param string $url
     * @return \OMK_Client
     * @throws OMK_Exception
     */
    public function setAppUrl( $url ){
        if( NULL == $url ){
            throw new OMK_Exception(_("Missing api local url."));
        }
        $this->client_url = $url;
        return $this;
    }
        
    /**
     * Returns the app private key exchanged with the transcoder
     * 
     * @return string app key
     * @throws OMK_Exception
     */
    public function getAppKey(){
        if( NULL == $this->client_key ){
            throw new OMK_Exception(_("Missing api local key."));
        }
        return $this->client_key;
    }
    
    /**
     * Returns the API version 
     * 
     * @return string version
     * @throws OMK_Exception
     */
    public function getVersion(){
        
        if ( NULL === $this->version) {
            throw new OMK_Exception(_("Missing version."));
        }
        return $this->version;
    }
    
    /**
     * Returns the attached transcoder key
     * 
     * @return string
     * @throws OMK_Exception
     */
    public function getTranscoderKey(){
        
        if ( NULL === $this->transcoder_key) {
            throw new OMK_Exception(_("Missing api transcoder key."));
        }
        return $this->transcoder_key;
    }

    /**
     * Returns the attached transcoder URL
     * 
     * @return string
     * @throws OMK_Exception
     */
    public function getTranscoderUrl(){
        
        if ( NULL === $this->transcoder_url) {
            throw new OMK_Exception(_("Missing api transcoder url."));
        }
        return $this->transcoder_url;
    }

    /**
     * Returns the client's name 
     * 
     * @return string
     * @throws OMK_Exception
     */
    public function getApplicationName(){
        if ( NULL === $this->application_name) {
            throw new OMK_Exception(_("Missing appplication name."));
        }
        return $this->application_name;
    }

    /**
     * Sets the authentification adapter
     * 
     * @param OMK_Authentification_Adapter $adapter
     * @return \OMK_Client
     */
    public function setAuthentificationAdapter( OMK_Authentification_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->authentificationAdapter = $adapter;
        return $this;

    }
    /**
     * Returns the authentification adapter
     * 
     * @return OMK_Authentification_Adapter
     * @throws OMK_Exception
     */
    public function getAuthentificationAdapter(){
        
        if( NULL == $this->authentificationAdapter){
            throw new OMK_Exception(_("No authentification adapter defined."));
        }
        return $this->authentificationAdapter;
        
    }

    /**
     * Sets the database adapter
     * 
     * @param OMK_Database_Adapter $adapter
     * @return \OMK_Client
     */
    public function setDatabaseAdapter(OMK_Database_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->databaseAdapter = $adapter;
        return $this;
        
    }
    
    /**
     * Gets the database adapter
     * 
     * @return Omk_Database_Adapter
     * @throws OMK_Exception
     */
    public function getDatabaseAdapter(){
        
        if( NULL == $this->databaseAdapter){
            throw new OMK_Exception(_("No database adapter defined."));
        }
        return $this->databaseAdapter;
        
    }

    /**
     * Sets the file adapter
     * 
     * @param OMK_File_Adapter $adapter
     * @return \OMK_Client
     */
    public function setFileAdapter(OMK_File_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->fileAdapter = $adapter;
        return $this;
        
    }
    
    /**
     * Gets the file adapter
     * 
     * @return OMK_File_Adapter
     * @throws OMK_Exception
     */
    public function getFileAdapter(){
        
        if( NULL == $this->fileAdapter){
            throw new OMK_Exception(_("No file adapter defined."));
        }
        return $this->fileAdapter;
        
    }
    
    /**
     * Sets an upload adapter in an container
     * 
     * @param OMK_Upload_Adapter $adapter
     * @return \OMK_Client
     */
    public function setUploadAdapter(OMK_Upload_Adapter $adapter = null) {
        $adapter->setClient($this);
        $name = $adapter->getName();
        $this->uploadAdapterContainer[$name] = $adapter;
        return $this;
    }
    
    /**
     * Gets an upload adapter or the default one
     * 
     * @param type $options
     * @return OMK_Upload_Adapter
     * @throws OMK_Exception
     */
    public function getUploadAdapter( $options = array() ){
        
        if( !count($this->uploadAdapterContainer)){
            throw new OMK_Exception(_("No uploader defined."));
        }
        // Attempts to load a specific adapter
        if(array_key_exists("upload_adapter", $options) && NULL != $options["upload_adapter"]){
            $upload_adapter = $options["upload_adapter"];
            if(array_key_exists( $upload_adapter, $this->uploadAdapterContainer)){
                $uploadAdapter = $this->uploadAdapterContainer[$upload_adapter];
                return $uploadAdapter;
            } else {
                throw new OMK_Exception(_("Invalid upload adapter requested"),1);
            }
        }
        // Returns the first defined adapter, making it de facto the default one 
        reset($this->uploadAdapterContainer);
        return current($this->uploadAdapterContainer);

    }
    
    /**
     * Sets the translation adapter
     * 
     * @param OMK_Translation_Adapter $adapter
     * @return \OMK_Client
     */
    public function setTranslationAdapter(OMK_Translation_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->translationAdapter = $adapter;
        return $this;
        
    }
    
    /**
     * Gets the translation adapter
     * 
     * @return OMK_Translation_Adapter
     * @throws OMK_Exception
     */
    public function getTranslationAdapter(){
        
        if( NULL == $this->translationAdapter ){
            throw new OMK_Exception(_("Missing translation object."));
        }
        return $this->translationAdapter;
        
    }

    /**
     * Sets the logger adapter
     * 
     * @param OMK_Logger_Adapter $adapter
     * @return \OMK_Client
     */
    public function setLoggerAdapter(OMK_Logger_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->loggerAdapter = $adapter;
        return $this;
        
    }
    
    /**
     * Gets the logger adapter
     * 
     * @param type $options
     * @return OMK_Logger_Adapter
     * @throws OMK_Exception
     */
    public function getLoggerAdapter( ){
        
        if( NULL == $this->loggerAdapter ){
            throw new OMK_Exception(_("Missing logger object."));
        }
        return $this->loggerAdapter;
        
    }

    /**
     * Gets the Queue branched to other adapters
     * 
     * @return OMK_Queue
     */
    public function getQueue(){
        if( NULL == $this->queue ){
            $this->queue    = new OMK_Queue();
            $this->queue->setClient( $this);
        }
        return $this->queue;
    }
    
    /**
     * Core gateway for all client calls, either responses or requests
     * 
     * This method acts as dispatcher depending on the action called,
     * forwards incoming requests to an OMK_Client_Response instance and 
     * outcoming requests to an OMK_Client_Request
     *
     * @param array $options
     *   An associative array containing:
     *   - format: the format for response, json default.
     *   - action: the client action requested.
     * @return mixed depending on format
     * @throws OMK_Exception
     */
    public function call($options){
        
        if (array_key_exists("format", $options) && NULL != $options["format"]) {
            $format = $options["format"];
        } else {
            $format = FALSE;
        }
        
        try{
            switch($options["action"]){
                case "app_get_media":
                case "app_new_media":
                case "app_request_format":
                case "app_subscribe":
                case "app_test_request":
                case "tracker_autodiscovery":
                    $response = $this->request($options);
                break;
                case "transcoder_cron":
                    $this->cron_context = TRUE;
                case "transcoder_send_format":
                case "transcoder_send_metadata":
                case "transcoder_get_settings":
                case "app_test_response":
                case "upload":
                    $response =$this->response($options);
                break;
            default :
                throw new OMK_Exception(sprintf(_("Unknown action requested: %s"),$options["action"]),self::ERR_UNKNOWN_ACTION);
                break;
            }
            if( "json" == $format){
                return $this->jsonEncode($response);
            }
            return $response;
            
        }catch( OMK_Exception $e ){
            // Log
            $this->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => "OMK exception raised.",
                "exception" => $e
            ));
            // And leave it to the developper if he requested
            if( $this->throwExceptions() || $this->cron_context ){
                throw $e;
            }
            $code           = $e->getCode();
            if( null == $code ){
                $code       = self::ERR_EXCEPTION;
            }
            
            // TODO : When called by cron this shouldn't be a string
            return $this->jsonEncode( array(
                "code"      => $code,
                "message"   => $e->getMessage()
            ));
        }
        
    }
    /**
     * Wrapper for all json encode in the client
     * 
     * @param array $options
     * @return string json encoded
     */
    public function jsonEncode( array $options ){
        
        // Encapsulate json response in an object
        // @see http://incompleteness.me/blog/2007/03/05/json-is-not-as-safe-as-people-think-it-is/
        // While a bit old, let's consider this a good practice and build upon it
        $object = new stdClass();
        $object->result = $options;
        
        // Allows to skip json encoding
        if( $this->skipJson() ){
            return $object;
        }
        
        // Converts object to JSON
        return json_encode($object);
    }

    /**
     * Gateway for all json decode
     * 
     * @param string $string
     * @return array
     */
    public function jsonDecode(  $string ){
        
        if( !is_string($string)){
            throw new OMK_Exception(__CLASS__."::".__METHOD__." : jsonDecode expects a valid string.",self::ERR_INVALID_STRING);
        }
        
        // Attempts to convert to JSON
         $decodedArray          = json_decode($string, TRUE);
         
         // Exits if failed
         if( $json_last_error = json_last_error()){
             $msg               = _("JSON conversion failed.");
             $this->getLoggerAdapter()->log(array(
                 "level"        => OMK_Logger_Adapter::WARN,
                 "message"      => $msg,
                 "data"         => $string
             ));
             return array(
                 "code"         => self::ERR_JSON_NONE + $json_last_error, 
                 "message"      => $msg
             );
         }
         return array(
             "code"         => 0,
             "message"      => _("Successfully decoded json string."),
             "result"       => $decodedArray
         );
    }
    
    /**
     * Validates if client should do JSON conversion or not
     * 
     * @return boolean
     */
    protected function skipJson(){
        if( $this->no_json){
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Validates if client should throw catched exceptions or not
     * 
     * @return boolean
     */
    protected function throwExceptions(){
        // TODO : decide how to parameter that
        return FALSE;
    }

    /**
     * Renders a phtml view 
     * 
     * @param string $view
     * @return type
     */
    public function render( $view ){
        
        // Defines actions requiring admin rights
        $adminViews = array(
            "settings.index",
            "settings.update",
            "admin.list"
        );
        // Defines ACL group to be checked
        if( in_array($view,$adminViews)){
            $group = OMK_Authentification_Adapter::GROUP_ADMIN;
        }else{
            $group = OMK_Authentification_Adapter::GROUP_USER;
        }
        
        // Resets view if authentification denied
        if( ! $this->getAuthentificationAdapter()->check($group)){
            $view = "error";
        }
        
        // Archaic controller to do stuff before rendering
        switch ($view) {
            // displays a video player for the given media id
            case "player.video":
                $media_id = $_REQUEST["media_id"];
                $player = new OMK_Player();
                $player->setClient($this);
                $result = $player->getVideoData(array(
                    "media_id" => $media_id    
                ));
                // Returns error if failed
                if( !array_key_exists("code", $result) || OMK_Client_Friend::ERR_OK != $result["code"]){
                    $view = "error.phtml";
                    break;
                }
                // Sets the videoData array
                $videoData = $result["videoData"];
                $view = "player.phtml";
                break;
            // upload files with PLUpload dependancies
            case "upload":
                $view = "upload.phtml";
                break;
            // lists all files ! caution, no break on this case, cascades to next.
            case "admin.list":
                $query = array("table"=>"files");
            // lists user files
            case "list":
                $view = "list.phtml";
                // If not inherited from the previous admin case
                if(!isset($query)){
                    $user_id = $this->getAuthentificationAdapter()->getUserId();
                    $query = array(
                        "table"     => "files",
                        "where"     => array(
                            "owner_id = ?" => $user_id
                        ),
                        "order"     => "id DESC"
                    );
                }
                // Runs the query
                try{
                    $result         = $this->getDatabaseAdapter()->select($query);
                    $filesList      = $result["rows"];
                    
                    // TODO: select a specific transcoder ?
                    $result         = $this->getDatabaseAdapter()->select(array(
                        "table" => "settings"
                    ));
                    $settingsList   = $result["rows"];
                    
                    
                }catch(OMK_Exception $e){
                    $this->getLoggerAdapter()->log(array(
                       "level"      => OMK_Logger_Adapter::WARN,
                        "message"   => "Failed to retrieve videos",
                        "exception" => $e
                    ));
                    if($this->throwExceptions()){
                        throw $e;
                    }
                }
                break;
            // lists settings
            case "settings.index":
                $view = "settings.index.phtml";
                break;
            // updates setting
            case "settings.update":
                $settingsInstance = new OMK_Settings();
                $settingsInstance->setClient($this);
                $settingsInstance->recordResult( $settingsInstance->update());
                if( $settingsInstance->successResult()){
                    $view = "settings.index.phtml";
                    break;
                }
                $error = TRUE;
                $view = "error.phtml";
                break;
            // error cases
            case "error":
            default:
                $view = "error.phtml";
                break;
        }
        
        // archaic view renderer
        ob_start();
        $filepath = $this->view_path."/{$view}";
        include( $filepath );
        $filecontent = ob_get_contents();
        ob_clean();
        
        // returns view content
        return $filecontent;
    }

    /**
     * Calls instance that requests servers, stores the results and converts them to json
     * 
     * @param type $options
     * @return string json
     */
    public function request($options = null) {

        $request = new OMK_Client_Request($this);
        $request->recordResult($request->run($options));
        return $request->getResult();
    }
    
    /**
     * Calls instance that responds to requests, stores the results and converts them to json
     * 
     * @param type $options
     * @return string json
     */
    public function response($options = null) {

        $response = new OMK_Client_Response($this);
        $response->run($options);
        return $response->getResult();
        
    }
    
    /**
     * Validates if the file upload is finished or not 
     * 
     * @param array $options
     *   An associative array containing:
     *   - upload_adapter: the name of an adapter (optional).
     * @return boolean
     */
    public function isUploadComplete( $options = array() ){
        $uploadAdapter = $this->getUploadAdapter($options);
        return $uploadAdapter->isUploadComplete();
    }
 
    /**
     * Returns the new files Id 
     * 
     * @return int
     */
    public function getLastInsertId(){
        return $this->getDatabaseAdapter()->getLastInsertId();
    }
}
