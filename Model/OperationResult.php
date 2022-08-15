<?php

/**
 *
 * User: Milad Naderpour
 * Date: 27/04/2016
 * Time: 02:41 PM
 *
 *
 *  @author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 */

namespace Whisper\Model;

class OperationResult
{

    public $_TimeOut, $_Result, $_Error, $_MSG ,$_StatusCode,$Succeeded;

    function __construct()
    {

    }

    public static function ReturnResult($Data)
    {
        $Result=new OperationResult();
        $Result->_Error=$Data->errorCode;
        $Result->_Result=$Data->result;
        $Result->Succeeded = $Data->succeeded;
        $Result->_StatusCode=$Data->code;
        $Result->_MSG=$Data->message;
        return $Result;
    }
    public static function ReturnSuccessResult($result,$msg="",$code =1)
    {
        $Result=new OperationResult();
        $Result->_Error=false;
        $Result->_Result=$result;
        $Result->Succeeded = true;
        $Result->_StatusCode=$code;
        $Result->_MSG=$msg;
        return $Result;
    }
    public static function ReturnErrorResult($msg="")
    {
        $Result=new OperationResult();
        $Result->_Error=true;
        $Result->_MSG=$msg;
        return $Result;
    }

    public static function ReturnTimeOutErrorResult($msg="",$statusCode=-1)
    {
        $Result=new OperationResult();
        $Result->_Error=true;
        $Result->_TimeOut = true;
        $Result->_StatusCode=$statusCode;
        $Result->_MSG=$msg;
        return $Result;
    }

}