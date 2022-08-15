<?php

namespace Whisper\App;

use Monolog\Logger;

/**
 *                Whisper Narrator
 *
 *  Read Ticket Information And Say It For Customer
 *
 * @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 *
 *   User: Milad Naderpour
 *   Date: 19/04/2016
 *   Time: 12:37 AM
 *
 *
 *
 */
class Narrator
{
    private $Agi,$_logger;

    private $sysSound, $Digit;

    function __construct($Agi,$SysSound,$Digit,$logStream)
    {
        $this->Agi=$Agi;
        $this->Digit=$Digit;
        $this->sysSound=$SysSound;
        $this->_logger = new Logger('Narrator');
        $this->_logger->pushHandler($logStream);
    }

    public function SayReservationFounded($Ticket)
    {
        //$this->_Logger->LOG($this->_ChannelName, 3, "Start Say Reservation Founded ", "SayReservationFounded");

        $this->Agi->exec('playback', 'rcp-somereceptionfound');
        $this->Agi->exec('playback', 'rcp-receptioninfo');

        $this->SayTicket($Ticket);

        return 1;

    }

    public function SayReservationNotFounded()
    {

        //$this->_Logger->LOG($this->_ChannelName, 3, "Start Say Reservation Not Founded ", "SayReservationNotFounded");
        //$this->_DBEng->ChangeActiveCallState($this->_CallId, "Narrator::Play Reservation Not Founded!");
        $this->Agi->exec('playback', 'rcp-noreception');

    }

    public function SayReservationInformation($Ticket)
    {
        $this->_logger->debug('Play Ticket ' , ["SayReservationInformation"]);
        $this->PlayMessage($this->SystemPlay("sys_reservationSuccessfull"),"sys_reservationSuccessfull");
        $this->PlayMessage($this->SystemPlay("sys_TakeANote") ,"sys_TakeANote");
        $this->SayTicket($Ticket);
        return 1;
    }

    private function SayTicket($Ticket)
    {
        $this->_logger->debug('Start Say Reservation Detail' , ["SayTicket"]);
        $this->_logger->notice("Visit Date: $Ticket->date_nobat  Visit Time:$Ticket->time",["SayTicket"]);
        $VisitDate = $Ticket->date_nobat;
        $VisitTime = $Ticket->time;
        $shift = $Ticket->shift;
        $VisitDay = substr($VisitDate, 8, 2);
        $VisitMonth = substr($VisitDate, 5, 2);
        $VisitHour = substr($VisitTime, 0, 2);
        $VisitMinute = substr($VisitTime, 3, 2);
        $this->PlayMessage($this->SystemPlay("sys_visitdate"), "Visit Date" );
        $this->SayVDate($VisitMonth, $VisitDay);
        $this->SayHour($VisitHour, $VisitMinute);
    }

    private function SayHour($Hour, $Minute)
    {
        $this->_logger->debug("Say Hour :".$Hour . ":" . $Minute  , ["SayHour"]);

        $first = substr($Hour, 0, 1);
        $last = substr($Hour, 1, 1);

        $this->PlayMessage($this->SystemPlay("sys_Hour"), "Say Hour");

        if ($Minute == "00") {
            $this->_logger->debug("Minute is 0 Only Say Hour"  , ["SayHour"]);

            if ($first == "0") {
                $this->_logger->debug("Hour is < 10"  , ["SayHour"]);
                $this->PlayMessage($this->Digit . $last, $this->Digit . $last);
            } else {
                $this->_logger->debug("Minute is 0 Only Say Hour"  , ["SayHour"]);
                $this->PlayMessage($this->Digit . $Hour, $this->Digit . $Hour);
            }
        } else {
            $this->_logger->debug("Minute is not 0 Say Hour And Minute"  , ["SayHour"]);
            if ($first == "0") {
                $this->_logger->debug("Hour is < 10"  , ["SayHour"]);
                $this->PlayMessage($this->Digit . $last . '_o', $this->Digit . $last . '_o');
            } else {
                $this->_logger->debug("Hour is > 10"  , ["SayHour"]);
                $this->PlayMessage($this->Digit . $Hour . '_o', $this->Digit . $Hour . '_o');
            }
            $this->SayDig($Minute);
            $this->PlayMessage($this->SystemPlay("sys_Minute"), $this->SystemPlay("sys_Minute"));
        }
    }

    private function SayDig($dig)
    {
        $this->_logger->debug("SayDig !"  , ["SayDig"]);

        $first = substr($dig, 0, 1);
        $last = substr($dig, 1, 1);

        if ($first == "0") {
            $this->PlayMessage($this->Digit . $last, $this->Digit . $last);
        } elseif ($first == "1") {
            $this->PlayMessage($this->Digit . $dig, $this->Digit . $dig);
        } elseif ($last != "0") {
            $this->PlayMessage($this->Digit . $first . "0_o", $this->Digit . $first . "0_o");
            $this->PlayMessage($this->Digit . $last,$this->Digit . $last);
        } else {
            $this->PlayMessage($this->Digit . $first . "0", $this->Digit . $first . "0");
        }

    }

    private function SayVDate($Month, $day)
    {
        $this->_logger->debug("Start Say Reservation Detail "  , ["SayVDate"]);
        $first = substr($day, 0, 1);
        $last = substr($day, 1, 1);
        $this->_logger->debug("First Digit:" . $first . " Last Digit:" . $last  , ["SayVDate"]);

        if ($first == "0") {
            $this->_logger->debug("1)playback day Last: " . $last . '_ome', ["SayVDate"]);
            $this->PlayMessage($this->Digit . $last . '_ome', $this->Digit . $last . '_ome');
        } else {
            $this->_logger->debug("1)playback day Full : " . $day . '_ome', ["SayVDate"]);
            $this->PlayMessage($this->Digit . $day . '_ome', $this->Digit . $day . '_ome');
        }
        $this->_logger->debug("2)playback Month: " . "Month_" . $Month, ["SayVDate"]);
        $this->PlayMessage($this->Digit . "Month_" . $Month, $this->Digit . "Month_" . $Month);

    }

    private function SayRef($Ref)
    {
        $this->_logger->debug("Say Reference Number", ["SayRef"]);
        $Ar = str_split($Ref, 2);
        foreach ($Ar as $val) {
            $this->SayRefDig($val);
        }
    }

    private function SayRefDig($dig)
    {

        $first = substr($dig, 0, 1);
        $last = substr($dig, 1, 1);

        if (strlen($dig) == 1) {
            $this->Agi->exec('playback', "d_" . $first);
        } elseif ($first == "0" && $last == "0") {
            $this->Agi->exec('playback', "d_00");
        } elseif ($first == "0") {
            $this->Agi->exec('playback', "d_0");
            $this->Agi->exec('playback', "d_" . $last);
        } elseif ($first == "1") {
            $this->Agi->exec('playback', "d_" . $dig);
        } elseif ($last != "0") {
            $this->Agi->exec('playback', "d_" . $first . "0_o");
            $this->Agi->exec('playback', "d_" . $last);
        } else {
            $this->Agi->exec('playback', "d_" . $first . "0");
        }

    }

    private function PlayMessage($SoundFile, $Message)
    {
        $this->_logger->debug("Play:" . $Message, ["PlayMessage"]);
        $this->Agi->exec('Playback', $SoundFile);
    }

    private function OnTimeOut($PlayBack, $Context, $SubUnitId, $Extra = "", $MSG = "")
    {
        if ($Extra == "") {
            $Extra = "OnTimeOut";
            $CallMessage = $Context . "TimeOut";
            $MSG = $Context . "TimeOut";
        } else {
            $CallMessage = $MSG;
        }

        $this->Agi->exec('playback', $PlayBack);
    }

    private function SystemPlay($Sound)
    {
        return $this->sysSound . "/" . $Sound;
    }
}
