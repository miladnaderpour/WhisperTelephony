<?php

/**
 *      Clinic Program Object
 *
 * User: Milad Naderpour
 * Date: 13/10/2016
 * Time: 03:52 PM
 */

namespace Whisper\Model;

class ClinicProgram
{
    public $Id,$VisitDate, $ClinicCode, $DoctorCode, $Shift, $Capacity, $Count, $RemainCapacity;


    public function SetFromServiceResult($Id,$Result)
    {
        $this->Id=$Id;
        $this->VisitDate=$Result->visitDate;
        $this->ClinicCode=$Result->clinicCode;
        $this->DoctorCode=$Result->doctorCode;
        $this->Shift=$Result->shift;
        $this->Capacity=$Result->capacity;
        $this->Count=$Result->count;
        $this->RemainCapacity=$Result->remainCapacity;

    }

}