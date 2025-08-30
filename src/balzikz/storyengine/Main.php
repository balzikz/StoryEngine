<?php

namespace balzikz\storyengine;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\Config;

// Для NPC
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;

// Для событий
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;

// Для предметов
use pocketmine\item\VanillaItems;

// Для интерфейсов (UI)
use pocketmine\form\SimpleForm;
use pocketmine\form\element\Button;


class Main extends PluginBase implements Listener {

    private ?Human $storyNpc = null;
    private Config $playerData;

    // --- Главный метод, который выполняется при включении плагина ---
    public function onEnable() : void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // --- ГЛАВНОЕ ИСПРАВЛЕНИЕ КРАША ---
        // Эта команда "распаковывает" skin.png из нашего .phar архива
        // в папку /plugin_data/StoryEngine/, если его там еще нет.
        // Это нужно сделать ДО того, как мы попытаемся его использовать.
        $this->saveResource("skin.png");
        // -------------------------------------

        // Инициализируем хранилище данных для прогресса игрока
        $this->playerData = new Config($this->getDataFolder() . "player_data.yml", Config::YAML);

        $this->getLogger()->info("§aStoryEngine успешно запущен!");

        // Вызываем функцию создания нашего NPC
        $this->spawnStoryNpc();
    }

    // --- Функции для удобной работы с данными игрока ---
    public function getPlayerProgress(Player $player): array {
        // Получаем данные игрока по его нику. Если данных нет, возвращаем массив по умолчанию.
        return $this->playerData->get($player->getName(), [
            "quest_stage" => 0 // 0 = игрок еще не начинал сюжет
        ]);
    }

    public function setPlayerProgress(Player $player, array $data): void {
        // Устанавливаем и сразу сохраняем данные игрока.
        $this->playerData->set($player->getName(), $data);
        $this->playerData->save();
    }

    // --- Функция для создания NPC ---
    public function spawnStoryNpc() : void {
        $world = $this->getServer()->getWorldManager()->getDefaultWorld();
        $location = new Location(128, 66, 128, $world, 0, 0);

        try {
            // --- ВТОРОЕ ИСПРАВЛЕНИЕ ---
            // Теперь мы ищем скин в папке с данными плагина, а не в "resources"
            $skinPath = $this->getDataFolder() . "skin.png";
            $skin = new Skin("StoryNPC", self::getSkinDataFromPNG($skinPath));
        } catch (\Exception $e) {
            $this->getLogger()->error("Не удалось загрузить скин для NPC: " . $e->getMessage());
            return;
        }

        $npc = new Human($location, $skin);
        $npc->setNameTag("Старейшина");
        $npc->setNameTagAlwaysVisible(true);
        $npc->spawnToAll();
        $this->storyNpc = $npc;
    }

    // --- Обработчик событий: что делать, когда игрок на что-то нажимает ---
    public function onPlayerInteract(PlayerInteractEvent $event) : void {
        $player = $event->getPlayer();
        $entity = $event->getTargetEntity();

        if ($this->storyNpc !== null && $entity !== null && $entity->getId() === $this->storyNpc->getId()) {
            $event->cancel();
            $this->openMainDialog($player);
        }
    }

    // --- Обработчик урона: делаем нашего NPC бессмертным ---
    public function onEntityDamage(EntityDamageEvent $event) : void {
        $entity = $event->getEntity();
        if ($this->storyNpc !== null && $entity->getId() === $this->storyNpc->getId()) {
            $event->cancel();
        }
    }

    // --- Функция, которая создает и показывает "умный" диалог ---
    public function openMainDialog(Player $player) : void {
        $progress = $this->getPlayerProgress($player);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($progress) {
            if ($data === null) return;

            // Логика зависит от стадии квеста
            if ($progress['quest_stage'] === 0) { // Если игрок на начальной стадии
                switch ($data) {
                    case 0: // Кнопка "Я готов помочь"
                        $player->sendMessage("§e<Старейшина>§f Отлично! Для начала... Принеси мне красный цветок. Он растет где-то на поляне за деревней.");
                        
                        // Обновляем прогресс игрока!
                        $newProgress = $progress;
                        $newProgress['quest_stage'] = 1; // Переводим игрока на стадию 1
                        $this->setPlayerProgress($player, $newProgress);
                        break;
                    case 1: // Кнопка "Мне нужно подумать"
                        $player->sendMessage("§e<Старейшина>§f Не затягивай. Судьба мира не ждет.");
                        break;
                }
            } elseif ($progress['quest_stage'] === 1) { // Если игрок уже взял квест
                 switch ($data) {
                    case 0: // Кнопка "Я принес цветок"
                        $player->sendMessage("§e<Старейшина>§f (Логика проверки цветка еще не добавлена)");
                        break;
                    case 1: // Кнопка "Напомни, что нужно сделать?"
                        $player->sendMessage("§e<Старейшина>§f Я попросил тебя принести мне красный цветок с поляны.");
                        break;
                }
            }
        });

        // Форма меняется в зависимости от прогресса
        if ($progress['quest_stage'] === 0) {
            $form->setTitle("Разговор со Старейшиной");
            $form->setContent("Здравствуй, путник. Мир на пороге тьмы, и нам нужна твоя помощь. Ты готов?");
            $form->addButton("Я готов помочь");
            $form->addButton("Мне нужно подумать");
        } elseif ($progress['quest_stage'] === 1) {
            $form->setTitle("Разговор со Старейшиной");
            $form->setContent("Ну что, путник? Удалось ли тебе найти то, о чем я просил?");
            $form->addButton("Я принес цветок");
            $form->addButton("Напомни, что нужно сделать?");
        }
        
        $player->sendForm($form);
    }
    
    // Вспомогательная функция для загрузки скина из файла (без изменений)
    private static function getSkinDataFromPNG(string $path) : string {
        $img = @imagecreatefrompng($path);
        if($img === false){
            throw new \Exception("Не удалось прочитать изображение по пути: $path");
        }
        $bytes = "";
        for ($y = 0; $y < imagesy($img); $y++) {
            for ($x = 0; $x < imagesx($img); $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                $bytes .= chr(($rgba >> 16) & 0xff) . chr(($rgba >> 8) & 0xff) . chr($rgba & 0xff) . chr(((~($rgba >> 24)) << 1) & 0xff);
            }
        }
        @imagedestroy($img);
        return $bytes;
    }
}
