<?php

/**
 * Created by PhpStorm.
 * User: MiladHSP
 * Date: 24/04/2016
 * Time: 07:03 AM
 */

namespace Whisper\Model;

    class Application
    {

        public  $AppConfigs;


        public function __construct($Application)
        {
            $this->AppConfigs=$Application;

        }
        
        public function AddConfig($Config)
        {
            
        }

        public function Config($Code)
        {
            if (array_key_exists($Code, $this->AppConfigs)) {
                return $this->AppConfigs[$Code];
            }
            else
            {
                return false;
            }
        }
    }