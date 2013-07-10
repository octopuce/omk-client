<?php

/**
 * Player takes care of collecting data for display
 *
 * @author alban
 */
class OMK_Player extends OMK_Client_Friend {

    // ERR Codes 250-274
    const ERR_INVALID_REQUEST   = 250;
    const ERR_MISCONFIGURATION  = 251;
    const ERR_LOAD_ERROR        = 252;
    
    /**
     * Loads video files for HTML5 display
     * 
     * By default will attempt to retrieve all video formats associated to childs
     * or siblings.
     * 
     * @param array $options
     *   An associative array containing:
     *   - id : int or array of media id of original files or transcoded formats
     *   - strict: bool switch to skip loading neighbours / parent, defautlt false
     *   - childs_only: bool switch to use parent id only as a reference for childs loading 
     * 
     */
    public function getVideoData( $options = array() ){
        
        // Retrieves media_id
        if( array_key_exists("media_id",$options) && ! is_null( $options["media_id"] )){$media_id = $options["media_id"];} 
        // Failed at retrieving variable $media_id
        else {throw new Exception(__CLASS__."::".__METHOD__." = "._("Missing media id."), self::ERR_MISSING_PARAMETER);}
        
        // Retrieves strict
        if( array_key_exists("strict",$options) && ! is_null( $options["strict"] )){$strict = $options["strict"];} 
        else { $strict = FALSE;}
        
        // Retrieves childs_only
        if( array_key_exists("childs_only",$options) && ! is_null( $options["childs_only"] )){$childs_only = $options["childs_only"];} 
        else { $childs_only = FALSE;}
        
        // Returns if incompatible settings
        if( $childs_only && $strict){
            return array(
                "code"      => self::ERR_MISCONFIGURATION,
                "message"   => _("Requiring strict and childs only modes is invalid.")
            );        
        }
        
        /**
         * @var array the valid status for a file 
         */
        $status_list = implode(",",array(OMK_File_Adapter::STATUS_TRANSCODE_READY,OMK_File_Adapter::STATUS_TRANSCODE_REQUESTED,OMK_File_Adapter::STATUS_STORED));

        // Builds original file request WHERE part
        $where = array(
            "type = ?" => OMK_File_Adapter::TYPE_VIDEO,
            "status IN ({$status_list})" => OMK_Database_Adapter::REQ_NO_BINDING
        );
        // Adds single or multiple ids to WHERE
        if( is_array($media_id)){
            // Pretty naive cleaning
            foreach($media_id as $k => $v){
                $media_id[$k] = (int) $v;
            }
            $where["id IN (".implode(",", $media_id).")"] = OMK_Database_Adapter::REQ_NO_BINDING;
        }else{
            $where["id = ?"] = $media_id;
         
        }
        
        // Attempts to retrieve the file data
        $this->recordResult(
            $this->getClient()->getDatabaseAdapter()->select(array(
                "table" => "files",
                "where" => $where
            ))
        );
        // Returns if failed to retrieve file
        if( !$this->successResult()){
            return array(
                "code"      => self::ERR_LOAD_ERROR,
                "message"   => _("Couldn't load this file.")
            );
        }
        if( ! array_key_exists("rows", $this->result) || !count($this->result["rows"])){
            return array(
                "code"      => self::ERR_INVALID_REQUEST,
                "message"   => _("Invalid request: not a valid video file or wrong id.")
            );
        }
        $originalVideoData  = $this->result["rows"];
        

        
        // Returns success if strict mode
        if( $strict ){
            return array(
                "code"          => self::ERR_OK,
                "videoData"     => array($originalVideoData)
            );
        }
        
        $where = array(
            "type = ?" => OMK_File_Adapter::TYPE_VIDEO,
            "status IN ({$status_list})" => OMK_Database_Adapter::REQ_NO_BINDING
        );      
            
        // Defines the related video query where part for scalar or array of id
        if( is_array($media_id)){
            
            $where["id NOT IN (".implode(",", $media_id).")"] = OMK_Database_Adapter::REQ_NO_BINDING;
            
        }else{
            $where["id != ?"] = $media_id;
            
            // Retrieves the requested media
            $requestedMediaData = current($originalVideoData); 
            
            // Adds clause to load related childs if not a parent
            if( null != $requestedMediaData["parent_id"]){
                $parent_id = $requestedMediaData["parent_id"];
            }
            // Adds clause to load self child if parent
            else{
                $parent_id = $requestedMediaData["id"];
            }
            $where["parent_id = ?"] = $parent_id;
        }            
        // Attempts to load relatives
        $this->recordResult(
            $this->getClient()->getDatabaseAdapter()->select(
                array(
                    "table" => "files",
                    "where" => $where
                )
            )
        );        
                        
        // Returns error if failed
        if( !$this->successResult()){
            return array(
                "code"      => self::ERR_INVALID_REQUEST,
                "message"   => _("Couldn't load this file.")
            );
        }
        $relatedVideoData   = $this->result["rows"];
        
        // Returns sucessfully childs only if only childs mode
        if($childs_only){
            return array(
                "code"      => self::ERR_OK,
                "videoData" => $relatedVideoData
                );
        }
        
        // Returns sucessfully merged result
        return array(
            "code"      => self::ERR_OK,
            "videoData" => array_merge($originalVideoData, $relatedVideoData)
            );

    }
    
}
