<?php
namespace skycubes\economy;

use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\scheduler\Task;;

class Popup extends Task{
	protected $plugin;
	protected $player;
	private $seconds;
	private $text;

	public function __construct(Economy $plugin, Player $player, $text, $duration){
		$this->plugin = $plugin;
		$this->player = $player;
		$this->seconds = ceil(($duration-1)/0.35);
		$this->text = $text;
	}

	public function onRun($tick){

		if($this->seconds <= 0){
			$this->plugin->getScheduler()->cancelTask($this->getTaskId());
		}else{
			$this->player->sendTip($this->text, "teste");
		
			$this->seconds--;
		}

		
	}
}