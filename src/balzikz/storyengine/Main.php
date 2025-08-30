<?php

namespace balzikz\storyengine;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase {

    public function onEnable() : void {
        $this->saveResource("npcs.yml");
        
        $this.getServer()->getCommandMap()->register("storyengine", new StoryCommand($this));
        
        $this.getLogger()->info("§aStoryEngine [Режим Режиссера] запущен!");
    }
}
