<?php class OMK_Client_Response extends OMK_Client_Friend {

     public function __construct(OMK_Client $client) {
         $this->setClient($client);
     }
     
     /**
      * Calls a method, stores internally the results to be displayed by the client
      * 
      * @param type $options
      * @return void 
      * @throws OMK_Exception
      */
     public function run( $options = NULL ){
         if (NULL == $options) {
             throw new OMK_Exception(_("Missing options."));
         }
         // Checks action
         if (array_key_exists("action", $options) && NULL != $options["action"]) {
             $action = $options["action"];
         } else {
             throw new OMK_Exception(_("Missing action."));
         }
         if( !method_exists($this, $action)){
             throw new OMK_Exception(_("Invalid action requested."));
         }
         
         // Call the method
         $this->$action($options);
     }
     
     // always true response for local test and availability checks
     /**
      * 
      * @param type $options
      * @return array
      * 
      * 
app_test

Who : [app,transcoder] -> App

When : whenever

What : App returns current timestamp

Request Params : void

onSuccess : void

onError : void

répond à une sorte de ping : pratique pour l'installation (fonctionne) ou pour que le serveur vérifie qu'elle elle est up
      */
     protected function app_test_response($options = null){
         $this->result =  array(
             "code" => 0,
             "message" => _("Local instance is up.")
         );
     }
     
     /**
      * 
      * @param type $options
      * transcoder_send_format

Who : Transcoder -> App

When : Transcoder finished transcoding a format

What : App adds the media format to its download queue

Request Params

    id:int
    preset:string
    url:string or list of strings (for thumbs)

onSuccess

    App updates media_format status to TRANSCODED or INVALID
    App updates media status to META_RECEIVED or META_INVALID

onError : Transcode logs error

      */
     protected function transcoder_send_format($options = null){
         
         // Retrieves file id - format - url - (option) uploadAdapter
         
         // Retrieves file through upload Adapter
         
         // Moves file through file Adapter
         
         
         
     }
     /**
      * 
      * @param type $options
      * 
transcoder_send_metadata

Who : Transcoder -> App

When : media meta decoded by transcoder

What : App sets media metadata

Request Params

    id:int
    meta:hashset{media_type:ENUM,media_meta:hashset{...}}

onSuccess : App updates media status to META_RECEIVED or META_INVALID
      */
     protected function transcoder_send_metadata($options = null){
         
     }
     
     /*
      * transcoder_cron
        Who : Transcoder -> App
        When : Transcoder cron ticks
        What : App executes cron tasks
        Request Params : null
        onSuccess : null
        onError : Transcoder logs error
      */
     protected function transcoder_cron($options = null){

        // Runs the cron action
        $cron = new OMK_Cron( $options );
        $cron->setClient($this->getClient());
        $this->recordResult(
             $cron->run()
        );
         
     }
     /**
      * 
      * @param type $options
      * transcoder_get_settings

Pas d'authentification requise, méthode publique

Who : Transcoder -> App

When : whenever

What : Transcoder returns available presets

Request Params : void

onSuccess : app records available formats to make a selection

onError : void
      */
     protected function transcoder_get_settings($options =null){
         
     }

     // Manages upload
     protected function upload($request = null){
         
        $this->recordResult( 
            $this->getClient()->getUploadAdapter($request)->upload($request) 
        );
        if( ! $this->successResult() ){
            return; // Failed to upload or upload in progress
        }
        $file_path      = $this->result["file_path"];
        $file_name      = $this->result["file_name"];
        $upload_adapter = $this->result["upload_adapter"];
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->insert(array(
                "table" => "files",
                "data"  => array(
                    "owner_id"  => $this->getClient()->getAuthentificationAdapter()->getUserId(),
                    "file_name" => $file_name,
                    "status"    => OMK_Database_Adapter::STATUS_UPLOADED
                )
            )) 
        );
        if(! $this->successResult() ){
           return; // Failed to include file into db
        }
        $file_id = $this->result["id"];
        $this->recordResult( 
            $this->getClient()->getFileAdapter()->create(
                    array(
                    "file_id"   => $file_id,
                    "file_name" => $file_name,
                    "file_path" => $file_path
                )
            )
        );
        if(! $this->successResult() ){
           return; // Failed to move file to final destination
        }
        // Update file record in db 
        $this->recordResult( 
            $this->getClient()->getDatabaseAdapter()->update(array(
                "table" => "files",
                "id"    => $file_id,
                "data"  => array(
                    "dt_updated"    => "NOW",
                    "file_path"     => $this->result["file_path"],
                    "status"        => OMK_Database_Adapter::STATUS_STORED
                )
            ))
        );
        if(! $this->successResult() ){
           return; // Failed to move file to final destination
        }
        // Adds file transcoding request to queue
        $this->recordResult( 
            $this->getClient()->getQueue()->push(
                array(
                    "priority"      => OMK_Queue::PRIORITY_HIGH,
                    "action"        => "app_new_media",
                    "object_id"     => $file_id,
                )
            )
        );
        if(! $this->successResult() ){
           return; // Failed to add item to queue
        }

    }
     
}