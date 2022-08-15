<?php

/**
 * Whisper Tell System Core
 *
 * User: Milad Naderpour
 * Date: 08/08/2022
 * Time: 12:13 AM
 *
 *
 * @author Milad Naderpour <Milad.Naderpour@gmail.com>
 */

namespace Whisper\App;

use Exception;
use Monolog\Handler\StreamHandler;
use Whisper\Agi\AGI;
use Whisper\Database\DBEngine;
use Whisper\WebApiClient\WhisperWebApiClient;
use Monolog\Logger;


class Core
{

    private $_ChannelName = "Whisper Core";
    private $_ProgramId = 100;
    /**
     * @var
     */
    private $_Program;
    public $Engine, $Agi, $Db, $Api;
    private $_Pid, $_Cid, $_Uid, $_Ctype, $_Chan, $_CallId, $_ani;
    private $_Config, $_logger, $_Agi, $_DBEng;

    /**
     * @var StreamHandler
     */
    private $log_stream;

    /**
     * @throws Exception
     */
    function __construct($config) {
        $this->_Pid = getmypid();
        $this->_Config = $config;
        $this->_Program = $config['Program']['ID'];
        $this->_logger = new Logger('Core');
        //$this->log_stream = new StreamHandler(__DIR__.'/core.log', Logger::DEBUG);
        $this->log_stream = new StreamHandler($config['Log']['LogFile'], Logger::DEBUG);
        $this->_logger->pushHandler($this->log_stream);
    }

    /**
     * @throws Exception
     */
    public function Init()
    {
        $this->InitAgi();
        $this->InitDb();
        $this->InitWebApi();
        $this->InitEngine();
        return true;
    }

    /**
     * @throws Exception
     */
    public function InitWithoutAGI()
    {
        $this->InitDb();
        $this->InitWebApi();
        $this->InitEngine();
        return true;
    }

    private function InitAgi()
    {
        // AGI Config
        $this->Agi = new AGI();
        $this->Agi->verbose("Whisper is up ~ HI !" . " PID:" . $this->_Pid);
        $this->Agi->verbose("LogFile:" . $this->_Config['Log']['LogFile']);
        $this->Agi->verbose("LogFile:" . __DIR__);
    }

    private function InitDb(){
        $this->Db = new DBEngine($this->_Config,$this->log_stream);
        $this->Db->Init();
    }

    /**
     * @throws Exception
     */
    private function InitWebApi()
    {
        $this->_logger->debug("Init WebApi");
        $this->Api = new WhisperWebApiClient($this->_Config['Api']['Url'],$this->log_stream);
    }

    private function InitEngine()
    {
        $this->_logger->debug("Init Engine");
        $this->Engine = new STEngine($this->Agi,$this->Db,$this->Api,$this->log_stream);
    }



    public function FinalizeSession()
    {
        //$this->_DBEng->RemoveActiveCall($this->_CallId);
        //$this->_DBEng->AddCallDetail($this->_CallId, $this->_ProgramId, 109, "Session Finalized", "", "");
    }

    public function run()
    {
        $this->_logger->notice("Run Engine");
        $this->Engine->Start(2);
    }

}