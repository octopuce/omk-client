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
    const ERR_INVALID_SETTING       = 175;
    const UNCHECKED                 = 0;
    const CHECKED                   = 1;
    const AVAILABLE_TRUE            = 1;
    const AVAILABLE_FALSE           = 2;
    
    const SETTINGS_TYPE_ORIGINAL    = 0;
    
    
    public function receive( $options = NULL ){
        
        if (NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        if (array_key_exists("name", $options) && NULL != $options["name"]) {
            $name = $options["name"];
        } else {
            throw new OMK_Exception(_("Missing name."));
        }
        if (array_key_exists("settings", $options) && NULL != $options["settings"]) {
            $settings = $options["settings"];
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }
        
        foreach ($settings as $theSetting) {
            
            if (array_key_exists("id", $theSetting) && NULL != $theSetting["id"]) {
                $id = $theSetting["id"];
            } else {
                throw new OMK_Exception(_("Missing id."));
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
                throw new OMK_Exception($this->result["message"],$this->result["code"]);
            }
            
        }
        return $this->getResult();
    }
    
    public function update( $options = NULL ){
        
        if (array_key_exists("settings", $_REQUEST) && NULL != $_REQUEST["settings"]) {
            $settingsValues = array_keys( $_REQUEST["settings"] );
        } else {
            throw new OMK_Exception(_("Missing settings."));
        }
        $this->recordResult($this->getClient()->getDatabaseAdapter()->update(array(
            "table" => "settings",
            "data"  => array(
                "checked" => self::UNCHECKED
                ),
            "where" => array()
        )));
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        $this->recordResult($this->getClient()->getDatabaseAdapter()->update(array(
            "table" => "settings",
            "data"  => array(
                "checked" => self::CHECKED
                ),
            "where" => array(
                "id IN ?" => $settingsValues
            )
        )));
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        return array(
            "code"      => OMK_Client_Friend::ERR_OK,
            "message"   => _("Settings successfully updated") 
        );
    }
}