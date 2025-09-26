<?php

namespace hudstats;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\utils\Config;

class Loader extends PluginBase implements Listener {

    private $clicks = [];
    private $lastHitTime = [];
    private $combo = [];
    private $config;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    }

    private function updateReach(Player $player): int {
        return (int)$player->getPosition()->distance($player->getLevel()->getSpawnLocation());
    }

    private function getClicks(Player $player): int {
        $name = $player->getName();
        if (!isset($this->clicks[$name])) return 0;
        $time = $this->clicks[$name][0];
        $clicks = $this->clicks[$name][1];
        if ($time !== time()) {
            unset($this->clicks[$name]);
            return 0;
        }
        return $clicks;
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $this->addClick($player);
    }

    public function onDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof Player) {
                $this->addClick($damager);
            }
        }
    }

    private function addClick(Player $player) {
        $name = $player->getName();
        if (!isset($this->clicks[$name])) {
            $this->clicks[$name] = [time(), 0];
        }
        [$time, $clicks] = $this->clicks[$name];
        if ($time !== time()) {
            $time = time();
            $clicks = 0;
        }
        $clicks++;
        $this->clicks[$name] = [$time, $clicks];
    }

    public function onEntityDamaged(EntityDamageEvent $event) {
        if (!$event instanceof EntityDamageByEntityEvent) return;
        $damager = $event->getDamager();
        $entity = $event->getEntity();
        if ($damager instanceof Player && $entity instanceof Player) {
            $cps = $this->getClicks($damager);
            $reach = $this->updateReach($entity);
            $combo = $this->calculateCombo($entity);
            $message = str_replace(
                ["{cps}", "{reach}", "{combo}"],
                [$cps, $reach, $combo],
                $this->config->get("tip")
            );
            $damager->sendTip($message);
        }
    }

    private function calculateCombo(Player $player): int {
        $name = $player->getName();
        $currentTime = time();
        $lastHit = $this->lastHitTime[$name] ?? 0;
        $combo = ($currentTime - $lastHit) < 1 ? ($this->combo[$name] ?? 0) + 1 : 0;
        $this->combo[$name] = $combo;
        $this->lastHitTime[$name] = $currentTime;
        return $combo;
    }
}
