<?php

namespace skycubes\economy;
use skycubes\economy\Definitions;

use \Exception;

class Translate{

	private $plugin;
	private $lang;
	private $language;

	public function __construct(Economy $plugin, $language=NULL){

		$this->plugin = $plugin;
		$definitions = new Definitions($this->plugin);

		$this->language = $language ?? $plugin->getLanguage();

		if(file_exists($definitions->getLangPath($this->language))){

			$this->lang = json_decode( file_get_contents($definitions->getLangPath($this->language)) );

		}else{
			throw new Exception('Translation file \''.$this->language.'.json\' not found.');
		}

	}


	/** 
    * Returns $identifier string from lang file
    * @access public 
    * @param String $identifier
    * @param Array $args
    * @return String
    */
	public function get($identifier, Array $args = []){

		if($this->lang->$identifier){

			if(count($args) > 0){
				$string = $this->lang->$identifier;
				for($i=0; $i < count($args); $i++){
					$string = preg_replace('/(\{arg'.($i+1).'\})/', preg_quote($args[$i]), $string);
				}
				return stripslashes($string);
			}else{
				return $this->lang->$identifier;
			}

		}else{
			throw new Exception('Cannot translate \''.$identifier.'\': Index not found.');
		}

	}

}