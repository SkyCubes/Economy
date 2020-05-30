<?php

namespace skycubes\economy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\level\sound\PopSound;

use skycubes\economy\Definitions;
use skycubes\economy\Translate;
use skycubes\economy\Popup;

use \PDO;
use \PDOException;

class Economy extends PluginBase implements Listener{

	protected $config;
	protected $conn;
	protected $skyforms;
	private $translator;

	private $definitions;

	public function onLoad(){

		$this->definitions = new Definitions($this);
		
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder().$this->definitions->getDef('LANG_PATH'));
        foreach(array_keys($this->getResources()) as $resource){
			$this->saveResource($resource, false);
		}
	}


	public function onEnable(){

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);

		$this->skyforms = $this->getServer()->getPluginManager()->getPlugin("SkyForms");

		$this->translator = new Translate($this);
		$this->economy = new EconomyDB($this);

		$dbMode = $this->config->get('Mode');
		switch(strtolower($dbMode)){
			case 'sqlite':
				$this->economy->setDBMode(false);
				$this->economy->setDBName($this->config->get('SQLite_info')['db_name']);
				$this->economy->setDBPrefix($this->config->get('SQLite_info')['table_prefix']);
				break;

			case 'mysql':
				$this->economy->setDBMode(true);
				$this->economy->setDBHost($this->config->get('MySQL_info')['db_host']);
				$this->economy->setDBName($this->config->get('MySQL_info')['db_name']);
				$this->economy->setDBUser($this->config->get('MySQL_info')['db_user']);
				$this->economy->setDBUser($this->config->get('MySQL_info')['db_passwd']);
				$this->economy->setDBPrefix($this->config->get('MySQL_info')['table_prefix']);
				break;

			default:
				break;
		}

		$this->economy->setAllowedCurrencies(array_keys($this->config->get('Currencies')));

		if($this->economy->loadDatabase()){
			$this->getLogger()->info("§a".$this->getFullName()." carregado com sucesso!");
		}else{
			$this->getLogger()->info("§c".$this->economy->getLastError());
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "economy":
				$sender->sendMessage($this->getFullName());
				return true;

			case "carteira":
			case "money":
			case "bal:":

				$playerName = $sender->getName();
				$playerNameWallet = isset($args[0]) ? $args[0] : $playerName;

				$this->showWallet($sender, $playerNameWallet);

				break;

			case "pagar":

				$playerName = $sender->getName();
				$this->showPaymentForm($sender);

				break;

			default:
				break;
		}
		return true;
	}


	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$playerName = $player->getName();

		if(!$this->economy->walletExists($playerName)){
			if($this->economy->createWallet($playerName)){

				foreach ($this->economy->getAllowedCurrencies() as $currency){

					$initialCurrencyValue = $this->config->get('Currencies')[$currency]['InitialValue'];
					if(!$this->economy->giveMoney($playerName, $currency, $initialCurrencyValue)){
						$this->getLogger()->info("§c".$this->economy->getLastError());
					}

				}

			}else{
				$this->getLogger()->info("§c".$this->economy->getLastError());
			}
		}

	}

	public function showPaymentForm(Player $player){


		$formTitle = $this->translator->get('FORM_PAYMENT_TITLE');
		$form = $this->skyforms->createCustomForm($formTitle);

		$form->addInput($this->translator->get('FORM_RECIPIENT_LABEL'), $this->translator->get('FORM_RECIPIENT_PLACEHOLDER'));
		$form->addDropdown($this->translator->get('FORM_CURRENCY_LABEL'), $this->economy->getAllowedCurrencies());
		$form->addInput($this->translator->get('FORM_VALUE_LABEL'), $this->translator->get('FORM_VALUE_PLACEHOLDER'));

		$self = $this;
		$okSound = new PopSound($player->getPosition());

		$form->sendTo($player, function($response) use (&$self, &$player, &$okSound){
			$currencies = $self->economy->getAllowedCurrencies();

			$recipientName = $response[$this->translator->get('FORM_RECIPIENT_LABEL')];
			$currency = $currencies[$response[$this->translator->get('FORM_CURRENCY_LABEL')]];
			$symbol = $self->config->get('Currencies')[$currency]['Symbol'];
			$value = $response[$this->translator->get('FORM_VALUE_LABEL')];

			if(is_numeric($value) && $value > 0){
				if($value <= $self->economy->getWallet($player->getName(), $currency)){
					if($self->economy->walletExists($recipientName)){
						if($self->economy->transferMoney($player->getName(), $recipientName, $currency, $value)){

							$player->getLevel()->addSound($okSound);
							 
							$player->addTitle("§2§l".$self->translator->get('FORM_RESULT_PAID'), "§a".$self->translator->get('FORM_RESULT_DESCRIPTION', ["§e".$symbol.$value."§a", "§6".$recipientName])."§a.", 20, 2*20, 20);
						}
					}else{
						$player->addTitle("\n", "§c".$this->translator->get('PLAYER_HAVENT_A_WALLET', [$recipientName]), 20, 2*20, 20);
					}
				}else{
					$player->addTitle("\n", "§c".$this->translator->get('YOU_DONT_HAVE_MONEY_ENOUGH'), 20, 2*20, 20);
				}
				
			}else{
				$player->addTitle("\n", "§c".$this->translator->get('FORM_INSERT_VALID_VALUE'), 20, 2*20, 20);
			}
							  
		});

	}

	public function showWallet(Player $player, string $playerNameWallet){
		$playerWallet = $this->economy->getWallet($playerNameWallet);

		if(!$playerWallet){
			if($playerNameWallet == $player->getName()){
				$popupString = "§c".$this->translator->get('YOU_HAVENT_A_WALLET');
			}else{
				$popupString = "§c".$this->translator->get('PLAYER_HAVENT_A_WALLET', [$playerNameWallet]);
			}
			$this->getScheduler()->scheduleRepeatingTask(new Popup($this, $player, $popupString, 5), 7);
			return;
		}
		if($playerNameWallet == $player->getName()){
			$popupStringTitle = "";
		}else{
			$popupStringTitle = "§a".$this->translator->get('WALLET_OF_PLAYER', ["§7".$playerWallet['player']])."§a:\n";
		}
		$popupString = "";
		foreach($this->economy->getAllowedCurrencies() as $currency){
			$amount = number_format($playerWallet[$currency], $this->config->get('Currencies')[$currency]['DecimalPlaces'], ',', '.');
			$customName = $this->config->get('Currencies')[$currency]['CustomName'];
			$symbol = $this->config->get('Currencies')[$currency]['Symbol'];
			$popupString .= "§f/ ".$customName."§f: §e".$symbol.$amount." ";
		}
		$popupString = preg_replace('/^(§f\/ )/', '', $popupString);
		$this->getScheduler()->scheduleRepeatingTask(new Popup($this, $player, $popupStringTitle.$popupString, 5), 7);
	}

	/** 
    * Returns selected language in config.yml
    * @access public
    * @return String
    */
    public function getLanguage(){
    	return $this->config->get('Language');
    }
}
?>