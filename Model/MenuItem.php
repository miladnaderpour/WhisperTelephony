<?php

/**
 *
 * User: Milad Naderpour
 * Date: 17/09/2016
 * Time: 12:45 PM
 *
 *
 * @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 *
 */


namespace Whisper\Model;

class MenuItem
{
    Public $Id,$MenuId,$ItemType,$Index,$Caption,$SoundFile,$Options,$Output,$Data;

    public function __construct($Id,$ItemType,$Index,$Caption,$SoundFile,$Options,$Output,$Data)
    {
        $this->Id=$Id;
        $this->ItemType=$ItemType;
        $this->Index=$Index;
        $this->Caption=$Caption;
        $this->SoundFile=$SoundFile;
        $this->Options=$Options;
        $this->Output=$Output;
        $this->Data=$Data;
    }

}