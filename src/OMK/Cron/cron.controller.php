<?php
error_reporting(E_WARNING);
if( "cli" != PHP_SAPI) {
    die("Can't run in this mode.");
}
$config_file = $argv[1];
if( !file_exists($config_file)) {
    die("Could not find config file.");
}
if( !is_readable($config_file)){
    die("Could not read config file.");
}
include($config_file);
if( !isset($client)){
    die("Could not instanciate omk client.");
}

$cron = new OMK_Cron_Exec();
$cron->setClient($client);
$cron->recordResult($cron->startController());
if( !$cron->successResult()){
    return;
}