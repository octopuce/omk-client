<?php
/*
FileAdapter
    Description: 
    Le file Adapter est chargé du stockage des fichiers originaux et transcodés. 
    Il les ventile selon sa logique propre dans des dossiers.
    Methodes: 
    open read write seek(tell?) close size(stat?)
    Config: 
    path_origin, path_transcoded, path_temp
*/
class OMK_File_Adapter extends OMK_Client_Friend {
    
    // ERR CODE 50-74

    const ERR_STORAGE_AUTH      = 50;
    const ERR_STORAGE_CREATE    = 51;
    const ERR_STORAGE_MOVE      = 52;
    
    /**
     * 
     * @param string $options.file_path
     */
    public function create( $options){
        
    }
    
    public function open( $options ){
        
    }
    
    public function read( $options ){
        
    }
    
    public function write( $options ){
        
    }
    
    public function seek( $options ){
        
    }
    
    public function size( $options ){
        
    }
    
    
    
}