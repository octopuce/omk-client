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
    
    /**
     * Appends data to file_path
     * 
     * @param array $options file_path, data
     * @return array code, message, file_size
     * @throws OMK_Exception
     */
    public function append($options = NULL) {
        
        if( array_key_exists("file_path",$options) && NULL != $options["file_path"]){
            $file_path = $options["file_path"];
        } else {
            throw new OMK_Exception(_("Missing file_path."), self::ERR_MISSING_PARAMETER);
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
        $file_size = $this->getFileSize(array("file_path"=>$file_path));
        return array(
            "code"      => 0,
            "message" => sprintf(_("Successfully appended %s octets to file %s "), strlen($data),$file_path),
            "file_size" => $file_size
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
    
    
    /**
     * {@inheritDoc}
     */
    public function getTranscodedFileData( array $options ){
        
        // Retrieves id
        if( array_key_exists("id",$options) && ! is_null( $options["id"] )){$id = $options["id"];} 
        // Failed at retrieving variable $id
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing id."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves file_name
        if( array_key_exists("file_name",$options) && ! is_null( $options["file_name"] )){$file_name = $options["file_name"];} 
        // Failed at retrieving variable $file_name
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing file_name."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves settings_id
        if( array_key_exists("settings_id",$options) && ! is_null( $options["settings_id"] )){$settings_id = $options["settings_id"];} 
        // Failed at retrieving variable $settings_id
        else {throw new OMK_Exception(__CLASS__."::".__METHOD__." = "._("Missing settings_id."), self::ERR_MISSING_PARAMETER);}      
        
        // Retrieves cardinality
        if( array_key_exists("cardinality",$options) && ! is_null( $options["cardinality"] )){$cardinality = $options["cardinality"];} 
        // Failed at retrieving variable $cardinality
        else {$cardinality = 1;}
        
        
        // Sets the response array
        $returnInfo = array(
            "code"          => OMK_Client_Friend::ERR_OK,
            "message"       => "",
            "file_path"     => "",
            "file_name"     => "",    # ex: some_media
            "extension"     => "",    # ex: mp4
            "serial"        => 0# ex: 12
        );
        
        // Retrieves the setting informations for extension
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(
            array(
                "table" => "settings",
                "where" => array(
                    "id = ?" => $settings_id
                )
            )
        ));
        
        // Exits if failed
        if( !$this->successResult()){ return array(
           "code"       => OMK_Settings_Manager::ERR_INVALID_SETTING,
            "message"   => _("This setting doesn't exist: {$settings_id}.")
        );}
        
        // Retrieves setting or dies
        if( array_key_exists("rows",$this->result) && ! is_null( $this->result["rows"] && count($this->result["rows"]))){
            $setting = current($this->result["rows"]);} 
        else { return array(
            "code"      => OMK_Settings_Manager::ERR_INVALID_SETTING,
            "message"   => _("This setting doesn't exist: {$settings_id}.")
        );}
        
        // Removes extension from file_name
        $pathParts                  = pathinfo($file_name);
        $returnInfo["extension"]      = $setting['extension'];
        
        // Builds file name
        $base                       = "{$pathParts['filename']}_{$settings_id}";
        
        // Handles serial / cardinality
        if( $cardinality > 1){
            $returnInfo["extension"] = "zip";
        }
        $returnInfo["serial"]       = 1;
        $returnInfo["file_name"]    = "{$base}.{$returnInfo["extension"]}";
        $returnInfo["file_path"]    = "{$this->storage_path}/{$id}/{$returnInfo["file_name"]}";
        return $returnInfo;
        
    }
    

} 
