<?php 
class OMK_Upload_Dummy extends OMK_Upload_Adapter {

    function upload( $options ){
       
        echo $options;
        
    }
} 
