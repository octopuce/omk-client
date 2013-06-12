<?php

/** Implements a simple response upload server for the omk client
  * To test this example, you must have a valid upload and final location setup
  * 
  * 
  */ 



// set up include path and autoload
$old_include_path = get_include_path(); 
set_include_path(dirname(__FILE__)."/../src".PATH_SEPARATOR.$old_include_path);
function __autoload($className) {
    
    $ds = DIRECTORY_SEPARATOR;
    $className = strtr($className, '_', $ds);
    $paths = explode(PATH_SEPARATOR, get_include_path());
    foreach($paths as $dir){
        $file = "{$dir}{$ds}{$className}.php";
        if (is_readable($file)){
            require_once $file;
            return;
        }
    }
    throw new Exception("Sorry, {$className} is nowhere to be found in ".get_include_path());
};

// Set up some fake session identifying us an admin
if( ! session_start() ){
   die("Oops, couldn't start a session, fix this fist."); 
}
$_SESSION["is_admin"] = TRUE;

// instanciate a dummy authentification adapter
$authentificationAdapter = new OMK_Authentification_Session(array(
    
));

$databaseAdapter = new OMK_Database_Mysql(array(
    "host"          => "localhost",
    "database"      => "omk",
    "user"          => "omk",
    "password"      => "omk",
    "prefix"        => ""
));

$fileAdapter = new OMK_File_SingleFolder(array(
    "storage_path"  => "/home/alban/code/omkstorage",
    "file_path"     => "http://omk53storage.octopuce.fr"
));

$uploadAdapter = new OMK_Upload_SingleFolder(array(
    "tmp_path"      => "/home/alban/code/omkstorage",
    "name"          => "singleFolder"
));

$loggerAdapter  = new OMK_Logger_File(array(
   "level"          => OMK_Logger_Adapter::DEBUG, 
   "log_file_path"  => "/tmp/omk.log" 
));

$translationAdapter = new OMK_Translation_Dummy(array(
    
));

$mimeTypeWhitelist = array(
    "audio/basic",
    "audio/L24",
    "audio/mp4",
    "audio/mpeg",
    "audio/ogg",
    "audio/vorbis",
    "audio/vnd.rn-realaudio",
    "audio/vnd.wave",
    "audio/webm",
    "image/gif",
    "image/jpeg",
    "image/pjpeg",
    "image/png",
    "image/svg+xml",
    "image/tiff",
    "image/vnd.microsoft.icon",
    "application/ogg",
    "text/plain",
    "video/mpeg",
    "video/mp4",
    "video/ogg",
    "video/quicktime",
    "video/webm",
    "video/x-matroska",
    "video/x-ms-wmv",
    "video/x-flv",
);


        
// set up the client
$client = new OMK_Client(array(
    "lang"                      => "fr",
    "application_name"          => "simple-server-example",
    "client_key"             => "1234567890abcdef",
    "client_url"             => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}{$_SERVER["SCRIPT_NAME"]}",
    "transcoder_key"        => "9ef121fe8bff86a8764bae831d68f804",
    "transcoder_url"        => "http://omkt.octopuce.fr/api",
    "config_file"               => __FILE__,
    "css_url_path"              => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}".dirname($_SERVER["SCRIPT_NAME"])."/../src/OMK/views/css",
    "js_url_path"               => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}".dirname($_SERVER["SCRIPT_NAME"])."/../src/OMK/views/js",
    "view_path"                 => dirname(__FILE__)."/../src/OMK/views",
    "mime_type_whitelist"       => $mimeTypeWhitelist,
    "authentificationAdapter"   => $authentificationAdapter,
    "databaseAdapter"           => $databaseAdapter,
    "fileAdapter"               => $fileAdapter,
    "loggerAdapter"             => $loggerAdapter,
    "translationAdapter"        => $translationAdapter,
    "uploadAdapter"             => $uploadAdapter
));

$action = array_key_exists("action", $_REQUEST) ? $_REQUEST["action"] : NULL ;

// Render (html)
if( NULL == $action ){
    if (array_key_exists("view", $_REQUEST) && NULL != $_REQUEST["view"]) {
        $view = $_REQUEST["view"];
    } else {
        $view = "upload";
    }
    die($client->render($view));
}

// Respond (json)
$response = $client->call(array(
    "action"    => $action,
    "format"    => "json"
    )
);
// TODO : FORCE REFRESH HEADER ?
echo $response;

