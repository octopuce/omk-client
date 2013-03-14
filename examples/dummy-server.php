<?php

/** Implements a dummy upload response server for the omk client
  * 
  */ 

// set up include path and autoload
set_include_path(dirname(__FILE__)."/../src".PATH_SEPARATOR.get_include_path());
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

// instanciate a dummy authentification adapter
$authentificationAdapter = new OMK_Authentification_Dummy();

// instanciate a dummy db adapter
$databaseAdapter = new OMK_Database_Dummy();

// instanciate a dummy file adapter
$fileAdapter = new OMK_File_Dummy();

// instanciate a dummy upload adapter
$uploadAdapter = new OMK_Upload_Dummy(array("name"=>"dummy"));

// instanciate a dummy logger adapter
$loggerAdapter  = new OMK_Logger_Dummy();

// instanciate a dummy translation adapter
$translationAdapter = new OMK_Translation_Dummy();

// set up the client
$client = new OMK_Client(array(
    "api_local_key"             => "1234567890abcdef",
    "api_local_url"             => (strstr( $_SERVER["SERVER_PROTOCOL"], "HTTP/") ? "http":"https")."://{$_SERVER["SERVER_NAME"]}{$_SERVER["SCRIPT_NAME"]}",
    "api_transcoder_key"        => "1234567890abcdef",
    "api_transcoder_url"        => "http://test.openmediakit.fr/",
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


$action = array_key_exists("action", $_REQUEST) ? $_REQUEST["action"] : NULL ;

// Render (html)
if( ( NULL == $action )||( !in_array($action,array("app_test","upload") ) )){
    echo $client->render("upload");
    die();
}

// Respond (json)
$response = $client->call(array(
    "action" => $action
    )
);
echo $response;