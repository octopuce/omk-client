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
class OMK_Client{
    
    // ERR Codes 225-249
    const ERR_EXCEPTION = 225;
    
    protected $authentificationAdapter;
    protected $databaseAdapter;
    protected $fileAdapter;
    protected $loggerAdapter;
    protected $translationAdapter;
    protected $queue;
    protected $uploadAdapterContainer = array();
    public $application_name;
    public $api_local_key;
    public $api_local_url;
    public $api_transcoder_key;
    public $api_transcoder_url;
    public $css_url_path;
    public $js_url_path;
    public $view_path;
    public $version         = "0.1";

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
        
        if (array_key_exists("api_local_key", $options) && NULL != $options["api_local_key"]) {
            $this->api_local_key = $options['api_local_key'];
        } 
        
        if (array_key_exists("api_local_url", $options) && NULL != $options["api_local_url"]) {
            $this->api_local_url = $options['api_local_url'];
        } 
        
        if (array_key_exists("api_transcoder_key", $options) && NULL != $options["api_transcoder_key"]) {
            $this->api_transcoder_key = $options['api_transcoder_key'];
        } 
        
        if (array_key_exists("application_name", $options) && NULL != $options["application_name"]) {
            $this->application_name = $options["application_name"];
        } 
        
        if (array_key_exists("api_transcoder_url", $options) && NULL != $options["api_transcoder_url"]) {
            $this->api_transcoder_url = $options['api_transcoder_url'];
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
    }
    public function getAppUrl(){
        if( NULL == $this->api_local_url ){
            throw new OMK_Exception(_("Missing api local url."));
        }
        return $this->api_local_url;
    }
    
    public function getVersion(){
        
        if ( NULL === $this->version) {
            throw new OMK_Exception(_("Missing version."));
        }
        return $this->version;
    }
    
    public function getTranscoderKey(){
        
        if ( NULL === $this->api_transcoder_key) {
            throw new OMK_Exception(_("Missing api transcoder key."));
        }
        return $this->api_transcoder_key;
    }

    public function getApplicationName(){
        if ( NULL === $this->application_name) {
            throw new OMK_Exception(_("Missing appplication name."));
        }
        return $this->application_name;
    }

        public function setAuthentificationAdapter( OMK_Authentification_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->authentificationAdapter = $adapter;
        return $this;

    }
    
    public function getAuthentificationAdapter(){
        
        if( NULL == $this->authentificationAdapter){
            throw new OMK_Exception(_("No authentification adapter defined."));
        }
        return $this->authentificationAdapter;
        
    }


    public function setDatabaseAdapter(OMK_Database_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->databaseAdapter = $adapter;
        return $this;
        
    }
    
    public function getDatabaseAdapter(){
        
        if( NULL == $this->databaseAdapter){
            throw new OMK_Exception(_("No database adapter defined."));
        }
        return $this->databaseAdapter;
        
    }

    public function setFileAdapter(OMK_File_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->fileAdapter = $adapter;
        return $this;
        
    }
    
    public function getFileAdapter(){
        
        if( NULL == $this->fileAdapter){
            throw new OMK_Exception(_("No file adapter defined."));
        }
        return $this->fileAdapter;
        
    }

    public function setUploadAdapter(OMK_Upload_Adapter $adapter = null) {
        $adapter->setClient($this);
        $name = $adapter->getName();
        $this->uploadAdapterContainer[$name] = $adapter;
        return $this;
    }
    
    public function getUploadAdapter( $options = NULL ){
        
        if( !count($this->uploadAdapterContainer)){
            throw new OMK_Exception(_("No uploader defined."));
        }
        // Attempts to load a specific adapter
        if(array_key_exists("upload_adapter", $options) && NULL != $options["upload_adapter"]){
            $upload_adapter = $options["upload_adapter"];
            if(array_key_exists( $upload_adapter, $this->uploadAdapterContainer)){
                $uploadAdapter = $this->uploadAdapterContainer[$upload_adapter];
                $uploadAdapter->upload( $options );
            } else {
                throw new OMK_Exception(_("Invalid upload adapter requested"),1);
            }
        }
        // Returns the first defined adapter, making it de facto the default one 
        reset($this->uploadAdapterContainer);
        return current($this->uploadAdapterContainer);

        }
    
    public function setTranslationAdapter(OMK_Translation_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->translationAdapter = $adapter;
        return $this;
        
    }
    
    public function getTranslationAdapter( $options = NULL ){
        
        if( NULL == $this->translationAdapter ){
            throw new OMK_Exception(_("Missing translation object."));
        }
        return $this->translationAdapter;
        
    }

    
    public function setLoggerAdapter(OMK_Logger_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->loggerAdapter = $adapter;
        return $this;
        
    }
    
    public function getLoggerAdapter( $options = NULL ){
        
        if( NULL == $this->loggerAdapter ){
            throw new OMK_Exception(_("Missing logger object."));
        }
        return $this->loggerAdapter;
        
    }

    public function getQueue(){
        if( NULL == $this->queue ){
            $this->queue    = new OMK_Queue();
            $this->queue->setClient( $this);
        }
        return $this->queue;
    }

    public function call($options){
        
        try{
            switch($options["action"]){
                case "app_test_request":
                case "app_subscribe":
                case "tracker_autodiscovery":
                case "app_new_media":
                case "app_request_format":
                    return $this->request($options);
                break;
                case "transcoder_cron":
                case "transcoder_send_format":
                case "transcoder_send_metadata":
                case "transcoder_get_settings":
                case "app_test_response":
                case "upload":
                    return $this->response($options);
                break;
            }
        }catch( OMK_Exception $e ){
            $this->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => "OMK exception raised.",
                "exception" => $e
            ));
            // And leave it to the developper if he requested
            if( $this->throwExceptions()){
                throw $e;
            }
            $code           = $e->getCode();
            if( null == $code ){
                $code       = self::ERR_EXCEPTION;
            }
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
        return json_encode($object);
    }


    protected function throwExceptions(){
        // TODO : decide how to parameter that
        return FALSE;
    }

    public function render( $view ){
        
        if( ! $this->authentificationAdapter->check()){
            $view = "error";
        }
        switch ($view) {
            case "upload":
                $view = "upload.phtml";
                break;
            case "error":
            default:
                $view = "error.phtml";
                break;
        }
        ob_start();
        $filepath = $this->view_path."/{$view}";
        include( $filepath );
        $filecontent = ob_get_contents();
        ob_clean();
        return $filecontent;
    }

    public function request($options = null) {

        $request = new OMK_Client_Request($this);
        $request->run($options);
        return $request->getResult(array("format"=>"json"));
    }
    
    public function response($options = null) {

        $response = new OMK_Client_Response($this);
        $response->run($options);
        return $response->getResult(array("format"=>"json"));
        
    }
    
}
