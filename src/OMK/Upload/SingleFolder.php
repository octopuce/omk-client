<?php 
class OMK_Upload_SingleFolder extends OMK_Upload_Adapter {
    
    var $label              = "singleFolder";

    function upload( $options ){
       
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


        // Remove old temp files	
//        if ($cleanupTargetDir) {
//                if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
//                        die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
//                }
//
//                while (($file = readdir($dir)) !== false) {
//                        $tmpfile_path = $targetDir . DIRECTORY_SEPARATOR . $file;
//
//                        // If temp file is current file proceed to the next
//                        if ($tmpfile_path == "{$file_path}.part") {
//                                continue;
//                        }
//
//                        // Remove temp file if it is older than the max age and is not the current file
//                        if (preg_match('/\.part$/', $file) && (filemtime($tmpfile_path) < time() - $maxFileAge)) {
//                                @unlink($tmpfile_path);
//                        }
//                }
//                closedir($dir);
//        }	


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
} 
