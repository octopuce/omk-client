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
    const ERR_PUSH  = 150;
    
    public function push( $options = NULL ){
        
        if( NULL == $options || !count($options)){
            throw new OMK_Exception(_("Missing options."));    
        }
        if (array_key_exists("origin", $options) && NULL != $options["origin"]) {
            $origin = $options["origin"];
        }else{
            $origin = "app";
        }
        if (array_key_exists("handler", $options) && NULL != $options["handler"]) {
            $handler = $options["handler"];
        }else{ 
            $handler = "transcoder";
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
        $databaseAdapter = $this->getClient()->getDatabaseAdapter();
        $this->recordResult(  
            $databaseAdapter->insert(
                array(
                    "table" => "queue",
                    "data"  => array(
                        "origin"    => $origin,
                        "handler"   => $handler,
                        "action"    => $action,
                        "object_id" => $object_id
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
    
    /**
     * 
     */
    public function fetchCronTasks(){
        $this->getClient()->getDatabaseAdapter()->select(array(
            "table" => "queue",
            "where" => array(
                "failed_attempts < ?" => 10
                ),
            "order" => "handler DESC"
        )) ;
    }
}