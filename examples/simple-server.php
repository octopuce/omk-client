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
    "storage_path"  => "/tmp/singleFolder"
));
$uploadAdapter = new OMK_Upload_SingleFolder(array(
    "tmp_path"      => "/tmp",
    "name"          => "singleFolder"
));
$loggerAdapter  = new OMK_Logger_File(array(
   "level"          => OMK_Logger_Adapter::DEBUG, 
   "log_file_path"  => "/tmp/omk.log" 
));
$translationAdapter = new OMK_Translation_Dummy(array(
    
));

// set up the client
$client = new OMK_Client(array(
    "application_name"          => "simple-server-example",
    "api_local_key"             => "1234567890abcdef",
    "api_local_url"             => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}{$_SERVER["SCRIPT_NAME"]}",
    "api_transcoder_key"        => "1234567890abcdef",
    "api_transcoder_url"        => "http://test.openmediakit.fr/",
    "config_file"               => __FILE__,
    "css_url_path"              => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}".dirname($_SERVER["SCRIPT_NAME"])."/../src/OMK/views/css",
    "js_url_path"               => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}".dirname($_SERVER["SCRIPT_NAME"])."/../src/OMK/views/js",
    "view_path"                 => dirname(__FILE__)."/../src/OMK/views",
    "authentificationAdapter"   => $authentificationAdapter,
    "databaseAdapter"           => $databaseAdapter,
    "fileAdapter"               => $fileAdapter,
    "loggerAdapter"             => $loggerAdapter,
    "translationAdapter"        => $translationAdapter,
    "uploadAdapter"             => $uploadAdapter
));

// Cron jobs will skip this, not apache. A better solution is to set a separate config file
if( "cli" != PHP_SAPI) {
    
    $action = array_key_exists("action", $_REQUEST) ? $_REQUEST["action"] : NULL ;

    // Render (html)
    if( NULL == $action ){
        die($client->render("upload"));
    }

    // Respond (json)
    $response = $client->call(array(
        "action" => $action
        )
    );
    // TODO : FORCE REFRESH HEADER ?
    echo $response;

    
}
