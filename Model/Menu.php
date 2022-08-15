<?php

/**
 * .
 * User:  Milad Naderpour
 * Date: 17/09/2016
 * Time: 12:43 PM
 *
 *
 * @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 */

namespace Whisper\Model;

class Menu
{
    public $Menu = array();

    public function AddItem($Id,$Caption,$Item)
    {
        if (!array_key_exists($Id, $this->Menu)) {
            $this->Menu[$Id]["Id"] = $Id;
            $this->Menu[$Id]["Caption"] = $Caption;
            $this->Menu[$Id]["Items"] = array();
            $this->Menu[$Id]["Values"] = array();
        }
        if (!array_key_exists($Item->Id, $this->Menu[$Id]["Items"]))
            {
                $this->Menu[$Id]["Items"][$Item->Id]=$Item;
            }

    }

    public function AddValue($Id,$Value)
    {
        if (!array_key_exists($Id, $this->Menu)) {
            $this->Menu[$Id]["Id"] = $Id;
            $this->Menu[$Id]["Caption"] = " - - - ";
            $this->Menu[$Id]["Items"] = array();
            $this->Menu[$Id]["Values"] = array();
        }
        if (!array_key_exists($Value->Id, $this->Menu[$Id]["Values"])) {
            $this->Menu[$Id]["Values"][$Value->Key] = $Value;
        }
    }
}