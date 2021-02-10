<?php

/**
 * @name Check
 * @main discord3682\check\Check
 * @author discord3682
 * @version 0.0.2
 * @api 3.0.0
 */

namespace discord3682\check;

use onebone\economyapi\EconomyAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\Plugin;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\utils\SingletonTrait;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\nbt\tag\IntTag;
use pocketmine\form\Form;
use pocketmine\item\Item;
use pocketmine\Player;

class Check extends PluginBase implements Listener
{

  use SingletonTrait;

  const CHECK = '§l§b[수표]§r§7 ';

  public function onEnable () : void
  {
    if ($this->getServer ()->getPluginManager ()->getPlugin ('EconomyAPI') instanceof Plugin)
    {
      $this->getServer ()->getPluginManager ()->registerEvents ($this, $this);
      $this->getServer ()->getCommandMap ()->register ('discord3682', new class () extends PluginCommand
      {

        public function __construct ()
        {
          parent::__construct ('check', Check::getInstance ());

          $this->setAliases (['수표']);
        }

        public function execute (CommandSender $sender, string $label, array $args) : void
        {
          if ($sender instanceof Player)
          {
            if (!isset ($args [0]))
            {
              Check::msg ($sender, '값을 입력하여 주십시오.');
            }else
            {
              if (!isset ($args [1]))
                $args [1] = 1;

              if (is_numeric ($args [0]) and is_numeric ($args [1]))
              {
                $args [0] = abs ($args [0]);
                $args [1] = abs ($args [1]);
                $price = $args [0] * $args [1];

                if (EconomyAPI::getInstance ()->myMoney ($sender) >= $price)
                {
                  $check = Check::getCheck ($args [0], $args [1]);

                  if ($sender->getInventory ()->canAddItem ($check))
                  {
                    $sender->getInventory ()->addItem ($check);
                    EconomyAPI::getInstance ()->reduceMoney ($sender, $price);
                    Check::msg ($sender, '수표를 구매하셨습니다.');
                  }else
                  {
                    Check::msg ($sender, '인벤토리 공간이 부족합니다.');
                  }
                }else
                {
                  Check::msg ($sender, '돈이 부족합니다.');
                }
              }else
              {
                Check::msg ($sender, '숫자로 입력하여 주십시오.');
              }
            }
          }
        }
      });
    }else
    {
      $this->getServer ()->getLogger ()->critical ('[Check] EconomyAPI 플러그인이 없습니다.');
      $this->getServer ()->getPluginManager ()->disablePlugin ($this);
    }
  }

  public function onLoad () : void
  {
    self::setInstance ($this);
  }

  public function onPlayerInteract (PlayerInteractEvent $ev) : void
  {
    $player = $ev->getPlayer ();
    $item = $ev->getItem ();

    if (($entry = $item->getNamedTagEntry ('check')) instanceof IntTag)
    {
      if (!$player->isSneaking ())
      {
        Check::msg ($player, '수표를 사용하셨습니다.');
        EconomyAPI::getInstance ()->addMoney ($player, $entry->getValue ());
        $item->setCount ($item->getCount () - 1);
        $player->getInventory ()->setItemInHand ($item);
      }else
      {
        $player->sendForm (new class ($player, $item) implements Form
        {

          private $player;
          private $item;

          public function __construct (Player $player, Item $item)
          {
            $this->player = $player;
            $this->item = $item;
          }

          public function jsonSerialize () : array
          {
            $player = $this->player;
            $item = $this->item;
            $all = 0;

        		foreach ($player->getInventory ()->all ($item) as $slot => $content)
            {
        			if ($slot < $player->getInventory ()->getSize ())
              {
                $all += $content->getCount ();
        			}
        		}

            return [
              'type' => 'custom_form',
              'title' => '§l§0수표를 사용합니다.',
              'content' => [
                [
                  'type' => 'label',
                  'text' => "\n" . '§r§f' . $item->getCustomName () . "\n" . '§r§f보유한 수표 수 : ' . $all
                ],

                [
                  'type' => 'input',
                  'text' => ' ',
                  'placeholder' => '§r§7터치하여 입력'
                ]
              ]
            ];
          }

          public function handleResponse (Player $player, $data) : void
          {
            if (!isset ($data [1]) or $data [1] === null)
            {
              Check::msg ($player, '빈칸을 채워주십시오.');
            }else
            {
              if (is_numeric ($data [1]))
              {
                $data [1] = abs ($data [1]);
                $item = $this->item;
                $item->setCount ($data [1]);

                if ($player->getInventory ()->contains ($item))
                {
                  Check::msg ($player, '수표를 사용하셨습니다.');
                  EconomyAPI::getInstance ()->addMoney ($player, ($item->getNamedTagEntry ('check')->getValue () * $data [1]));

                  $player->getInventory ()->removeItem ($item);
                }else
                {
                  Check::msg ($player, '수표가 부족합니다.');
                }
              }else
              {
                Check::msg ($player, '숫자로 입력하여 주십시오.');
              }
            }
          }
        });
      }
    }
  }

  public static function msg ($player, string $msg) : void
  {
    $player->sendMessage (self::CHECK . $msg);
  }

  public static function getCheck (float $amount, int $count = 1) : Item
  {
    $item = Item::get (339, 0, $count);
    $item->setCustomName ('§r§f수표 : ' . $amount);
    $item->setLore (['터치하여 수표를 사용합니다.']);
    $item->setNamedTagEntry (new IntTag ('check', $amount));

    return $item;
  }
}
