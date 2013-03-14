<?php
class OMK_Translation_Gettext extends OMK_Translation_Adapter{
 
    /**
     * 
     * @param array $options
     *   An associative array containing:
     *   - translation_path: The location of where location files are.
     *   - locale: The locale to use for translation.
     * 
     * @throws Exception
     * @throws OMK_Exception
     */
    public function __construct( $options = NULL ) {

        if (NULL == $options || !count($options)) {
            throw new Exception(_("Missing options."));
        }
        if (array_key_exists("locale", $options) && NULL != $options["locale"]) {
            $locale = $options["locale"];
        } else {
            throw new OMK_Exception(_("Missing locale."));
        }
        if (array_key_exists("translation_path", $options) && NULL != $options["translation_path"]) {
            $translation_path = $options["translation_path"];
        } else {
            throw new OMK_Exception(_("Missing translation_path."));
        }
        putenv("LC_ALL={$locale}");
        setlocale(LC_ALL, $locale);
        // Specify location of translation tables
        bindtextdomain("OMKClient", $translation_path);

        // Choose domain
        textdomain("OMKClient");

        // Translation is looking for in ./$translation_path/$locale/LC_MESSAGES/OMKClient.mo now
        
    }
    
    
    function translate ($string){
        return _($string);
    }
}
