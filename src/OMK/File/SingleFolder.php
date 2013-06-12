<?php 
class OMK_File_SingleFolder extends OMK_File_Adapter {
    
    protected $storage_path;
    protected $file_path;
    protected $chunk_size = 8192;

    public function __construct($options) {
        if(array_key_exists("storage_path", $options) && NULL != $options["storage_path"]){
            $this->storage_path = $options["storage_path"];
        }else{
            throw new OMK_Exception(_("Missing storage path."), self::ERR_STORAGE_PATH);
        }
        if (array_key_exists("file_path", $options) && NULL != $options["file_path"]) {
            $this->file_path = $options["file_path"];
        } else {
            throw new OMK_Exception(_("Missing file_path."));
        }
    }
    
    public function append($options = NULL) {
        
        if( array_key_exists("file_path",$options) && NULL != $options["file_path"]){
            $file_path = $options["file_path"];
        } else {
            throw new omk_(_("Missing file_path."), self::ERR_MISSING_PARAMETER);
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data = $options["data"];
        } else {
            throw new OMK_Exception(_("Missing data."), self::ERR_MISSING_PARAMETER);
        }
        
        if( ! file_put_contents($file_path, $data, FILE_APPEND)){
            return array(
                "code" => self::ERR_STORAGE_APPEND,
                "message" => sprintf(_("Failed to append %s  octets appended to file %s "), strlen($data),$file_path)
            );
        }
        
        return array(
            "code"      => 0,
            "message" => sprintf(_("Successfully appended %s octets to file %s "), strlen($data),$file_path)
        );
    }

    public function create($options){
        
        if (array_key_exists("file_path", $options) && NULL != $options["file_path"]) {
            $file_path = $options["file_path"];
        } else {
            throw new OMK_Exception(_("Missing base url."), self::ERR_STORAGE_FILE_PATH);    
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
            $id = $options["id"];
        } else {
            throw new OMK_Exception(_("Missing id."));
        }
        
        $final_path = $this->file_path;

        if (array_key_exists("file_path", $options) && NULL != $options["file_path"]) {
            $path = str_replace($this->storage_path, $final_path, $options["file_path"]);
        } else {
            throw new OMK_Exception(_("Missing file_path."), self::ERR_STORAGE_FILE_PATH);
        }
        
        return $path;
        
    }
    

} 
