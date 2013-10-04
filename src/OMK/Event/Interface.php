<?php

interface OMK_Event_Interface {
    
    function getEventName();
    function run($options);
}
