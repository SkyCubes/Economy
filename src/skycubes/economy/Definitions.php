<?php

namespace skycubes\economy;
use skycubes\economy\Economy;
use \Exception;

class Definitions{

	protected const LANG_PATH = 'lang';
	protected const ACCOUNTS_TABLE = 'accounts';

	private $plugin;

	public function __construct(Economy $plugin){

		$this->plugin = $plugin;

	}

	public function getLangPath(string $language){

		return $this->plugin->getDataFolder().self::LANG_PATH.'/'.$language.'.json';

	}
	public function getSQLitePath(string $sqlitedb){

		return $this->plugin->getDataFolder().'/'.$sqlitedb;

	}

	public function getDef($def){

		return constant('self::'.$def);

	}



}

?>