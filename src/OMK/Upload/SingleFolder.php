<?php 
class OMK_Upload_SingleFolder extends OMK_Upload_Adapter {
    
    protected $protocol   = "http"; 
    protected $name   = "singleFolder"; // mandatory : sets a key name for this adapter
    protected $chunk_size       = 8192000;

    public function __construct($options = null) {
        parent::__construct($options);
        if( array_key_exists("chunk_size",$options) && NULL != $options["chunk_size"]){
            $this->chunk_size = $options["chunk_size"];
        } 
    }
    function upload( $options = NULL){
       
        $file               = current($_FILES);
        $chunks             = (int) $_POST["chunks"];
        $chunk              = (int) $_POST["chunk"];
        $file_name = (string) basename($_POST["name"]);
        if (array_key_exists("offset", $_REQUEST) && NULL != $_REQUEST["offset"]) {
            $offset         = (int) $_REQUEST["offset"];
        } else { 
            $offset         = 0;
        }
        if (array_key_exists("total", $_REQUEST) && NULL != $_REQUEST["total"]) {
            $total          = (int) $_REQUEST["total"];
        } else { 
            $total          = 0;
        }
        

        // Settings
        $targetDir          = $this->tmp_path;
        $cleanupTargetDir   = true; // Remove old files
        $maxFileAge         = 5 * 3600; // Temp file age in seconds

        // Create target dir
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $file_path          = $targetDir . DIRECTORY_SEPARATOR . $file_name;
        $chunking           = $chunks > 0;

        // Open temp file
        if (!$out = fopen("{$file_path}.part", "a")) {
            return array(
                "code" => OMK_Upload_Adapter::ERR_OUTPUT_STREAM, 
                "message" => _("Failed to open output stream.")
            );
        }

        if (!empty($_FILES)) {
            if ($_FILES['file']['error'] || !is_uploaded_file($_FILES['file']['tmp_name'])) {
             return array("code" => OMK_Upload_Adapter::ERR_MOVE_UPLOADED, "message" => _("Failed to move uploaded file."));
            }
            // Read binary input stream and append it to temp file
            if (!$in = fopen($_FILES['file']['tmp_name'], "rb")) {
                return array("code" => OMK_Upload_Adapter::ERR_INPUT_STREAM, "message" => _("Failed to open input stream."));
            }
        } else {	
            return array("code" => OMK_Upload_Adapter::ERR_MISSING_FILE, "message" => _("Failed to open output stream."));
        }

//        if ($chunking) {
//            fseek($out, $offset); // write at a specific offset
//        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        fclose($out);
        fclose($in);

        // Check if file has been uploaded
        if (!$chunking || ($chunk + 1) == $chunks ) {
            // Strip the temp .part suffix off 
            // TODO Check MIME of object
            rename("{$file_path}.part", $file_path);
            $this->upload_complete = true;
            return array(
                "code"              => 0,
                "message"           => _("File {$file_name} correctly uploaded"),
                "file_path"         => $file_path,
                "file_name"         => $file_name,
                "upload_adapter"    => $this->name
            );
        }

        return array("code" => 1, "message"=>_("Chunking part received"));
        
    }
    
    /**
     * {@DocInherit}
     */
    public function getFileContentRange($options = NULL) {
        
        // Attempts to retrieve file path
        if( array_key_exists("file_path",$options) && NULL != $options["file_path"]){
            $file_path = $options["file_path"];
        } else {
            throw new OMK_Exception(_("Missing file_path."), self::ERR_MISSING_PARAMETER);
        }
        
        // Attempts to retrieve full size
        if( array_key_exists("full_size",$options) && NULL != $options["full_size"]){
            $full_size = $options["full_size"];
        } else {
            throw new OMK_Exception(_("Missing full_size."), self::ERR_MISSING_PARAMETER);
        }
        
        clearstatcache();
        
        // Attempts to retrieve current size of file
        $file_size = filesize($file_path);
        
        // Returned data for request range
        $parts          = array(
            1           => $file_size,  // Start of request range
            2           => ""           // End of request range
        );
        $diff           = $full_size - $file_size;
        if( $diff < $this->chunk_size){
            $parts[2] = "";
            $finished = TRUE;
        }else{
            $parts[2] = $file_size + $this->chunk_size;
            $finished = FALSE;
        }
        
        return array(
            "code"          => OMK_Client_Friend::ERR_OK,
            "message"       => sprintf(_("Content range calculated.")),
            "content_range" => implode("-", $parts),
            "finished"      => $finished
        );
        
    }
} 
