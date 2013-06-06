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
    const ERR_CLAUSE_WHERE_ARRAY        = 29;
    const ERR_INVALID_LOCK              = 30;
    
    
    const REQ_CURRENT_TIMESTAMP         = "_%NOW%_";
    const REQ_INCREMENT                 ="_%INCREMENT%_";
    const REQ_NO_BINDING                ="_%NO_BINDING%_";
    
    
    function count($options = NULL){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    function delete($options = NULL){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    function insert($options = NULL){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }

    function lock( $options = NULL ){

        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);

    }
         
    function save( $options = NULL){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
    
    function select($options = NULL){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }  
    
    function unlock( $options = NULL ){

        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);

    }
         
    function update($options = NULL ){
        
        throw new OMK_Exception(_("You must override this method."), OMK_Client_Friend::ERR_METHOD_OVERRIDE_REQUIRED);
        
    }
      
}