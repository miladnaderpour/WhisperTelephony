<?php

namespace Whisper\App;

use Monolog\Logger;
use Whisper\Model\OperationResult;

/**
 * User: Milad Naderpour
 * Date: 11/10/2016
 * Time: 04:28 PM
 *
 *  Sata HIS Tell Engine
 *
 */
class STEngine
{
    private $_ChannelName = "STEngine";
    private $_ProgramId = 600;
    private $Agi, $Db, $Api, $_logger, $_Narrator;
    private $Menu, $CurrentMenu;
    private $_ARResults;

    /**
     * Initialize The Sata Tell Engine Program
     * @param $Agi
     * @param $Db
     * @param $Api
     * @param $logStream
     */
    function __construct($Agi, $Db, $Api, $logStream)
    {
        $this->Agi = $Agi;
        $this->Db = $Db;
        $this->Api = $Api;
        $this->_logger = new Logger('STEngine');
        $this->_logger->pushHandler($logStream);
        $this->_Narrator = new Narrator($Agi, "whisper/system", "whisper/Digit/", $logStream);
        $this->_ARResults = array();
    }

    public function Start($MenuId)
    {
        $this->_logger->info("Start Engine...", ["Start"]);
        $this->Agi->verbose("Whisper Engine Started");
        if ($this->LoadMenu()) {
            $this->Program($MenuId);
        }
    }

    private function LoadMenu()
    {
        $this->_logger->debug("Load Menu", ["LoadMenu"]);
        $Result = $this->Db->GetMenus();
        if (!$Result->_Error) {
            $this->Menu = $Result->_Result;
            $this->_logger->debug("Menu Loaded", ["LoadMenu"]);
            return true;
        } else {
            $this->_logger->error("Load Menu", ["LoadMenu"]);
            return false;
        }
    }

    private function GetMenu($MenuId)
    {
        if (array_key_exists($MenuId, $this->Menu->Menu))
            return OperationResult::ReturnSuccessResult($this->Menu->Menu[$MenuId]);
        return OperationResult::ReturnErrorResult();
    }

    private function Program($MenuId)
    {
        $p = $this->GetMenu($MenuId);
        if (!$p->_Error)
            $this->ExcProgram($this->Menu->Menu[$MenuId]);
    }

    private function ExcProgram($Menu)
    {
        $Exit = false;
        $this->_ARResults['Jump'] = 0;
        $this->CurrentMenu = $Menu;
        while (!$Exit) {
            foreach ($this->CurrentMenu["Items"] as $Item) {
                $this->_logger->debug("Process " . $Item->Caption, ["ExcProgram"]);
                $Result = $this->ExcItem($Item);
                if ($Result->_StatusCode == 9) {
                    $this->_logger->debug("Jump Signal Received! Next PRG:" . $this->_ARResults['Next'],
                        ["ExcProgram"]);
                    $this->_ARResults['Jump'] = 1;
                    break;
                } elseif ($Result->_Error) {
                    $this->_logger->debug("Exit Signal Received!", ["ExcProgram"]);
                    $Exit = true;
                    break;
                }
            }
            if ($this->_ARResults['Jump'] == 1) {
                $this->_logger->debug("Set Jump Program To " . $this->_ARResults['Next'], ["ExcProgram"]);
                $this->_ARResults['previous'] = $Menu->Id;
                $new = $this->GetMenu($this->_ARResults['Next']);
                if (!$new->_Error) {
                    $this->_logger->notice("program: " . $this->_ARResults['Next'] . " loaded", ["ExcProgram"]);
                    $this->CurrentMenu = $new->_Result;
                    $this->_ARResults['Jump'] = 0;
                } else {
                    $Exit = true;
                }
            } else {
                $Exit = true;
            }
        }
    }

    private function ExcItem($Item)
    {
        $this->_logger->info("Item " . $Item->Caption, ["ExcItem"]);

        switch ($Item->ItemType) {
            case 1:
                return $this->PlayBack($Item);
            case 2:
                return $this->TwoTo($Item);
            case 3:
                return $this->ThreeTo($Item);
            case 4:
                return $this->FiveTo($Item);
            case 5:
                return $this->NumericalTo($Item);
            case 6:
                return $this->Record($Item);
            case 7:
                return $this->Transfer($Item);
            case 8:
                return $this->JumpToProgram($Item);
            case 9:
                return $this->Hangup($Item);
            case 10:
                return $this->SelectMenu($Item);
            case 11:
                return $this->ItemFeedBack($Item, 0);
            case 12:
                return $this->SelectDoctor();
            case 13:
                return $this->ArrayChoice($Item);
            case 14:
                return $this->CheckMelliCodeAccuracy($Item);
            case 15:
                return $this->Reservation();
            case 16:
                return $this->CheckSelectedClinic();
            case 17:
                return $this->CheckClinicShiftState();
            case 18:
                return $this->TimeCondition($Item);
        }
        return OperationResult::ReturnErrorResult();
    }

    private function PlayBack($Item)
    {
        $this->_logger->debug("Play: " . $Item->Caption, ["PlayBack"]);
        $this->Agi->exec('Playback', $Item->SoundFile);
        return OperationResult::ReturnSuccessResult(true);
    }

    private function TwoTo($Item)
    {
        $this->_logger->debug("2 Option Choice:" . $Item->Caption . $Item->Caption, ["TwoTo"]);
        $Data = $this->SaveChooseResult($Item, 2);
        return OperationResult::ReturnSuccessResult($Data->_Result, "", 0);
    }

    private function ThreeTo($Item)
    {
        $this->_logger->debug("3 Option Choice:" . $Item->Caption, ["ThreeTo"]);
        $Data = $this->SaveChooseResult($Item, 3);
        return OperationResult::ReturnSuccessResult($Data->_Result, "", 0);
    }

    private function FiveTo($Item)
    {
        $this->_logger->debug("5 Option Choice:" . $Item->Caption, ["FiveTo"]);
        $Data = $this->SaveChooseResult($Item, 5);
        return OperationResult::ReturnSuccessResult($Data->_Result, "", 0);
    }

    private function ArrayChoice($Item)
    {
        $this->_logger->debug("Array Option Choice:" . $Item->Caption, ["ArrayChoice"]);
        $Data = $this->SaveChooseResult($Item, Count($this->_ARResults[$Item->Options]));
        return OperationResult::ReturnSuccessResult($Data->_Result, "", 0);

    }

    private function NumericalTo($Item)
    {
        $this->_logger->debug("Get Numerical Value For:" . $Item->Caption, ["ArrayChoice"]);
        return $this->CheckNumericalValue($Item);

    }

    private function choose($Item, $ChoiceCount, $Nullable, $NRetries)
    {
        $this->_logger->debug("1) " . $Item->Caption, ["Chose"]);
        $this->_logger->debug("2) Playback " . $Item->SoundFile . "...", ["Chose"]);
        $Reject = false;
        $Retries = 1;
        if ($ChoiceCount < 10)
            $Digits = 1;
        elseif ($ChoiceCount < 100)
            $Digits = 2;
        elseif ($ChoiceCount < 1000)
            $Digits = 3;
        while (!$Reject) {
            $this->_logger->debug("Get Data With: No Retries=" . $NRetries . " And Retries=" . $Retries, ["Chose"]);
            $GData = $this->GetData($Item->SoundFile, $Digits, 3000, $NRetries, $Retries);
            if (!$GData->_TimeOut) {
                $this->_logger->debug("Data Received:" . $GData->_Result, ["Chose"]);
                $Retries = $GData->_MSG;
                $this->_logger->debug("Retries Increased To:" . $Retries, ["Chose"]);
                $CHResult = $this->CheckChooseResult($GData, $Nullable, 1, $ChoiceCount);
                if ($CHResult->_Error) {
                    if ($Retries >= $NRetries) {
                        $this->_logger->debug("Retries Exceeded", ["Chose"]);
                        $this->PlayMessage($this->SystemPlay("sys_RetriesExceded"), "Play: RetryTimeExceeded ", "Result Validation");
                        return OperationResult::ReturnErrorResult();
                    } else {
                        $Retries++;
                        $this->_logger->debug("Retries Increased To:" . $Retries, ["Chose"]);
                    }
                } else {
                    $this->_logger->debug("Data Received Successfully", ["Chose"]);
                    return OperationResult::ReturnSuccessResult($GData->_Result);
                }
            } else {
                $this->_logger->debug("Time Out", ["Chose"]);
                $this->PlayMessage($this->SystemPlay("sys_RetriesExcceded"), "Play: RetryTimeExceeded ", "Result Validation");
                return OperationResult::ReturnErrorResult("RetryTimeExceeded");
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function CheckChooseResult($Data, $Nullable, $Min, $Max)
    {
        $this->_logger->debug("Validate Result...", ["CheckChoseResult"]);
        if ($Data->_Result != '') {
            $this->_logger->debug("Result is Not Null", ["CheckChoseResult"]);
            if ($Data->_Result >= $Min && $Data->_Result <= $Max) {
                $this->_logger->debug("Result Accepted", ["CheckChoseResult"]);
                return OperationResult::ReturnSuccessResult($Data->_Result);
            } else {
                $this->_logger->debug("Result Rejected" . "(Out Of Range)", ["CheckChoseResult"]);
                $this->PlayMessage($this->SystemPlay("sys_InvalidNumber"), "Input Data Is Not Valid !", "Out Of Range");
                $r = OperationResult::ReturnErrorResult("Result Rejected" . "(Out Of Range)");
                $r->_StatusCode = -1;
                return $r;
            }
        } elseif ($Nullable) {
            $this->_logger->debug("Result Accepted -- NULL Result", ["CheckChoseResult"]);
            return OperationResult::ReturnSuccessResult('');
        } else {
            $this->_logger->debug("Result Rejected -- NULL", ["CheckChoseResult"]);
            $this->PlayMessage($this->SystemPlay("sys_InvalidNumber"), "Input Data Is Not Valid !", "NULL Value");
            $r = OperationResult::ReturnErrorResult("Result Rejected" . "(Out Of Range)");
            $r->_StatusCode = -2;
            return $r;
        }
    }

    private function SaveChooseResult($Item, $Range)
    {
        $Data = $this->choose($Item, $Range, true, 3);
        if (!$Data->_Error) {
            $this->_logger->error("Receive :" . $Data->_Result . "For:" . $Item->Caption, ["SaveChooseResult"]);
            $this->ItemFeedBack($Item, $Data->_Result);
        } elseif ($Data->_TimeOut) {
            $this->_logger->error("Receive :" . $Data->_Result . "For:" . $Item->Caption . "TimeOut",
                ["SaveChooseResult"]);
            $this->ItemFeedBack($Item, $Data->_Result);
        } else {
            $this->_logger->error("Error in Save Result !!!" . $Item->Caption, ["SaveChooseResult"]);
        }
        return $Data;
    }

    private function GetData($SoundFile, $MaxDigit, $Timeout = 10000, $Retries = 3, $InitRetry = 0)
    {
        $RetryCount = $InitRetry;
        $this->_logger->debug("Get Data Initiated With No Retries=" . $Retries . " And RetryCount=" . $InitRetry,
            ["GetData"]);

        while ($RetryCount <= $Retries) {
            $this->_logger->debug("Playback :" . $SoundFile . " And Wait For Answer...", ["GetData"]);
            $PGResult = $this->PlayAndGet($SoundFile, $Timeout, $MaxDigit);

            if ($PGResult->_TimeOut) {
                $this->_logger->debug("Retries:" . $RetryCount . " Of " . $Retries, ["GetData"]);

                if ($RetryCount >= $Retries) {
                    $this->_logger->debug("Retries Exceeded", ["GetData"]);
                    return OperationResult::ReturnTimeOutErrorResult($RetryCount);
                } else {
                    $RetryCount++;
                    $this->_logger->debug("Retries Increased To:" . $RetryCount, ["GetData"]);
                }
            } else {
                $this->_logger->debug("Data Received Successfully", ["GetData"]);
                return OperationResult::ReturnSuccessResult($PGResult->_Result, $RetryCount);
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function CheckNumericalValue($Item)
    {
        $this->_logger->debug("Get Numerical Value For: " . $Item->Caption, ["CheckNumericalValue"]);
        $Data = $this->PlayAndGet($Item->SoundFile, 5000, $Item->Options);
        if (!$Data->_Error) {
            $this->_logger->debug("Data Received", ["CheckNumericalValue"]);
            $Data = $this->ItemFeedBack($Item, $Data->_Result);
        } else {
            $this->_logger->error("Error In Get Data", ["CheckNumericalValue"]);
        }
        return OperationResult::ReturnResult($Data);
    }

    private function VariableItemsChoose($SoundFiles, $ChoiseCount, $Nullable, $NRetries)
    {
        $this->_logger->debug("1) No Of Coices:" . $ChoiseCount, ["VariableItemsChoose"]);
        $this->_logger->debug("2) Playback Sound Files", ["VariableItemsChoose"]);
        $Reject = false;
        $Retries = 1;
        while (!$Reject) {
            $this->_logger->debug("Get Data With: No Retries=" . $NRetries . " And Retries=" . $Retries, ["VariableItemsChoose"]);
            $GData = $this->PlayAndGetMulti($SoundFiles, 1, 3000, $NRetries, $Retries);
            if (!$GData->_TimeOut) {
                $this->_logger->debug("Data Recived:" . $GData->_Result, ["VariableItemsChoose"]);
                $Retries = $GData->_MSG;
                $this->_logger->debug("Retries Increased To:" . $Retries, ["VariableItemsChoose"]);
                $CHResult = $this->CheckChooseResult($GData, $Nullable, 1, $ChoiseCount);
                if ($CHResult->_Error) {
                    if ($Retries >= $NRetries) {
                        $this->_logger->debug("Retries Exceeded", ["VariableItemsChoose"]);
                        $this->PlayMessage($this->SystemPlay("sys_RetriesExcceded"), "Play: RertyTimeExceeded ", "Result Validation");
                        return OperationResult::ReturnErrorResult("Retries Exceeded");
                    } else {
                        $Retries++;
                        $this->_logger->debug("Retries Increased To:" . $Retries, ["VariableItemsChoose"]);
                    }
                } else {
                    $this->_logger->debug("Data Received Successfully", ["VariableItemsChoose"]);
                    return OperationResult::ReturnSuccessResult($GData->_Result, "Retries Exceeded");
                }
            } else {
                $this->_logger->debug("Time Out", ["VariableItemsChoose"]);
                $this->PlayMessage($this->SystemPlay("sys_RetriesExcceded"), "Play: RertyTimeExceeded ", "Result Validation");
                return OperationResult::ReturnTimeOutErrorResult("Time Out");
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function PlayAndGetMulti($SoundFiles, $MaxDigit, $Timeout = 10000, $Retries = 3, $InitRetry = 0)
    {
        $RetryCount = $InitRetry;
        $this->_logger->debug("Get Data Initiated With No Retries=" . $Retries . " And RetryCount=" . $InitRetry
            , ["PlayAndGetMultiFiles"]);

        while ($RetryCount <= $Retries) {
            $this->_logger->debug("Playback Files And Wait For Answer...", ["PlayAndGetMultiFiles"]);
            $PGResult = $this->PlayAndGetMultiFiles($SoundFiles, $Timeout, $MaxDigit);

            if ($PGResult->_TimeOut) {
                $this->_logger->debug("Retries:" . $RetryCount . " Of " . $Retries, ["PlayAndGetMultiFiles"]);
                if ($RetryCount >= $Retries) {
                    $this->_logger->debug("Retries Exceeded", ["PlayAndGetMultiFiles"]);
                    return OperationResult::ReturnErrorResult($RetryCount);
                } else {
                    $RetryCount++;
                    $this->_logger->debug("Retries Increased To:" . $RetryCount, ["PlayAndGetMultiFiles"]);

                }
            } else {
                $this->_logger->debug("Data Received Successfully", ["PlayAndGetMultiFiles"]);
                return OperationResult::ReturnSuccessResult($PGResult->_Result, $RetryCount);
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function PlayAndGetMultiFiles($SoundFiles, $Timeout = 5000, $MaxDigit = 1, $Nullable = false, $PlayError = false)
    {
        $buff = "";
        $end = end($SoundFiles);
        foreach ($SoundFiles as $SoundFile) {
            if ($end == $SoundFile) {
                $tout = $Timeout;
                $IsLast = true;
                $this->_logger->debug("Last Chance For Choice an Item TimeOut:" . $tout, ["PlayAndGetMultiFiles"]);
            } else {
                $tout = 5;
                $IsLast = false;
            }
            $this->_logger->debug("Play Sound Items" . $SoundFile . " TimeOut:" . $tout, ["PlayAndGetMultiFiles"]);
            $res = $this->Agi->fastpass_get_data($buff, $SoundFile, $tout, $MaxDigit);
            $result = $res['result'];
            $timeout = $res['data'];

            if ($timeout != "timeout" && $result != '') {
                $this->_logger->debug("Result:" . $result, ["PlayAndGetMultiFiles"]);
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout != "timeout" && $result == '' && !$Nullable) {
                $this->OnTimeOut($this->SystemPlay("sys_nodigitreceived"), "PlayAndGetMultiFiles",
                    $this->_ProgramId);
                return OperationResult::ReturnErrorResult($result);
            } elseif ($timeout != "timeout" && $result == '' && $Nullable) {
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout == "timeout" && $result == '' && $IsLast && $Nullable) {
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout == "timeout" && $result == '' && $IsLast && !$Nullable) {
                $this->OnTimeOut($this->SystemPlay("sys_nodigitreceived"), "PlayAndGetMultiFiles", $this->_ProgramId);
                return OperationResult::ReturnTimeOutErrorResult($result);
            } elseif ($timeout == "timeout" && $result != '' && $IsLast) {
                return OperationResult::ReturnSuccessResult($result);
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function PlayAndGet($SoundFile, $Timeout = 5000, $MaxDigit = 1, $Nullable = false)
    {
        $buff = "";
        $sounds = explode(",", $SoundFile);
        $end = end($sounds);
        foreach ($sounds as $soundfile) {
            $this->_logger->debug("Play Sound File:" . $soundfile, ["PlayAndGet"]);

            if ($end == $soundfile) {
                $tout = $Timeout;
                $IsLast = true;
                $this->_logger->debug("Last Chance For Choice an Item", ["PlayAndGet"]);
            } else {
                $tout = 5;
                $IsLast = false;
            }

            $res = $this->Agi->fastpass_get_data($buff, $soundfile, $tout, $MaxDigit);
            $result = $res['result'];
            $timeout = $res['data'];

            if ($timeout != "timeout" && $result != '') {
                $this->_logger->debug("Result", ["PlayAndGet"]);
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout != "timeout" && $result == '' && !$Nullable) {
                $this->OnTimeOut($this->SystemPlay("sys_nodigitreceived"), "PlayAndGet", $this->_ProgramId);
                return OperationResult::ReturnErrorResult($result);
            } elseif ($timeout != "timeout" && $result == '' && $Nullable) {
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout == "timeout" && $result == '' && $IsLast && $Nullable) {
                return OperationResult::ReturnSuccessResult($result);
            } elseif ($timeout == "timeout" && $result == '' && $IsLast && !$Nullable) {
                $this->OnTimeOut($this->SystemPlay("sys_nodigitreceived"), "PlayAndGet", $this->_ProgramId);
                return OperationResult::ReturnTimeOutErrorResult($result);
            } elseif ($timeout == "timeout" && $result != '' && $IsLast) {
                return OperationResult::ReturnSuccessResult($result);
            }
        }
        return OperationResult::ReturnErrorResult();
    }

    private function Record($Item)
    {
        $RecordFile = '/var/spool/asterisk/Survey/Recordings/' . "-" . $Item->Id;
        $this->PlayMessage($Item->SoundFile, " Play Recording Message :", "Record");
        $this->Agi->record_file($RecordFile, 'wav', '#', -1, null, true);
        return OperationResult::ReturnSuccessResult($RecordFile);
    }

    private function Transfer($Item)
    {
        $this->Agi->exec('DIAL', $Item->Options);
        return OperationResult::ReturnSuccessResult($Item->Options);
    }

    private function Hangup($Item)
    {

        $this->_logger->debug("Hangup: " . $Item->Caption, ["Hangup"]);
        $this->Agi->hangup();
        return OperationResult::ReturnSuccessResult(0, "HangUP" . $Item->Options);

    }

    private function SelectMenu($Item)
    {

        $this->_logger->debug("Play Menu:" . $Item->Caption . "({ $Item->SoundFile})", ["SelectMenu"]);

        $Data = $this->choose($Item, $Item->Options, false, 3);

        if (!$Data->_Error) {
            $this->_logger->debug("Receive :" . $Data->_Result . " For:" . $Item->Caption .
                " Output is:" . $Item->Output, ["SelectMenu"]);
            if ($Item->Output > 0) {
                $this->_logger->debug($Item->Data . " is set to:" . $Data->_Result,["SelectMenu"]);
                $this->AddDataARR($Item->Data, $Data->_Result);
                return OperationResult::ReturnSuccessResult($Data, $Item->Data . " is set to:" . $Data->_Result);
            }
            if ($Item->Output == 0) {
                $this->_logger->debug("Next Program Id is:".$this->CurrentMenu["Values"][$Data->_Result]->Value);
                $this->_ARResults['Next'] = $this->CurrentMenu["Values"][$Data->_Result]->Value;
                $this->_logger->debug("Jump To Next Program Set To:" . $this->_ARResults['Next'],
                    ["SelectMenu"]);
                return OperationResult::ReturnSuccessResult($Data, "Jump To:" . $this->_ARResults['Next'],9);
            }
        } else {
            $this->_logger->debug(" Choice Error !!!" . $Item->Caption, ["SelectMenu"]);
            return OperationResult::ReturnErrorResult(" Choice Error !!!");
        }
        return OperationResult::ReturnErrorResult(" Choice Error !!!");
    }

    private function JumpToProgram($Item)
    {
        $this->_logger->info("Set Jump To Program For:" . $Item->Caption, ["JumpToProgram"]);
        $this->_ARResults['Next'] = $Item->Data;
        return OperationResult::ReturnSuccessResult($Item->Data, "Jump To:" . $this->_ARResults['Next'], 9);
    }


    /*
     *
     *
     * Service Functions
     *
     *
     */

    public function CheckSelectedClinic()
    {
        $this->_logger->debug("CheckClinicStatus...", ["CheckSelectedClinic"]);
        $ClinicState = $this->CheckClinicStatus();

        if ($ClinicState->_Error) {
            $this->_logger->error("Get Clinic State Result Error!!!", ["CheckSelectedClinic"]);
            return OperationResult::ReturnErrorResult();
        }
        $this->_logger->info("Clinic Name Is:" . $this->_ARResults['ClinicName'], ["CheckSelectedClinic"]);
        return OperationResult::ReturnSuccessResult(1);
    }

    public function SelectDoctor()
    {
        $this->_logger->debug("Start SelectDoctor", ["SelectDoctor"]);
        $this->_logger->debug("Clinic:" . $this->_ARResults['ClinicName'] . " Status:" .
            $this->_ARResults['ClinicStatus'], ["SelectDoctor"]);
        switch ($this->_ARResults['ClinicStatus']) {
            case 1:
                return $this->OnManualDoctorSelection();
            case 2:
                return $this->OnAutoDoctorSelection();
            case 3:
                return $this->ReservationError("sys_in-personOnly", "Clinic reservation is in-person");
        }
        return OperationResult::ReturnErrorResult("StatusCode Out Of Range - " . $this->_ARResults['ClinicStatus']);
    }

    private function CheckClinicStatus()
    {
        $this->_logger->debug("Check Clinic State For :" . $this->_ARResults["Clinic"], ['CheckClinicStatus']);
        $ClinicState = $this->Api->ClinicStatus($this->_ARResults["Clinic"]);

        if ($ClinicState->_Error) {
            $this->_logger->error("Error In Get Clinic State", ['CheckClinicStatus']);
            return OperationResult::ReturnErrorResult("Error In Get Clinic State");
        }
        $Res = $ClinicState->_Result[0];
        switch ($Res->available) {
            case 0:
                $this->_logger->debug("Clinic Is Not Active!", ['CheckClinicStatus']);
                return $this->ReservationError("sys_clinicnotactive", "Clinic Is Not Active!");
            case 3:
                $this->_logger->debug("Reservation type is in-person Only", ['CheckClinicStatus']);
                return $this->ReservationError("sys_in-personOnly", "Clinic reservation is in-person");
        }


        $this->_logger->notice("ADD Clinic Info ---> Name:" . $Res->clinicName . " Status:" . $Res->available,
            ['CheckClinicStatus']);
        $this->AddDataARR("ClinicStatus", $Res->available);
        $this->AddDataARR("ClinicName", $Res->clinicName);
        $this->AddDataARR("durationTime", $Res->durationTime);
        $this->AddDataARR("ClinicShift1", $Res->shift1);
        $this->AddDataARR("ClinicShift2", $Res->shift2);
        $this->AddDataARR("ClinicShift3", $Res->shift3);
        return OperationResult::ReturnSuccessResult($Res->available);
    }

    private function CheckClinicShiftState()
    {
        $this->_logger->debug("Check Clinic Shift State For :" . $this->_ARResults["Clinic"], ["CheckClinicShiftState"]);
        $shift = "ClinicShift" . $this->_ARResults['Shift'];

        $this->_logger->debug("Shift is set to :" . $shift, ["CheckClinicShiftState"]);

        if ($this->_ARResults[$shift] == 0) {
            $this->_logger->debug("Shift " . $this->_ARResults['Shift'] . " is deactivated!", ["CheckClinicShiftState"]);
            return $this->ReservationError("sys_shiftIsNotActive", "Clinic Shift Is Not Active!");
        }
        return OperationResult::ReturnSuccessResult(true);
    }

    public function GetClinicProgram()
    {
        $this->_logger->debug("Get Capacities For Clinic:" . $this->_ARResults['Clinic'] .
            " Day:" . $this->_ARResults['Day'] .
            " Shift:" . $this->_ARResults['Shift'], ["GetClinicProgram"]);
        $RetData = $this->Api->ClinicProgram($this->_ARResults['Clinic'], $this->_ARResults['Day'] - 1,
            $this->_ARResults['Shift']);
        $this->_logger->debug("Data Received With Size:" . count($RetData->_Result), ["GetClinicProgram"]);
        return $RetData;
    }

    private function ChoiceDoctor($GetClinicResult)
    {
        $this->_logger->debug("Choice Doctor...", ["ChoiceDoctor"]);
        $List = $this->CreateDoctorList($GetClinicResult)->_Result;
        if (count($List) == 0) {
            return $this->ReservationError("sys_drhasnotshift", "Clinic has not Capacity in this Shift And Day!");
        }

        $RPlayList = $this->CreatePlayList($List);
        $Doctor = $this->VariableItemsChoose($RPlayList->_Result, $RPlayList->_StatusCode, false, 3);
        if ($Doctor->_Error)
            return OperationResult::ReturnErrorResult();
        $this->_logger->debug($List[$Doctor->_Result - 1]->DoctorCode . " Is selected ", ["ChoiceDoctor"]);
        return OperationResult::ReturnSuccessResult($List[$Doctor->_Result - 1], $Doctor->_Result);
    }

    private function CreateDoctorList($GetClinicResult)
    {
        $ClinicDrArrays = array();
        foreach ($GetClinicResult->_Result as $Itm) {
            if ($Itm->Capacity > 0)
                array_push($ClinicDrArrays, $Itm);
        }
        return OperationResult::ReturnSuccessResult($ClinicDrArrays);
    }

    private function CalculateCapacity($GetClinicResult)
    {
        $Capacity = 0;
        foreach ($GetClinicResult->_Result as $Itm) {
            if ($Itm->RemainCapacity > 0)
                $Capacity += $Itm->RemainCapacity;
        }
        $this->_logger->debug("Available Capacity:" . $Capacity, ["CalculateCapacity"]);

        return OperationResult::ReturnSuccessResult($Capacity);
    }

    private function OnAutoDoctorSelection()
    {
        $this->_logger->debug("Doctor Will Be Selected Automatically", ["OnAutoDoctorSelection"]);

        $ClinicProgramResult = $this->GetClinicProgram();

        if ($ClinicProgramResult->_Error) {
            $this->_logger->error("Get Clinic Error Result!!!", ["OnAutoDoctorSelection"]);
            return $ClinicProgramResult;
        }

        if (count($ClinicProgramResult->_Result) < 1) {
            return $this->ReservationError("sys_drhasnotshift", "Doctor  has not any  Capacity!");
        }

        $Capacity = $this->CalculateCapacity($ClinicProgramResult)->_Result;
        $this->_logger->debug("Remain Capacity For " . $this->_ARResults['ClinicName'], ["CalculateCapacity"]);
        if ($Capacity < 1) {
            return $this->ReservationError("sys_capacityisfull", "Clinic has not Capacity!");
        }

        return OperationResult::ReturnSuccessResult("");
    }

    private function OnManualDoctorSelection()
    {
        $this->_logger->debug("Start Manual Doctor Selection", ["OnManualDoctorSelection"]);
        $ClinicProgramResult = $this->GetClinicProgram();

        if ($ClinicProgramResult->_Error) {
            $this->_logger->error("Get Clinic Error Result!!!", ["OnManualDoctorSelection"]);
            return $ClinicProgramResult;
        }

        $Drr = $this->ChoiceDoctor($ClinicProgramResult);
        if ($Drr->_Error) {
            $this->_logger->error("Error In Select Doctor", ["OnManualDoctorSelection"]);
            return OperationResult::ReturnErrorResult();
        }

        $DrCap = $this->CheckDrCapacity($Drr->_Result);
        if ($DrCap->_Error) {
            return OperationResult::ReturnErrorResult();
        }

        $this->_logger->error("Save Doctor Code:" . $Drr->_Result->DoctorCode, ["OnManualDoctorSelection"]);
        $this->AddDataARR("Doctor", $Drr->_Result->DoctorCode);

        return OperationResult::ReturnSuccessResult("Doctor", $Drr->_Result->DoctorCode);
    }

    private function CreatePlayList($List)
    {
        $cnt = count($List);
        $this->_logger->debug("List Count:" . $cnt, ["CreatePlayList"]);
        $id = 1;
        $SoundFiles = array();
        foreach ($List as $item) {
            $Sound = "whisper/Doctors/" . $item->DoctorCode;
            $NoSound = "whisper/Digit/" . $id;
            array_push($SoundFiles, $Sound);
            $this->_logger->debug("SoundFile:" . $Sound, ["CreatePlayList"]);
            array_push($SoundFiles, $NoSound);
            $this->_logger->debug("Digit Sound File:" . $NoSound, ["CreatePlayList"]);
            $id++;
        }
        return OperationResult::ReturnSuccessResult($SoundFiles, $cnt);
    }

    private function CheckDrCapacity($Programs)
    {
        $this->_logger->debug("Check Dr Capacity., ID:" . $Programs->DoctorCode .
            " Remain:" . $Programs->RemainCapacity, ["CheckDrCapacity"]);

        if ($Programs->RemainCapacity < 1) {
            $this->PlayMessage($this->SystemPlay("sys-drcapacityfull"), "Dr Capacity Is Full!", ["CheckDrCapacity"]);
            return OperationResult::ReturnErrorResult();
        } else {
            $this->_logger->debug("Dr has " . $Programs->RemainCapacity . " Remain Capacity", ["CheckDrCapacity"]);
            $TResult = $this->CheckReservationTimes($Programs);
            if ($TResult->_Error)
                return OperationResult::ReturnErrorResult("");
            return OperationResult::ReturnSuccessResult($Programs->RemainCapacity);
        }

    }

    private function CheckReservationTimes($Programs)
    {
        $this->_logger->debug("Start Checking Reservation Times For Clinic:" . $this->_ARResults["Clinic"] .
            " Doctor:" . $Programs->DoctorCode . " Shift:" . $this->_ARResults["Shift"] .
            " Day:" . $this->_ARResults["Day"] .
            " Duration Time: " . $this->_ARResults["durationTime"], ["CheckReservationTimes"]);

        $Result = $this->Api->CheckReservationTime($this->_ARResults["Clinic"], $this->_ARResults["Day"] - 1,
            $this->_ARResults["Shift"], $Programs->DoctorCode);

        if ($Result->_Error) {
            $this->_logger->error("Error In Api -> CheckReservationTime", ["CheckReservationTimes"]);
        } else {
            $this->_logger->notice(" Status Code is:" . $Result->_StatusCode, ["CheckReservationTimes"]);
            switch ($Result->_StatusCode) {
                case 200:
                    return OperationResult::ReturnSuccessResult($Result->_Result[0], $Result->_MSG);
                case 412:
                    return $this->ReservationError("sys_ShiftTimeout", "Shift Timeout!!!");
                case 406:
                    return $this->ReservationError("sys-drcapacityfull", "Dr Capacity is Full!");
                case 413:
                    return $this->ReservationError("sys-drcapacityfull", "Time Overflow!");
                default:
                    return $this->ReservationError("sys-drcapacityfull", "Unexpected Error!!!!");
            }
        }
        return $this->ReservationError("sys-drcapacityfull", "Unexpected Error!!!!");
    }

    private function CheckMelliCodeAccuracy($Item)
    {

        $this->_logger->notice("Check National code: $Item->Caption", ["CheckMelliCodeAccuracy"]);
        $NRetries = 1;
        while ($NRetries < 4) {
            $this->_logger->debug("Retries:" . $NRetries . " of 3", ["CheckMelliCodeAccuracy"]);
            $CHR = $this->CheckNumericalValue($Item);
            if ($CHR->_TimeOut) {
                $this->_logger->debug("Time Out Error!!!", ["CheckMelliCodeAccuracy"]);
                return OperationResult::ReturnTimeOutErrorResult("Time Out Error!!!");
            }
            if (array_key_exists("MelliCode", $this->_ARResults)) {
                $this->_logger->debug("Code is:" . $this->_ARResults["MelliCode"], ["CheckMelliCodeAccuracy"]);
                $pr = $this->CheckNationalCode($this->_ARResults["MelliCode"]);
                if (!$pr->_Error) {
                    $this->_logger->debug("MelliCode Is Correct", ["CheckMelliCodeAccuracy"]);
                    return OperationResult::ReturnSuccessResult(1);
                } else {
                    $this->_logger->debug("MelliCode Is Not Correct", ["CheckMelliCodeAccuracy"]);
                    $this->PlayMessage($this->SystemPlay("sys_wrongmellicode"), "Mellicode Is Not Correct");
                }
            } else {
                $this->_logger->debug("MelliCode Does not exist!!!", ["CheckMelliCodeAccuracy"]);
                $this->PlayMessage($this->SystemPlay("sys_wrongmellicode"), "Mellicode Is Not Correct");
                return OperationResult::ReturnErrorResult();
            }
            $NRetries++;
        }

        $this->_logger->debug("Retries Exceeded", ["CheckMelliCodeAccuracy"]);
        $this->PlayMessage($this->SystemPlay("sys_RetriesExcceded"), "Play: RertyTimeExceeded ", "Result Validation");
        return OperationResult::ReturnErrorResult();
    }

    public function CheckNationalCode($code)
    {
        if (!preg_match('/^[0-9]{10}$/', $code))
            return OperationResult::ReturnErrorResult("Pattern miss match");
        for ($i = 0; $i < 10; $i++)
            if (preg_match('/^' . $i . '{10}$/', $code))
                return OperationResult::ReturnErrorResult("Pattern miss match");;
        for ($i = 0, $sum = 0; $i < 9; $i++)
            $sum += ((10 - $i) * intval(substr($code, $i, 1)));
        $ret = $sum % 11;
        $parity = intval(substr($code, 9, 1));
        if (($ret < 2 && $ret == $parity) || ($ret >= 2 && $ret == 11 - $parity))
            return OperationResult::ReturnSuccessResult(true, "Matched");
        return OperationResult::ReturnErrorResult("Code miss match");
    }

    private function Reservation()
    {
        $this->_logger->debug("Start Reservation", ["Reservation"]);
        $this->_logger->notice("Reservation: Clinic:" . $this->_ARResults["Clinic"] .
            (" Day:" . ($this->_ARResults["Day"] - 1)) .
            " Shift:" . $this->_ARResults["Shift"] .
            " Doctor:" . $this->_ARResults["Doctor"] .
            " National Code:" . $this->_ARResults["MelliCode"] .
            " Phone:" . $this->_ARResults["Phonenumber"], ["Reservation"]);
        switch ($this->_ARResults['ClinicStatus']) {
            case 1:
                $this->_logger->notice("Manual Reservation is selected", ["Reservation"]);
                $pr = $this->Api->Reservation($this->_ARResults["Clinic"], $this->_ARResults["Day"] - 1,
                    $this->_ARResults["Shift"], $this->_ARResults["Doctor"],
                    $this->_ARResults["MelliCode"], $this->_ARResults["Phonenumber"]);
                break;
            case 2:
                $this->_logger->notice("Auto Reservation selected", ["Reservation"]);
                $pr = $this->Api->AutoReservation($this->_ARResults["Clinic"], $this->_ARResults["Day"] - 1,
                    $this->_ARResults["Shift"], $this->_ARResults["MelliCode"],
                    $this->_ARResults["Phonenumber"]);
                break;
        }

        if ($this->HandleReservationError($pr)->_Error) {
            return OperationResult::ReturnErrorResult();
        } else {
            return $this->ReservationAnnouncement($pr->_Result[0]);
        }
    }

    private function HandleReservationError($Result)
    {
        $this->_logger->debug("Handle Errors:...", ["HandleReservationError"]);

        if ($Result->_Error) {
            $this->_logger->error("Error In Reservation - Status Code is:" . $Result->_StatusCode, ["HandleReservationError"]);
        } else {
            $this->_logger->debug("Check The Status! " . " Status Code is:" . $Result->_StatusCode, ["HandleReservationError"]);
            switch ($Result->_StatusCode) {
                case 200:
                    $this->SaveReservationInformation($Result);
                    return OperationResult::ReturnSuccessResult(0);
                case 403:
                    return $this->ReservationError("sys-pationthascapacity", "Pationt has another reservation");
                case 404:
                    return $this->ReservationError("sys_drhasnotshift", "The doctor has not any capacity in this shift");
                case 406:
                    return $this->ReservationError("sys-drcapacityfull", "Dr Capacity is Full!");
                case 412:
                    return $this->ReservationError("sys_ShiftTimeout", "Shift Time Out");
                default:
                    return $this->ReservationError("sys_error", "The doctor has not any capacity in this shift");
            }
        }
        return $this->ReservationError("sys_error", "The doctor has not any capacity in this shift");
    }

    private function ReservationError($SoundFile, $Message)
    {
        $this->_logger->debug("Reservation Error: " . $Message, ["ReservationError"]);
        $this->PlayMessage($this->SystemPlay($SoundFile), $Message, "ReservationError");
        return OperationResult::ReturnErrorResult($Message);
    }

    private function SaveReservationInformation($Result)
    {
        $this->_logger->notice("INFO: Clinic:" . $Result->_Result->ClinicCode .
            " Visit Date:" . $Result->_Result->date_nobat .
            " MelliCode:" . $Result->_Result->bim_cod,
            ["SaveReservationInformation"]);
        //$this->_DBEng->AddNewReservation($Result->_Result);
    }

    private function ReservationAnnouncement($Data)
    {
        $this->_Narrator->SayReservationInformation($Data);
        return OperationResult::ReturnSuccessResult(1, '1');
    }

    private function TimeCondition($Item)
    {

        $this->_logger->debug("Check Time Condition For:" . $Item->Caption, ["TimeCondition"]);

        $TimeOptions = explode(",", $Item->Options);
        $StartTimeAr = explode(":", $TimeOptions[0]);
        $FinishTimeAr = explode(":", $TimeOptions[1]);
        $currentTime = gettimeofday(true);
        $StartTime = mktime($StartTimeAr[0], $StartTimeAr[1], 0);
        $FinishTime = mktime($FinishTimeAr[0], $FinishTimeAr[1], 0);

        $this->_logger->debug("Start Time is:" . $TimeOptions[0] . " Finish Time is:" . $TimeOptions[1],
            ["TimeCondition"]);

        if (($currentTime > $StartTime) && ($currentTime < $FinishTime)) {

            $this->_logger->debug("Time Is Matched!!", ["TimeCondition"]);
            return OperationResult::ReturnSuccessResult($currentTime);

        } else {

            $this->_logger->debug("Time Is Miss Matched!!", ["TimeCondition"]);
            $this->PlayMessage($Item->SoundFile, "Time Miss Match", ["TimeCondition"]);
            return OperationResult::ReturnErrorResult("Time Miss Matched!");
        }
    }

    /*
     *
     *
     *      End Of Service Functions
     *
     */

    private function ItemFeedBack($Item, $Result = "")
    {
        $this->_logger->debug("Output For:" . $Item->Caption . " is:" . $Item->Output, ["ItemFeedBack"]);

        switch ($Item->Output) {
            case 0:
                break;
            case 2:
                $this->AddDataARR($Item->Data, $Result);
                break;
            case 3:
                $this->AddItemResult($Item, $Result);
                $this->AddDataARR($Item->Data, $Result);
                break;
            case 4:
                $this->AddDataARR($Item->Data, $Item->Options);
                break;
            default:
                $this->AddItemResult($Item, $Result);
                break;
        }
        return true;
    }

    private function AddItemResult($Item, $Result)
    {

        $this->_logger->debug("Receive :" . $Result->_Result . "For:" . $Item->Caption, ["AddItemResult"]);
        if ($Result->_Error) {
            if ($Result->_TimeOut) {
                $this->_logger->debug("Timeout Result!!! For:" . $Item->Caption . " Item ID:" . $Item->ItemId,
                    ["AddItemResult"]);
            } else {
                $this->_logger->debug("Null Result!!!" . "For:" . $Item->Caption . " Item ID:" . $Item->Id,
                    ["AddItemResult"]);
            }
        } else {
            $this->_logger->debug("Add " . $Result->_Result . " as Result For:" . $Item->Caption . " Item ID:" . $Item->Id,
                ["AddItemResult"]);
        }

    }

    public function AddDataARR($Data, $Result)
    {
        $this->_logger->debug("Array Item : " . $Data . " is set to " . $Result, ["AddDataARR"]);
        $this->_ARResults[$Data] = $Result;
    }

    private function PlayMessage($SoundFile, $Message, $Context = "")
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

        $this->_logger->debug($this->_ChannelName, ["OnTimeOut"]);
        $this->_logger->debug($this->_ChannelName, ["OnTimeOut"]);
        $this->Agi->exec('playback', $PlayBack);
    }

    private function SystemPlay($Sound)
    {
        return "whisper/system/" . $Sound;
    }
}
