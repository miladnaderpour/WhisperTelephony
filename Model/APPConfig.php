<?php
/**
 * Class APP Config
 *
 * User: Milad Naderpour
 * Date: 08/09/2022
 * Time: 02:18 PM
 *
 * 		@author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 */

namespace Whisper\Model;

class APPConfig
{

    public $Name,$Code,$Value,$Flag;

    public function __construct($Name,$Code,$Value,$Flag)
    {
        $this->Name=$Name;
        $this->Code=$Code;
        $this->Value=$Value;
        $this->Flag=$Flag;

    }

}