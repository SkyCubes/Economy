<?php

namespace skycubes\economy;

use skycubes\economy\Definitions;
use skycubes\economy\Translate;

use \PDO;
use \PDOException;

class EconomyDB{

	protected $conn;
	private $lastError;

	private $translator;
	private $definitions;

	private $DBInfos = array();
	private $allowedCurrencies = array();
	private $prefix = "";

	private $accountsTable;

	public function __construct(Economy $plugin){

		$this->translator = new Translate($plugin);
		$this->definitions = new Definitions($plugin);

		$this->accountsTable = $this->definitions->getDef('ACCOUNTS_TABLE');
		$this->setDBPasswd = '';
	}

	public function getLastError(){
		return $this->lastError;
	}

	public function setDBMode(bool $mode){
		$this->DBInfos['mode'] = ($mode) ? 'mysql' : 'sqlite';
	}

	public function setDBName(string $name){
		$this->DBInfos['name'] = $name;
	}

	public function setDBHost(string $host){
		$this->DBInfos['host'] = $host;
	}

	public function setDBUser(string $user){
		$this->DBInfos['user'] = $user;
	}

	public function setDBPasswd(string $passwd){
		$this->DBInfos['passwd'] = $passwd;
	}

	public function setDBPrefix(string $prefix){
		$this->prefix = $prefix;
	}

	public function setAllowedCurrencies(Array $currencies){
		$this->allowedCurrencies = $currencies;
	}

	public function getAllowedCurrencies(){
		return $this->allowedCurrencies;
	}

	public function loadDatabase(){
		if(!isset($this->DBInfos['mode'])){
			$this->lastError = $this->translator->get('DB_UNDEFINED_MODE');
			return false;
		}
		if(!isset($this->DBInfos['name'])){
			$this->lastError = $this->translator->get('DB_UNDEFINED_NAME');
			return false;
		}

		switch(strtolower($this->DBInfos['mode'])){
			case 'sqlite':

				try{
					$this->conn = new PDO("sqlite:".$this->definitions->getSQLitePath($this->DBInfos['name']));
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}
				
				break;

			case 'mysql':

				if(!isset($this->DBInfos['host'])){
					$this->lastError = $this->translator->get('DB_UNDEFINED_HOST');
					return false;
				}
				if(!isset($this->DBInfos['user'])){
					$this->lastError = $this->translator->get('DB_UNDEFINED_USER');
					return false;
				}

				try{
					$connString = "mysql:host=".$this->DBInfos['host'].";dbname=".$this->DBInfos['name'];
					$this->conn = new PDO($connString, $this->DBInfos['user'], $this->DBInfos['passwd']);
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}

				break;

			default:
				$this->lastError = $this->translator->get('DB_UNRECOGNIZED_MODE');
				return false;
				break;
		}

		return $this->startTables();
	}

	private function startTables(){
		$table = $this->prefix.$this->accountsTable;

		try{
			$query = $this->conn->prepare("CREATE TABLE IF NOT EXISTS `".$table."` (
				id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				player VARCHAR(40)
			);");
			$query->execute();
		}catch(PDOException $e){
			$this->lastError = $e->getMessage();
			return false;
		}

		foreach($this->allowedCurrencies as $currency){
			$presentCurrencies = [];

			try{
				$checkPresentCurrencies = $this->conn->query("PRAGMA table_info(".$table.")");
				$checkPresentCurrencies->setFetchMode(PDO::FETCH_ASSOC);
			}catch(PDOException $e){
				$this->lastError = $e->getMessage();
				return false;
			}

			foreach ($checkPresentCurrencies as $presentCurrency){
				$presentCurrencies[] = $presentCurrency['name'];
			}

			if(!in_array($currency, $presentCurrencies)){

				try{
					$this->conn->query("ALTER TABLE ".$table." ADD ".$currency." FLOAT(10) DEFAULT 0;");
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}

			}
		}

		return true;
	}

	public function createWallet(string $playerName){

		$table = $this->prefix.$this->accountsTable;

		try{
			if($this->walletExists($playerName)){
				$this->lastError = $this->translator->get('CANNOT_CREATE_WALLET_PLAYER_ALREADY_HAVE_IT', [$playerName]);
				return false;
			}

			$query = $this->conn->prepare("INSERT INTO ".$table." (player) VALUES (:player)");
			$query->bindValue(":player", $playerName);
			$query->execute();
		}catch(Exception $e){
			$this->lastError = $e->getMessage();
			return false;
		}

		return true;

	}

	public function giveMoney(string $playerName, string $currency, float $value){
		$table = $this->prefix.$this->accountsTable;

		if(in_array($currency, $this->allowedCurrencies)){

			$playerCurrencyAmount = $this->getWallet($playerName, $currency);
			if($playerCurrencyAmount !== false){

				$value = $playerCurrencyAmount + $value;

				try{
					$sql = "UPDATE ".$table." SET ".$currency." = :value WHERE LOWER(player) = LOWER(:player)";
					$query = $this->conn->prepare($sql);
					$query->bindValue(":value", $value);
					$query->bindValue(":player", $playerName);
					$query->execute();
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}

				return true;

			}else{
				return false;
			}

		}else{
			$this->lastError = $this->translator->get('CURRENCY_NOT_ALLOWED');
			return false;
		}
	}

	public function removeMoney(string $playerName, string $currency, float $value, ?bool $force = false){
		$table = $this->prefix.$this->accountsTable;

		if(in_array($currency, $this->allowedCurrencies)){

			$playerCurrencyAmount = $this->getWallet($playerName, $currency);
			if($playerCurrencyAmount !== false){

				$value = $playerCurrencyAmount - $value;
				if(!$force){
					if($value < 0) $value = 0;
				}

				try{
					$sql = "UPDATE ".$table." SET ".$currency." = :value WHERE LOWER(player) = LOWER(:player)";
					$query = $this->conn->prepare($sql);
					$query->bindValue(":value", $value);
					$query->bindValue(":player", $playerName);
					$query->execute();
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}

				return true;

			}else{
				return false;
			}

		}else{
			$this->lastError = $this->translator->get('CURRENCY_NOT_ALLOWED');
			return false;
		}
	}

	public function transferMoney(string $payingAgent, string $recipient, string $currency, float $value){
		$table = $this->prefix.$this->accountsTable;

		if($this->getWallet($payingAgent, $currency) < $value) return false;

		if($this->removeMoney($payingAgent, $currency, $value)){

			if($this->giveMoney($recipient, $currency, $value)){
				return true;
			}else{
				$this->lastError = $this->translator->get('CANNOT_TRANSFER_MONEY_TO_PLAYER', [$payingAgent, $recipient]);
				$this->giveMoney($payingAgent, $currency, $value);
				return false;
			}

		}

	}

	public function getWallet(string $playerName, ?string $currency = NULL){
		$table = $this->prefix.$this->accountsTable;

		if($currency !== null){
			if(in_array($currency, $this->allowedCurrencies)){

				try{
					$query = $this->conn->prepare("SELECT ".$currency." FROM ".$table." WHERE LOWER(player) = LOWER(:player)");
					$query->bindValue(":player", $playerName);
					$query->execute();
					$result = $query->fetch(PDO::FETCH_ASSOC);
				}catch(PDOException $e){
					$this->lastError = $e->getMessage();
					return false;
				}
				
				return $result[$currency];

			}else{
				return false;
			}
		}else{
			try{
				$query = $this->conn->prepare("SELECT * FROM ".$table." WHERE LOWER(player) = LOWER(:player)");
				$query->bindValue(":player", $playerName);
				$query->execute();
				$result = $query->fetch(PDO::FETCH_ASSOC);
				return $result;
			}catch(PDOException $e){
				$this->lastError = $e->getMessage();
				return false;
			}
		}
		
	}

	public function walletExists(string $playerName){

		$table = $this->prefix.$this->accountsTable;

		try{
			$query = $this->conn->prepare("SELECT * FROM ".$table." WHERE LOWER(player) = LOWER(:player)");
			$query->bindValue(":player", $playerName);
			$query->execute();
			$result = $query->fetchAll(PDO::FETCH_ASSOC);
		}catch(PDOException $e){
			throw new Exception($e->getMessage());
		}

		return (count($result) > 0) ? true : false; 
		
	}
}