<?php class OMK_Logger_Database extends OMK_Logger_Adapter {
    
     public function log($level = self::OMK_LOG_DEBUG, $msg = ""){
         
         $db = $this->getClient()->getDatabaseAdapter();
         $db->insert("logs",$level,$msg);
         
     }
} 
