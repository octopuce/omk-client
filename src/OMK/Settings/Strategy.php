<?php

class OMK_Settings_Strategy extends OMK_Client_Friend{

    
    protected $type;
    protected $metadata;
    
    /**
     * Retrieves the right settings for a given media type and metadata
     * 
     * @param array $options
     * @return array code, message, settingsList
     * @throws OMK_Exception
     */
    public function getSettingsIdList( array $options = NULL ){
        
        $settingsList = array();
        
        // Checks parameters
        if(is_null($options)){
            throw new OMK_Exception("Settings Manager missing options parameter");
        }
        if (array_key_exists("type", $options) && !is_null($options["type"])) {
            $type = $options["type"];
        } else {
            throw new OMK_Exception("Missing parameter type", self::ERR_MISSING_PARAMETER);
        }
        if (array_key_exists("metadata", $options) && !is_null($options["metadata"])) {
            $this->metadata = $options["metadata"];
        } else {
            throw new OMK_Exception("Missing parameter metadata", self::ERR_MISSING_PARAMETER);
        }
        
        //@todo Check "other" case, as FFMPEG 1 returns this is some case
        
        // Checks a method exists for the requested type
        if( !method_exists($this, $type)){
            throw new OMK_Exception("Invalid type requested : {$type}", self::ERR_INVALID_PARAMETER);
        }
        
        // Attempts to load settings to be requested
        $this->recordResult($this->getClient()->getDatabaseAdapter()->select(array(
            "table" => "settings",
            "where" => array(
                "type = ?"      => $type,
                "checked = ?"   => OMK_Settings_Manager::CHECKED,
                "available = ?" => OMK_Settings_Manager::AVAILABLE_TRUE
            )
        )));

        // Exits if failed
        if( !$this->successResult()){throw new OMK_Exception($this->result["message"],$this->result["code"]);}
        
        // Checks the validity of the db result
        if (array_key_exists("rows", $this->result) && count($this->result["rows"]) ) {
            $settingsList = $this->result["rows"];
        } else {
            return(array(
                "code"      => self::ERR_MISSING_PARAMETER,
                "message"   => sprintf( _("No settings for the %s media type."), $type)
            ));
        }

        // Retrieves the list
        $settingsList = $this->$type($settingsList);
        
        return array(
            "code"              => OMK_Client_Friend::ERR_OK,
            "message"           => "Sucessfully choosed settings", 
            "settingsList"      =>  $settingsList
        );
    }

    /**
     * Barebone method for video
     * 
     * @param array $settingsList
     * @return type
     */
    protected function video( array $settingsList ){
        return $settingsList;
    }
    
    /**
     * Barebone method for audio
     * 
     * @param array $settingsList
     * @return type
     */
    protected function audio( array $settingsList ){
        return $settingsList;
    }

}
