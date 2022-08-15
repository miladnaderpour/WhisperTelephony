<?php

/**
 * User: Milad Naderpour
 * Date: 13/10/2016
 * Time: 09:16 AM
 *
 *
 * @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 */

namespace Whisper\Model;

class MenuValue
{
    Public $Id,$MenuId,$Key,$Value,$Description;

    public function __construct($Id,$MenuId,$Key,$Value,$Description)
    {
        $this->Id = $Id;
        $this->MenuId = $MenuId;
        $this->Key = $Key;
        $this->Value = $Value;
        $this->Description = $Description;
    }

}