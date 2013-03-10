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
 
    function insert($options = null){
        
        throw new OMK_Exception("You must override this method.");
        
    }
    
    function update($options = null ){
        
        throw new OMK_Exception("You must override this method.");
        
    }

    
    function select($options = null){
        
        throw new OMK_Exception("You must override this method.");
        
    }
    
    function delete($options = null){
        
        throw new OMK_Exception("You must override this method.");
        
    }
           
}