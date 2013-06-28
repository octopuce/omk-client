<?php
/*
Queue
    Description: 
    Gère la file des opérations à effectuer : crée / détruit des éléments, définit leur état, retourne les opérations à traiter par type ou globalement
    Methodes: 
    add/remove(i) :  ajoute/enlève un élément à la pile
    getTask(X) : retourne un élément de la pile (de type X si fourni)
    setTask(X,S) : assigne un statut S à l'élément X de la pile
    Config :  -
    Views: Liste des éléments dans la pile et modification des éléments
*/
class OMK_Queue extends OMK_Client_Friend{
  
    // ERR CODE 150-174
    const ERR_PUSH              = 150;
    
    const LOCK_LOCKED           = 1;
    const LOCK_UNLOCKED         = 0;
    
    const STATUS_NULL           = 0;
    const STATUS_IN_PROGRESS    = 1;
    const STATUS_ERROR          = 2;
    const STATUS_SUCCESS        = 3;
    
    const PRIORITY_HIGH         = 5;
    const PRIORITY_MEDIUM       = 10;
    const PRIORITY_LOW          = 15;
    
    
    public function push( $options = NULL ){
        
        if( NULL == $options || !count($options)){
            throw new OMK_Exception(_("Missing options."));    
        }
        if (array_key_exists("priority", $options) && NULL != $options["priority"]) {
            $priority = $options["priority"];
        } else {
            $priority = self::PRIORITY_HIGH;
        }
        if (array_key_exists("action", $options) && NULL != $options["action"]) {
            $action = $options["action"];
        }else{
            throw new OMK_Exception(_("Missing action."));
        }
        if (array_key_exists("object_id", $options) && NULL != $options["object_id"]) {
            $object_id = $options["object_id"];
        }else{ 
            throw new OMK_Exception(_("Missing object id."));
        }
        if (array_key_exists("params", $options) && NULL != $options["params"]) {
            $params = $options["params"];
        } else {
            $params = "";
        }
        $databaseAdapter = $this->getClient()->getDatabaseAdapter();
        $this->recordResult(  
            $databaseAdapter->insert(
                array(
                    "table" => "queue",
                    "data"  => array(
                        "priority"          => $priority,
                        "action"            => $action,
                        "object_id"         => $object_id,
                        "params"            => $params,
                        "dt_created"        => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP,
                        "dt_last_request"   => OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP
                        
                    )
                )
            )
        );
        if( !$this->successResult()){
            return array(
                "code"      => self::ERR_PUSH,
                "message"   => sprintf(_("Could not push %s in queue"),$action)
            );
        }
        return array(
            "code"  => 0,
            "message"   => sprintf(_("Successfully added %s to queue"),$action)
        );
    }
    

}