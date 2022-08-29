#!/usr/bin/php
<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Whisper\App\Core;
use Whisper\Database\DBEngine;
use Whisper\WebApiClient\WhisperWebApiClient;

require('vendor/autoload.php');

function getConfig()
{
    $config['DB']['host'] = "localhost";
    $config['DB']['User'] = "root";
    $config['DB']['Pass'] = "1q2w3e4r5t";
    $config['DB']['DBName'] = "WhisperDev";

    $config['Log']['DBLevel'] = 4;
    $config['Log']['Verbose'] = true;
    //$config['Api']['Url'] = 'http://172.20.199.16:8080/service/SataService.svc?wsdl';
    //$config['Api']['Url'] = 'https://172.20.199.16/api';
    $config['Api']['Url'] = 'https://localhost/api';
    $config['Program']['ID'] = '2';

    $config['Log']['LogFile'] = "/var/log/Agi/IVRApp.log";
    $config['Log']['Level'] = Logger::NOTICE;
    return $config;
}

/**
 * @throws Exception
 */
function runAgi($config)
{
    $App = new Core($config);
    if ($App->Init()) {
        $App->run();
    }
}


/**
 * @throws Exception
 */
function runTestApi(array $config, $func,$params=[])
{
    $log_stream = new StreamHandler(__DIR__.'/api.log', Logger::DEBUG);
    $Api= new WhisperWebApiClient($config['Api']['Url'],$log_stream);

    switch (strtolower($func))
    {
        case 'connection':
            echo 'Connect to Server '. $config['Api']['Url'] . "... \n" ;
            $res = $Api->TestConnection();
            if($res)
            {
                foreach ($res->_Result as $r)
                {
                    echo $r->key .' is: ' . $r->value . "\n";
                }
                return true;
            }
            echo 'Connection Failed';
            break;
        case 'clinic':
            printClinicStatus($Api->ClinicStatus($params[0]));
            break;
        case 'schedule':
            if (count($params) == 3)
                printSchedule($Api->ClinicProgram($params[0],$params[1],$params[2]));
            else
                var_dump($Api->CheckReservationTime($params[0],$params[1],$params[2],$params[3]));
            break;
        case 'reserve':
            var_dump($Api->Reservation($params[0],$params[1],$params[2],$params[3],$params[4],$params[5]));

    }
 return true;
}

function printClinicStatus($result)
{
    echo "\n **************  Clinic Status *************** \n";
    $format_clinic = "%6s   %-8s %-25s %-6s";
    echo (sprintf($format_clinic,"ID","Code","Clinic","Status")."\n");
    echo (sprintf($format_clinic,$result->_Result[0]->id,$result->_Result[0]->clinicCode,$result->_Result[0]->clinicName
        ,$result->_Result[0]->available));
}

function printSchedule($result)
{
    echo "\n **************  Schedule *************** \n";
    $format_clinic = "%6s   %-10s %-8s %-12s %-10s %-15s";
    echo (sprintf($format_clinic,"ID","VisitDate","Shift","DoctorCode","Capacity","RemainCapacity")."\n");
    foreach ($result->_Result as $schedule) {
        echo(sprintf($format_clinic, $schedule->Id, $schedule->VisitDate, $schedule->Shift, $schedule->DoctorCode,
            $schedule->Capacity, $schedule->RemainCapacity));
    }
}



function runTestDb(array $config, $func,$params=[])
{
    $log_stream = new StreamHandler(__DIR__.'/db.log', Logger::DEBUG);
    $dbe = new DBEngine($config,$log_stream);
    $host = $config['DB']['host'];
    $user = $config['DB']['User'];
    $dataBase = $config['DB']['DBName'];
    echo "Connect to Database $dataBase on $host by $user ... \n" ;
    $res = $dbe->Init();
    switch (strtolower($func))
    {
        case 'connection':
            if($res)
            {
                echo "db config is ok!";
                return true;
            }
            echo 'Connection Failed';
            break;
        case 'config':
            $r = $dbe->Application();
            printAppConfigs($r);
            break;
        case 'menu':
            $r = $dbe->GetMenus();
            printAppMenu($r->_Result);
            //var_dump($r);
            break;
    }
    return true;
}

function printAppConfigs($application)
{
    echo "\n **************  Application Config *************** \n";
    $format = "%4d %-20s %s";
    foreach ($application->AppConfigs as $cnf)
    {
       echo (sprintf($format,$cnf->Code,$cnf->Name,$cnf->Value)."\n");
    }
}

function printAppMenu($Menu)
{
    echo "\n **************  Application Menu *************** \n";
    $format_menu = "%6d   %-20s";
    $format_menuItem = "%15s  %-5s  %-32s  %-7s  %-6s  %-15s  %-25s\n";
    $format_menuValue = "%15s  %-10s  %-10s %-25s\n";
    foreach ($Menu->Menu as $mnu)
    {
        echo ("\n".sprintf($format_menu,$mnu["Id"],$mnu["Caption"])."\n");
        echo (sprintf($format_menuItem,"ID","Index","Caption","Options","Output", "Data","SoundFile"));
        foreach ($mnu["Items"] as $itm)
        {
            echo (sprintf($format_menuItem,$itm->Id,$itm->Index,$itm->Caption,$itm->Options,
                $itm->Output,$itm->Data,$itm->SoundFile));
        }
        echo "\n";
        echo (sprintf($format_menuValue,"ID","Key","Value","Description"));
        foreach ($mnu["Values"] as $vlu)
        {
            echo (sprintf($format_menuValue,$vlu->Id,$vlu->Key,$vlu->Value,$vlu->Description));
        }
        echo "\n\n";
    }

}

function printError($argc,$min,$msg)
{
    if($argc < $min)
    {
        echo ($msg);
        return true;
    }
    return false;
}

/**
 * @throws Exception
 */
function runTestCore(array $config,$func,$params=[])
{
    $core = new Core($config);
    //$core->InitWithoutAGI();
    $core->Init();
    //$engine = new \Whisper\App\STEngine();
    $engine = $core->Engine;
    switch (strtolower($func))
    {
        case 'connection':
           break;
        case 'checkclinic':
            $engine->AddDataARR('Clinic',$params[0]);
            $r = $engine->CheckSelectedClinic();
            var_dump($r);
            break;
        case 'schedule':
            $engine->AddDataARR('Clinic',$params[0]);
            $engine->AddDataARR('Day',$params[1]);
            $engine->AddDataARR('Shift',$params[2]);
            $r = $engine->GetClinicProgram();
            var_dump($r);
            break;
        case 'national':
            printNationalCodeStatus($params[0],$engine->CheckNationalCode($params[0]));
            break;
        case 'run':
            echo "\n **************  Run Core *************** \n";
            $core->run();
            break;
    }
}

function printNationalCodeStatus($code,$result)
{
    echo "\n **************  NationalCodeCheck For $code *************** \n";
    $format_clinic = "    %-16s   %-25s \n";
    echo (sprintf($format_clinic,"National Code","Result"));
    echo (sprintf($format_clinic,$code,$result->_MSG));
}


/**
 * @throws Exception
 */
function run($argc, $argv)
{
    $config = getConfig();
    if ($argc == 1)
        runAgi($config);

    if ($argc > 2)
    {
        switch ($argv[1])
        {
            case 'api':
                if(printError($argc,3,'test-api [connect]'))
                return;
                runTestApi($config,$argv[2],array_slice($argv,3));
                break;
            case'db':
                if(printError($argc,3,'db [connect,test]'))
                    return;
                runTestDb($config,$argv[2],array_slice($argv,3));
                break;
            case 'core':
                runTestCore($config,$argv[2],array_slice($argv,3));
                break;
        }
    }
}




try {
    run($argc, $argv);
    }
catch (Exception $e) {

    }

