<?php
/**
 *
 *          Whisper Web API Client
 *
 * Date: 07/08/2022
 * Time: 12:30 AM
 *
 *      User: Milad Naderpour
 *
 *  @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 *
 *
 */

namespace Whisper\WebApiClient;
use Exception;
use Monolog\Logger;
use GuzzleHttp\Client;
use Whisper\Model\ClinicProgram;
use Whisper\Model\OperationResult;

class WhisperWebApiClient
{

    private $_ChannelName="Api";

    private $_url,$_logger,$_client,$_ServiceOK,$_api,$_SessionID;

    /**
     * @throws Exception
     */
    function __construct($_url,$logStream) {
        $this->_url = $_url;
        $this->_logger = new Logger('Api');
        $this->_logger->pushHandler($logStream);
        $this->_client = new Client(['defaults' => ['verify' => false]]);
    }

    public function is_Available()
    {
        if($this->_ServiceOK)
            return 1;
        else
            return 0;
    }

    public function TestConnection()
    {
        $this->_logger->addInfo('Api is now ready');
        $res = $this->Get('Check');
        $this->_logger->addInfo('Server Response Succeed is : ' . $res->Succeeded);
        if ($res)
        {
            $this->_ServiceOK = true;
            return $res;
        }
        else $this->_ServiceOK = false;
        return false;
    }

    public function ClinicStatus($ClinicCode)
    {
        $this->_logger->addInfo('Get Clinic'. $ClinicCode . 'Status ... ' );
        $res = $this->Get('Clinics/'.$ClinicCode);
        if ($res)
            return OperationResult::ReturnSuccessResult($res->_Result->result);
        return OperationResult::ReturnErrorResult();
    }

    public function ClinicProgram($ClinicCode,$Day,$Shift)
    {
        $this->_logger->addInfo('Get Schedule for Clinic:'. $ClinicCode . ' Day:'.$Day.' Shift:'.$Shift);
        $res = $this->Get("Schedule/$ClinicCode/$Day/$Shift");
        if ($res)
            return $this->CreatDoctorArray($res->_Result->result);
        return OperationResult::ReturnErrorResult();
    }

    private function CreatDoctorArray($ProgramArray)
    {
        $Items = array();
        if(is_array($ProgramArray))
        {
            $i = 1;
            foreach ($ProgramArray as $item)
            {
                $OBJ=new ClinicProgram();
                $OBJ->SetFromServiceResult($i,$item);
                $Items[$i]=$OBJ;
            }
            return OperationResult::ReturnSuccessResult($Items);
        }
        return OperationResult::ReturnErrorResult();
    }

    public function CheckReservationTime($ClinicCode,$Day,$Shift,$DoctorCode)
    {
        $this->_logger->debug('Get Schedule for Clinic:'. $ClinicCode . ' Day:'.$Day.' Shift:'.$Shift.
                                ' Doctor:'.$DoctorCode);
        $res = $this->Get("Schedule/$ClinicCode/$Day/$Shift/$DoctorCode");
        if ($res)
            return OperationResult::ReturnResult($res->_Result);
        return OperationResult::ReturnErrorResult();

    }

    public function Reservation($ClinicCode,$Day,$Shift,$DoctorCode,$MelliCode,$PhoneNumber)
    {
        $this->_logger->debug("MelliCode:".$MelliCode." Clinic Code:".$ClinicCode." Shift:".$Shift." Day:".$Day.
            " Doctor:".$DoctorCode,["Reservation"]);

        $params= ['clinicCode'=>$ClinicCode,'day'=>$Day,'shift'=>$Shift,'doctorCode'=>$DoctorCode,
            'melliCode'=>$MelliCode,'phoneNumber'=>$PhoneNumber];

        $res = $this->Post("Reservation",$params);
        if ($res)
            return OperationResult::ReturnResult($res->_Result);
        return OperationResult::ReturnErrorResult();
    }

    public function AutoReservation($ClinicCode,$Day,$Shift,$MelliCode,$PhoneNumber)
    {
        $this->_logger->debug("MelliCode:".$MelliCode." Clinic Code:".$ClinicCode." Shift:".$Shift." Day:".$Day.
            " Doctor: auto ",["Reservation"]);

        $params= ['clinicCode'=>$ClinicCode,'day'=>$Day,'shift'=>$Shift,'doctorCode'=>'auto',
            'melliCode'=>$MelliCode,'phoneNumber'=>$PhoneNumber];

        $res = $this->Post("Reservation",$params);
        if ($res)
            return OperationResult::ReturnResult($res->_Result);
        return OperationResult::ReturnErrorResult();
    }


    private function Get($route)
    {
        $this->_logger->info('Get '.$route);
        $res = $this->_client->get($this->_url.'/'.$route);
        if ($res->getStatusCode()==200) {
            $this->_logger->addInfo('Response is OK! for ' . $this->_url.'/'.$route);
            return OperationResult::ReturnSuccessResult( json_decode($res->getBody()));
        }
        return false;
    }

    private function Post($route,$params)
    {
        $this->_logger->info('Post '.$route);
        $res = $this->_client->post($this->_url.'/'.$route,['json' => $params]);
        if ($res->getStatusCode()==200) {
            $this->_logger->addInfo('Response is OK! for ' . $this->_url.'/'.$route);
            return OperationResult::ReturnSuccessResult( json_decode($res->getBody()));
        }
        return false;
    }

}