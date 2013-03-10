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
    
    public function push( $options = null ){
        
        if( null == $options || !count($options)){
            throw new OMK_Exception("Missing options.");
        }
        if (array_key_exists("origin", $options) && null != $options["origin"]) {
            $origin = $options["origin"];
        }else{
            $origin = "app";
        }
        if (array_key_exists("handler", $options) && null != $options["handler"]) {
            $handler = $options["handler"];
        }else{ 
            $handler = "transcoder";
        }
        if (array_key_exists("action", $options) && null != $options["action"]) {
            $action = $options["action"];
        }else{
            throw new OMK_Exception("Missing action.");
        }
        if (array_key_exists("object_id", $options) && null != $options["object_id"]) {
            $object_id = $options["object_id"];
        }else{ 
            throw new OMK_Exception("Missing object id.");
        }
        $databaseAdapter = $this->getClient()->getDatabaseAdapter();
        return $databaseAdapter->insert(array(
            "table" => "queue",
            "data"  => array(
                "origin"    => $origin,
                "handler"   => $handler,
                "action"    => $action,
                "object_id" => $object_id
            )
        ));

    }
    
}