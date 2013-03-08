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
    
    protected $authentificationAdapter;
    protected $databaseAdapter;
    protected $fileAdapter;
    protected $loggerAdapter;
    protected $translationAdapter;
    protected $uploadAdapterContainer = array();
    public $api_local_key;
    public $api_local_url;
    public $api_transcoder_key;
    public $api_transcoder_url;
    public $css_url_path;
    public $js_url_path;
    public $view_path;

    public function __construct( $options= array() ){
        $this->configure($options);
    }
    
    public function configure( $options = array() ){
        
        if (array_key_exists("authentificationAdapter", $options) && null != $options["authentificationAdapter"]) {
            $this->setAuthentificationAdapter( $options['authentificationAdapter'] );
        } 
     
        if (array_key_exists("databaseAdapter", $options) && null != $options["databaseAdapter"]) {
            $this->setDbAdapter( $options['databaseAdapter'] );
        } 
        
        if (array_key_exists("fileAdapter", $options) && null != $options["fileAdapter"]) {
            $this->setFileAdapter( $options['fileAdapter'] );
        }
        
        if (array_key_exists("uploadAdapter", $options) && null != $options["uploadAdapter"]) {
            if( !is_array($options["uploadAdapter"])){
                $options["uploadAdapter"] = array($options["uploadAdapter"]);
            }
            foreach ($options["uploadAdapter"] as $uploadAdapter) {
                $this->setUploadAdapter($uploadAdapter);
            }
        } 
        
        if (array_key_exists("loggerAdapter", $options) && null != $options["loggerAdapter"]) {
            $this->setLoggerAdapter( $options['loggerAdapter'] );
        }
        
        if (array_key_exists("translationAdapter", $options) && null != $options["translationAdapter"]) {
            $this->setTranslationAdapter( $options['translationAdapter'] );
        }
        
        if (array_key_exists("api_local_key", $options) && null != $options["api_local_key"]) {
            $this->api_local_key = $options['api_local_key'];
        } 
        
        if (array_key_exists("api_local_url", $options) && null != $options["api_local_url"]) {
            $this->api_local_url = $options['api_local_url'];
        } 
        
        if (array_key_exists("api_transcoder_key", $options) && null != $options["api_transcoder_key"]) {
            $this->api_transcoder_key = $options['api_transcoder_key'];
        } 
        
        if (array_key_exists("api_transcoder_url", $options) && null != $options["api_transcoder_url"]) {
            $this->api_transcoder_url = $options['api_transcoder_url'];
        } 
        
        if (array_key_exists("css_url_path", $options) && null != $options["css_url_path"]) {
            $this->css_url_path = $options['css_url_path'];
        } 
        
        if (array_key_exists("js_url_path", $options) && null != $options["js_url_path"]) {
            $this->js_url_path = $options['js_url_path'];
        }
        
        if (array_key_exists("view_path", $options) && null != $options["view_path"]) {
            $this->view_path = $options['view_path'];
        }else{
            $this->view_path = ".";
        }
    }

    public function setAuthentificationAdapter( OMK_Authentification_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->authentificationAdapter = $adapter;
        return $this;

    }
    
    public function setDbAdapter(OMK_Database_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->databaseAdapter = $adapter;
        return $this;
        
    }
    
    public function setFileAdapter(OMK_File_Adapter $adapter = null) {

        $adapter->setClient($this);
        $this->fileAdapter = $adapter;
        return $this;
        
    }
    
    public function setUploadAdapter(OMK_Upload_Adapter $adapter = null) {
        $adapter->setClient($this);
        $name = $adapter->getName();
        $this->uploadAdapterContainer[$name] = $adapter;
        return $this;
    }
    
    public function getUploadAdapter( $options = null ){
        
        if( !count($this->uploadAdapterContainer)){
            throw new OMK_Exception("No uploader defined.");
        }
        // Attempts to load a specific adapter
        if(array_key_exists("upload_adapter", $options) && null != $options["upload_adapter"]){
            $upload_adapter = $options["upload_adapter"];
            if(array_key_exists( $upload_adapter, $this->uploadAdapterContainer)){
                $uploadAdapter = $this->uploadAdapterContainer[$upload_adapter];
                $uploadAdapter->upload( $options );
            } else {
                throw new OMK_Exception("Invalid upload adapter requested",1);
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
    
    public function getTranslationAdapter( $options = null ){
        
        if( null == $this->translationAdapter ){
            throw new OMK_Exception("Missing translation object.");
        }
        return $this->translationAdapter;
        
    }

    
    public function setLoggerAdapter(OMK_Logger_Adapter $adapter = null) {
        
        $adapter->setClient($this);
        $this->loggerAdapter = $adapter;
        return $this;
        
    }
    
    public function getLoggerAdapter( $options = null ){
        
        if( null == $this->loggerAdapter ){
            throw new OMK_Exception("Missing logger object.");
        }
        return $this->loggerAdapter;
        
    }


    public function call($options){
        
        switch($options["action"]){
            case "tracker_autodiscovery":
            case "app_new_media":
            case "app_request_format":
                return $this->request($options);
            break;
            case "app_subscribe":
            case "transcoder_cron":
            case "transcoder_send_format":
            case "transcoder_send_metadata":
            case "transcoder_get_settings":
            case "app_test":
            case "upload":
                return $this->response($options);
            break;
        }
        
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
        return $request->getResult();
    }
    
    public function response($options = null) {

        $response = new OMK_Client_Response($this);
        $response->run($options);
        return $response->getResult();
        
    }
    
}
