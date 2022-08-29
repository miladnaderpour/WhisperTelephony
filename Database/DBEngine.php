<?php


/**
 * Class RTCEng
 *
 * User: Milad Naderpour
 * Date: 19/04/2016
 * Time: 02:18 PM
 *
 * 		@author Milad Naderpour <Milad.Naderpour@gmail.com>
 *
 */

namespace Whisper\Database;

use Monolog\Logger;
use Whisper\Model\APPConfig;
use Whisper\Model\Application;
use Whisper\Model\Menu;
use Whisper\Model\MenuItem;
use Whisper\Model\MenuValue;
use Whisper\Model\OperationResult;

class DBEngine{

	public $State,$_logger;

	private $_Con,$_DBInited ;

	private $_Uid,$_CallId,$_Cid;
    /**
     * @var mixed
     */
    private $_host;
    /**
     * @var string
     */
    private $_user;
    /**
     * @var string
     */
    private $_password;
    /**
     * @var string
     */
    private $_dataBase;

    function __construct($config,$logStream)
    {
        $this->_host = $config['DB']['host'];
        $this->_user = $config['DB']['User'];
        $this->_password = $config['DB']['Pass'];
        $this->_dataBase = $config['DB']['DBName'];
        $this->_logger = new Logger('Db Engine');
        $this->_logger->pushHandler($logStream);
    }

	public function Init()
	{
		$this->_Con = mysqli_connect($this->_host, $this->_user, $this->_password, $this->_dataBase);
		if (!$this->_Con) {
			$err=  mysqli_connect_errno() ." - " . mysqli_connect_error();
            $this->_logger->addError("Database Connection failed - ".$err);
			return false;
		}
		$this->_Con->set_charset('utf8');
        $this->_logger->debug("Db Connection successful! - ".mysqli_get_host_info($this->_Con));
		$this->_DBInited=true;
		return true;
	}

	public function IsInited()
	{
		return $this->_DBInited;
	}

	public function Application()
	{
		$AppCfg=array();
		if($this->_DBInited)
		{
			$qry="Select Id,Config,ConfigurationCode,`Value`,Flag From Application;";
            $this->_logger->debug("Get Application Config - ".$qry);
			$qres=$this->_Con->query($qry);
			if ($qres->num_rows > 0)
			{
				while($row = $qres->fetch_assoc())
				{
					$Config= new APPConfig($row["Config"],$row["ConfigurationCode"],$row["Value"],$row["Flag"]);
					$AppCfg[$Config->Code]=$Config;
				}
                return new Application($AppCfg);
			}
			else
			{
                $this->_logger->error("No APP Data",["Application"]);
				return false;
			}
		}
		else
		{
            $this->_logger->error("DB Not Inited:: Init Error",["Application"]);
			return false;
		}
	}

	public function GetMenus()
	{
        $this->_logger->debug("Fetch Menus Information",["GetMenu"]);
		if($this->_DBInited)
		{
			$qry = "SELECT
                            Menus.Id, 
                            Menus.Caption, 
                            Menus.Type, 
                            MenuItems.Id AS ItemId, 
                            MenuItems.ItemType AS ItemTypeId,
                            ItemType.`Name`AS ItemType,
                            MenuItems.`Index` AS ItemIndex, 
                            MenuItems.Caption AS ItemCaption, 
                            MenuItems.SoundFile, 
                            MenuItems.`Options`, 
                            MenuItems.Output, 
                            MenuItems.`Data`
                        FROM
                            Menus
                            LEFT JOIN MenuItems ON Menus.Id = MenuItems.MenuId
                            INNER JOIN ItemType ON MenuItems.ItemType = ItemType.Id 
                        ORDER BY
                            Menus.Id, 
                            MenuItems.`Index`;";

			$qres=$this->_Con->query($qry);
            $this->_logger->debug("Query Successful !: ".$qres->num_rows . " Item",["GetMenu"]);
			if ($qres->num_rows > 0)
			{
                $Menu = $this->CreateMenuItems($qres);
                $Menu = $this->GetMenuValues($Menu);
				return OperationResult::ReturnSuccessResult($Menu);
			}
			else
			{
                $this->_logger->error("Menu was Not Found!",["GetMenu"]);
                return OperationResult::ReturnErrorResult("No Menu was Found!");
			}
		}
		else
		{
			return OperationResult::ReturnErrorResult();
		}
	}

    private function CreateMenuItems($qres)
    {
        $this->_logger->debug("Items Loaded Successfully !",["CreateMenu"]);
        $format_menuItem = "Item:%-4s  Type(%-2s)%-10s %5s %-25s  %-2s";
        $Menu= new Menu();
        while($row = $qres->fetch_assoc())
        {
            $MenuItem = new MenuItem($row["ItemId"],$row["ItemTypeId"],$row["ItemIndex"],$row["ItemCaption"],
                $row["SoundFile"],$row["Options"],$row["Output"],$row["Data"]);
            $MenuItem->MenuId=$row["Id"];

            $this->_logger->debug(sprintf($format_menuItem,$row["ItemId"],$row["ItemTypeId"],$row["ItemType"],
                                                           $row["ItemIndex"],$row["ItemCaption"],$row["Id"]));

            $Menu->AddItem($row["Id"],$row["Caption"],$MenuItem);
            $this->_logger->debug($row["Id"].":".$row["Caption"] ." added to menu ".$row["Id"],["CreateMenu"]);
        }
        return $Menu;
    }

	private function GetMenuValues($Menu)
	{
        $this->_logger->debug("Fetch Menu Key-Value Information",["GetMenuValues"]);

		if($this->_DBInited)
		{
			$qury = "SELECT MenuValues.Id,MenuValues.MenuId,MenuValues.`Key`,MenuValues.`Value`,MenuValues.Description
					  FROM MenuValues;" ;
			$qres=$this->_Con->query($qury);
            $this->_logger->debug("Query Successful !: ".$qres->num_rows . " Value",["GetMenuValues"]);
			if ($qres->num_rows > 0)
			{
			    return $this->CreateMenuValues($qres,$Menu);
			}
			else
			{
                $this->_logger->error("No Menu Values was Found!",["GetMenuValues"]);
                return $Menu;
			}
		}
		else
		{
            $this->_logger->error("Database Error",["GetMenuValues"]);
		}
        return $Menu;
	}

    private function CreateMenuValues($qres,$Menu)
    {
        $this->_logger->debug("Values Loaded Successfully !",["CreateMenuValues"]);
        $format_menuValue = "%5s  key:%-10s  value:%-10s  %-25s";
        while($row = $qres->fetch_assoc())
        {
            $MenuValue = new MenuValue($row["Id"],$row["MenuId"],$row["Key"],$row["Value"],$row["Description"]);
            $Menu->AddValue($row["MenuId"],$MenuValue);
            $this->_logger->debug(sprintf($format_menuValue,$row["MenuId"],$row["Key"],$row["Value"],$row["Description"]));
        }
        return $Menu;
    }
}