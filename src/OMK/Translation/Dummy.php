<?php class OMK_Translation_Dummy extends OMK_Translation_Adapter {
    function t( $string ){
        return $string;
    }
} 
