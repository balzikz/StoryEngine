<?php

namespace balzikz\storyengine;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;

class StoryCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("story", "Главная команда StoryEngine", "/story help", ["se"]);
        $this->setPermission("storyengine.command.admin");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if(!$this->testPermission($sender)) return false;
        if(!$sender instanceof Player) {
            $sender->sendMessage("Используйте команду в игре.");
            return false;
        }

        if(!isset($args[0])) {
            $sender->sendMessage("Используйте /story help");
            return false;
        }

        switch($args[0]) {
            case "npc":
                if(!isset($args[1])) {
                    $sender->sendMessage("Использование: /story npc <create|remove> ...");
                    return false;
                }
                
                if($args[1] === 'create') {
                    if(!isset($args[2]) || !isset($args[3])) {
                        $sender->sendMessage("Использование: /story npc create <id> <имя_в_кавычках>");
                        return false;
                    }
                    $this->createNpc($sender, $args[2], $args[3]);
                }
                break;
        }
        return true;
    }

    private function createNpc(Player $player, string $npcId, string $name): void {
        $npcConfig = new Config($this.getPlugin()->getDataFolder() . "npcs.yml", Config::YAML);
        
        if($npcConfig->exists($npcId)) {
            $player->sendMessage("§cNPC с ID '$npcId' уже существует!");
            return;
        }

        $pos = $player->getPosition();
        $npcConfig->set($npcId, [
            "name" => $name,
            "skin" => "default",
            "world" => $pos->getWorld()->getFolderName(),
            "x" => $pos->getX(),
            "y" => $pos->getY(),
            "z" => $pos->getZ()
        ]);
        $npcConfig->save();
        $player->sendMessage("§aNPC '$name' с ID '$npcId' успешно создан и сохранен в npcs.yml!");
    }
}
