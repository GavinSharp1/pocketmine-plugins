<?php
/**
 ** CONFIG:monitor-settings
 **/
namespace aliuly\helper;

use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;
use aliuly\helper\Main as HelperPlugin;
use aliuly\helper\common\mc;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;

class DbMonitorTask extends PluginTask implements Listener{
  protected $canary;
  protected $ok;
  protected $dbm;
  protected $fix;

  static public function defaults() {
		return [
      "# canary-account" => "account to query",//this account is tested to check database proper operations
      "canary-account" => "steve",
      "# check-interval" => "how to often to check database (seconds)",
      "check-interval" => 600,
		];
	}
	public function __construct(HelperPlugin $owner,$cfg){
		parent::__construct($owner);
    $this->canary = $cfg["canary-account"];
    $owner->getServer()->getScheduler()->scheduleRepeatingTask($this,$cfg["check-interval"]*20);
    $this->ok = true; // Assume things are OK...
    $this->dbm = $owner->auth->getDataProvider();
    $owner->getServer()->getPluginManager()->registerEvents($this, $owner);
	}
  private function setStatus($mode) {
    if ($this->ok === $mode) return;
    $this->ok = $mode;
    if ($mode) {
      $this->getOwner()->getLogger()->info(mc::_("Restored database connection"));
      $this->getOwner()->getServer()->broadcastMessage(TextFormat::GREEN.mc::_("Database connectivity restored!"));
    } else {
      $this->getOwner()->getLogger()->error(mc::_("LOST DATABASE CONNECTION!"));
      $this->getOwner()->getServer()->broadcastMessage(TextFormat::RED.mc::_("Detected loss of database connectivity!"));
    }
  }
  private function enableAuth($mgr,$auth) {
    if ($auth === null) return false; // OK, this is weird!
    if ($auth->isEnabled()) return true;
    $this->getOwner()->getLogger()->info(mc::_("Enabling SimpleAuth"));
    $mgr->enablePlugin($auth);
    if (!$auth->isEnabled()) return false;
    $this->dbm = $auth->getDataProvider();
    return true;
  }

	public function onRun($currentTicks){
    //echo __METHOD__.",".__LINE__."\n";//##DEBUG
    $mgr = $this->getOwner()->getServer()->getPluginManager();
    $auth = $mgr->getPlugin("SimpleAuth");
    if ($auth === null) return; // OK, this is weird!

    if (!$auth->isEnabled()) {
      if (!$this->enableAuth($mgr,$auth)) return; // Ouch...
    }

    $player = $this->getOwner()->getServer()->getOfflinePlayer($this->canary);
    if ($player == null) return;//We can't proceed!
    if ($this->dbm->isPlayerRegistered($player)) {
      $this->setStatus(true);
      return;
    }
    /*
     * Lost connection to database...
     */
    $this->setStatus(false);
    /*
     * let's try to reconnect by resetting SimpleAuth
     */
    if ($auth->isEnabled()) {
      $this->getOwner()->getLogger()->info(mc::_("Disabling SimpleAuth"));
      $mgr->disablePlugin($auth);
    }
    if (!$auth->isEnabled()) {
      $this->getOwner()->getLogger()->info(mc::_("Enabling SimpleAuth"));
      if (!$this->enableAuth($mgr,$auth)) return; // Ouch...
    }
    if ($this->dbm->isPlayerRegistered($player)) $this->setStatus(true);
	}
  public function onConnect(PlayerJoinEvent $ev) {
    if ($this->ok) return;
    $ev->getPlayer()->kick(mc::_("Database is experiencing technical difficulties"));
  }
}