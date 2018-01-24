<?php

namespace BotMan\Drivers\Telegram\Extensions;

use Illuminate\Support\Collection;

/**
 * Class Keyboard.
 */
class Keyboard
{
    const TYPE_KEYBOARD = 'keyboard';
    const TYPE_INLINE = 'inline_keyboard';

    protected $oneTimeKeyboard = false;
    protected $resizeKeyboard = false;

    /**
     * @var array
     */
    protected $rows = [];

    /**
     * @param string $type
     * @return Keyboard
     */
    public static function create($type = self::TYPE_INLINE)
    {
        return new self($type);
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
     * @param bool $active
     * @return $this
     */
    public function oneTimeKeyboard($active = true)
    {
        $this->oneTimeKeyboard = $active;

        return $this;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function resizeKeyboard($active = true)
    {
        $this->resizeKeyboard = $active;

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
            'reply_markup' => json_encode(Collection::make([
                $this->type => $this->rows,
                'one_time_keyboard' => $this->oneTimeKeyboard,
                'resize_keyboard' => $this->resizeKeyboard,
            ])->filter()),
        ];
    }
}
