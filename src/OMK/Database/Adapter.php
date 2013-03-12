<?php
/*
DatabaseAdapter
    Description:
    Reçoit la configuration de la base de données et conduit les opérations d'écriture / lecture demandées par les objets
    Methodes: 
    cf. Zend_Adapter
    search
    Config : 
     db credentials classiques (db,user,pwd,host)
*/
abstract class OMK_Database_Adapter extends OMK_Client_Friend {
 
    // ERR CODE 25 - 49
    const ERR_INSERT                    = 25;
    const ERR_DELETE                    = 26;
    const ERR_SELECT                    = 27;
    const ERR_UPDATE                    = 28;
    
    const STATUS_UPLOADED               = 1;
    const STATUS_STORED                 = 2;
    const STATUS_METADATA_REQUESTED     = 3;
    const STATUS_METADATA_RECEIVED      = 4;
    const STATUS_TRANSCODE_REQUESTED    = 5;
    const STATUS_TRANSCODE_PARTIALLY    = 6;
    const STATUS_TRANSCODE_COMPLETE     = 7;
    
    
    function insert($options = null){
        
        throw new OMK_Exception(_("You must override this method."));
        
    }
    
    function update($options = NULL ){
        
        throw new OMK_Exception(_("You must override this method."));
        
    }

    
    function select($options = null){
        
        throw new OMK_Exception(_("You must override this method."));
        
    }
    
    function delete($options = null){
        
        throw new OMK_Exception(_("You must override this method."));
        
    }
           
}