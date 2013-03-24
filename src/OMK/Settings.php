<?php
/*
Settings
    Description:
    Affiche les réglages offerts par les transcoders (presets et par paramètres) pour chaque type de media et enregistre les choix de l'utilisateur.
    Reçoit les réglages des transcoders via le client d'API
    Methodes: 
    receive : reçoit les données du transcoder et les enregistre
    create : enregistre pour un media donné un nouveau réglage
    read : affiche les réglages enregistrés
    update 
    delete
    Config : -
    Views: Vue d'affichage par liste et de modification
*/
class OMK_Settings extends OMK_Client_Friend{
    
    // ERR CODE 175-199
    
    public function receive( $options = NULL ){
        
        if (NULL == $options || !count($options)) {
            throw new Exception(_("Missing options."));
        }
        if (array_key_exists("name", $options) && NULL != $options["name"]) {
            $name = $options["name"];
        } else {
            throw new Exception(_("Missing name."));
        }
        if (array_key_exists("settings", $options) && NULL != $options["settings"]) {
            $settings = $options["settings"];
        } else {
            throw new Exception(_("Missing settings."));
        }
        
        foreach ($settings as $theSetting) {
            
            if (array_key_exists("id", $theSetting) && NULL != $theSetting["id"]) {
                $id = $theSetting["id"];
            } else {
                throw new Exception(_("Missing id."));
            }
            $theSetting["transcoder_name"] = $name;
            
            $this->recordResult($this->getClient()->getDatabaseAdapter()->save(array(
                "table"     => "settings",
                "data"      => $theSetting,
                "where"     => array(
                    "transcoder_name = ?" => $name,
                    "id = ?"    => $id 
                )
                
            )));
            if( ! $this->successResult()){
                return $this->getResult();
            }
            
        }
        return $this->getResult();
    }
    
}