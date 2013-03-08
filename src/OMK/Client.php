<?php 

require_once "Exception.php";
require_once "Request.php";
require_once "Response.php";

class OMK_APIClient{
    
    protected $dbAdapter;
    protected $fileAdapter;
    protected $authentificationAdapter;
    protected $uploadAdapter;

    public function __construct( $options = null ){
        $this->configure($options);
    }
    
    public function configure( $options = null ){
        
        if( !count($options)){
            throw new OMK_Exception("Missing configuration options");
        }
        
        if (array_key_exists("authentificationAdapter", $options) && null != $options["authentificationAdapter"]) {
            $this->setAuthentificationAdapter( $options['authentificationAdapter'] );
        } 
     
        if (array_key_exists("dbAdapter", $options) && null != $options["dbAdapter"]) {
            $this->setDbAdapter( $options['dbAdapter'] );
        } 
        
        if (array_key_exists("fileAdapter", $options) && null != $options["fileAdapter"]) {
            $this->setFileAdapter( $options['fileAdapter'] );
        }
        
        if (array_key_exists("uploadAdapter", $options) && null != $options["uploadAdapter"]) {
            $this->setUploadAdapter($options['uploadAdapter']);
        } 
        
    }
    
    public function setAuthentificationAdapter( OMK_AuthentificationAdapter $adapter = null) {
        
        $this->authentificationAdapter      = $adapter;

    }
    
    public function setDbAdapter( OMK_DbAdapter $adapter = null) {

        $this->dbAdapter                    = $adapter;
        
    }
    
    public function setFileAdapter( OMK_FileAdapter $adapter = null) {

        $this->fileAdapter                  = $adapter;
        
    }
    
    public function setUploadAdapter( OMK_UploadAdapter $adapter = null) {

        $this->uploadAdapter                = $adapter;
        
    }
    
    public function request($options = null) {

        $request = new OMK_Request($this);
        $request->run($options);
        
    }
    
    public function response($options = null) {

        $response = new OMK_Response($this);
        $response->run($options);
        
    }
    
}
