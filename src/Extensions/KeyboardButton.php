<?php

namespace BotMan\Drivers\Telegram\Extensions;

use Illuminate\Support\Collection;

class KeyboardButton implements \JsonSerializable
{
    protected $text;

    protected $url;

    protected $callbackData;

    protected $requestContact = false;

    protected $requestLocation = false;

    public static function create($text)
    {
        return new self($text);
    }

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function url($url)
    {
        $this->url = $url;

        return $this;
    }

    public function callbackData($callbackData)
    {
        $this->callbackData = $callbackData;

        return $this;
    }

    /**
     * Specify data which should be serialized to JSON.
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return Collection::make([
            'url' => $this->url,
            'callback_data' => $this->callbackData,
            'text' => $this->text,
        ])->filter()->toArray();
    }
}
