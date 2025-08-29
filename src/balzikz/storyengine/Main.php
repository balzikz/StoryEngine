<?php

namespace balzikz\storyengine;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Server;

// Для NPC
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\CompoundTag;

// Для событий
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

// Для игрока и команд
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

// Для предметов
use pocketmine\item\VanillaItems;

// Для интерфейсов (UI)
use pocketmine\form\Form;
use pocketmine\form\element\Button;
use pocketmine\form\SimpleForm;

class Main extends PluginBase implements Listener {

    /** @var Human|null */
    private ?Human $storyNpc = null; // Переменная для хранения нашего NPC, чтобы к нему можно было обратиться

    // --- Главный метод, который выполняется при включении плагина ---
    public function onEnable() : void {
        // Регистрируем, что этот плагин будет слушать события (клики, урон и т.д.)
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Выводим сообщение в консоль, что плагин успешно запустился
        $this->getLogger()->info("§aStoryEngine успешно запущен!");

        // Вызываем функцию создания нашего NPC
        $this->spawnStoryNpc();
    }

    // --- Функция для создания NPC ---
    public function spawnStoryNpc() : void {
        // Получаем мир по умолчанию
        $world = $this->getServer()->getWorldManager()->getDefaultWorld();

        // Координаты, где появится NPC. Измени их на свои!
        $location = new Location(128, 66, 128, $world, 0, 0);

        // Загружаем скин из папки resources/skin.png
        // Если файла нет, сервер выдаст ошибку, так что убедись, что он на месте!
        try {
            $skin = new Skin("StoryNPC", self::getSkinDataFromPNG($this->getDataFolder() . "resources/skin.png"));
        } catch (\Exception $e) {
            $this->getLogger()->error("Не удалось загрузить скин для NPC: " . $e->getMessage());
            return;
        }

        // Создаем NPC
        $npc = new Human($location, $skin);

        // Настройки NPC
        $npc->setNameTag("Старейшина"); // Имя, которое будет видно над головой
        $npc->setNameTagAlwaysVisible(true); // Имя видно всегда, а не только при наведении
        $npc->setCanSaveWithChunk(true); // Сохранять NPC вместе с миром
        $npc->setImmobile(true); // Запрещаем NPC двигаться самостоятельно

        // "Спавним" (добавляем) NPC в мир
        $npc->spawnToAll();

        // Сохраняем NPC в нашу переменную для дальнейшего использования
        $this->storyNpc = $npc;
    }

    // --- Обработчик событий: что делать, когда игрок на что-то нажимает ---
    public function onPlayerInteract(PlayerInteractEvent $event) : void {
        $player = $event->getPlayer();
        $entity = $event->getTargetEntity();

        // Проверяем, что игрок нажал именно на нашего NPC
        if ($this->storyNpc !== null && $entity !== null && $entity->getId() === $this->storyNpc->getId()) {
            // Отменяем стандартное действие (удар)
            $event->cancel();
            // Открываем диалоговое окно для игрока
            $this->openMainDialog($player);
        }
    }

    // --- Обработчик урона: делаем нашего NPC бессмертным ---
    public function onEntityDamage(EntityDamageEvent $event) : void {
        $entity = $event->getEntity();

        // Проверяем, что урон пытаются нанести именно нашему NPC
        if ($this->storyNpc !== null && $entity->getId() === $this->storyNpc->getId()) {
            // Отменяем событие урона
            $event->cancel();
        }
    }

    // --- Функция, которая создает и показывает диалоговое окно ---
    public function openMainDialog(Player $player) : void {
        // Создаем простую форму (окно с кнопками)
        $form = new SimpleForm(function (Player $player, ?int $data) {
            // Эта часть кода выполняется ПОСЛЕ того, как игрок нажал на кнопку

            // Если игрок просто закрыл окно, ничего не делаем
            if ($data === null) {
                return;
            }

            // В переменной $data хранится номер кнопки, которую нажал игрок (начиная с 0)
            switch ($data) {
                case 0: // Нажата первая кнопка ("Расскажи мне историю")
                    $player->sendMessage("§e<Старейшина>§f Давным-давно, этот мир был...");
                    // Здесь ты можешь выдать квест, телепортировать игрока и т.д.
                    $player->getInventory()->addItem(VanillaItems::APPLE());
                    $player->sendMessage("§e<Старейшина>§f Возьми это яблоко, оно придаст тебе сил.");
                    break;

                case 1: // Нажата вторая кнопка ("Кто ты?")
                    $player->sendMessage("§e<Старейшина>§f Я лишь хранитель этого места. Мое имя давно забыто.");
                    break;

                case 2: // Нажата третья кнопка ("Мне пора идти")
                    $player->sendMessage("§e<Старейшина>§f Удачи тебе, путник.");
                    break;
            }
        });

        // --- Настраиваем внешний вид формы ---
        $form->setTitle("Разговор со Старейшиной"); // Заголовок окна
        $form->setContent("Здравствуй, путник. Что привело тебя в эти земли?"); // Основной текст
        $form->addButton("Расскажи мне историю", Button::TYPE_IMAGE, "textures/items/book_writable"); // Кнопка 0
        $form->addButton("Кто ты?", Button::TYPE_IMAGE, "textures/ui/icon_steve"); // Кнопка 1
        $form->addButton("Мне пора идти", Button::TYPE_IMAGE, "textures/ui/crossout"); // Кнопка 2

        // Отправляем форму игроку
        $player->sendForm($form);
    }
    
    private static function getSkinDataFromPNG(string $path) : string {
        $img = @imagecreatefrompng($path);
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
