<?php

namespace BotMan\Drivers\Telegram\Extensions;


/**
 * Class Keyboard
 * @package BotMan\Drivers\Telegram\Extensions
 */
class Keyboard
{
    const TYPE_KEYBOARD = 'keyboard';
    const TYPE_INLINE = 'inline_keyboard';

    /**
     * @var array
     */
    protected $rows = [];

    /**
     * @return Keyboard
     */
    public static function create()
    {
        return new self;
    }

    /**
     * Keyboard constructor.
     * @param string $type
     */
    public function __construct($type = self::TYPE_INLINE)
    {
        $this->type = $type;
    }

    /**
     * @param $type
     * @return $this
     */
    public function type($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Add a new row to the Keyboard.
     * @param KeyboardButton[] $buttons
     * @return Keyboard
     */
    public function addRow(KeyboardButton ...$buttons)
    {
        $this->rows[] = $buttons;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'reply_markup' => json_encode([
                $this->type => $this->rows
            ])
        ];
    }

}