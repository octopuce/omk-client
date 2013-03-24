<?php 
class OMK_File_SingleFolder extends OMK_File_Adapter {
    
    protected $storage_path;
    protected $http_path;

    public function __construct($options) {
        if(array_key_exists("storage_path", $options) && NULL != $options["storage_path"]){
            $this->storage_path = $options["storage_path"];
        }else{
            throw new OMK_Exception(_("Missing storage path."), self::ERR_STORAGE_PATH);
        }
        if (array_key_exists("http_path", $options) && NULL != $options["http_path"]) {
            $this->http_path = $options["http_path"];
        } else {
            throw new Exception(_("Missing http path."));
        }
    }

    public function create($options){
        
        if (array_key_exists("file_path", $options) && NULL != $options["file_path"]) {
            $file_path = $options["file_path"];
        } else {
            throw new OMK_Exception(_("Missing file path."), self::ERR_STORAGE_FILE_PATH);    
        }

        if (array_key_exists("file_id", $options) && NULL != $options["file_id"]) {
            $file_id = $options["file_id"];
        } else{
            throw new OMK_Exception(_("Missing file id."), self::ERR_STORAGE_FILE_ID);    
        }

        if (array_key_exists("file_name", $options) && NULL != $options["file_name"]) {
            $file_name = $options["file_name"];
        } else{
            throw new OMK_Exception(_("Missing file name."), self::ERR_STORAGE_FILE_NAME);    
        }
        // TODO: test MIME
        $new_path = "{$this->storage_path}/{$file_id}";
        if( !is_dir($new_path)){
            
            if( !mkdir($new_path, 0777, TRUE) ){
                return array(
                    "code"      => OMK_File_Adapter::ERR_STORAGE_CREATE,
                    "message"   => _("Failed to create storage path.")
                );
            }
        }
        if( !is_writable($new_path)){
            return array(
                "code"      => OMK_File_Adapter::ERR_STORAGE_AUTH,
                "message"   => _("Failed to access storage path.")
            );
        }
        $new_path .= "/".$file_name;
        if( !rename($file_path,$new_path)){
            return array(
                "code"      => OMK_File_Adapter::ERR_STORAGE_MOVE,
                "message"   => _("Failed to move file to storage.")
            );        
        }
        
        return array(
            "code"      => 0,
            "message"   => _("File {$file_name} moved to file system"),
            "file_path" => $new_path
        );
    }
    
    public function getDownloadUrl( $options = NULL ){
        
        if (array_key_exists("id", $options) && NULL != $options["id"]) {
            $this->id = $options["id"];
        } else {
            throw new Exception(_("Missing id."));
        }

        if (array_key_exists("file_path", $options) && NULL != $options["file_path"]) {
            $path = str_replace($this->storage_path, $this->http_path, $options["file_path"]);
        } else {
            throw new OMK_Exception(_("Missing file_path."), self::ERR_STORAGE_FILE_PATH);
        }
        
        return $path;
        
    }
} 
