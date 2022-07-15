<?php

namespace BotMan\Drivers\Telegram\Extensions;

use Illuminate\Support\Collection;

/**
 * Class KeyboardButton.
 */
class KeyboardButton implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $text;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $callbackData;

    /**
     * @var bool
     */
    protected $requestContact = false;

    /**
     * @var bool
     */
    protected $requestLocation = false;

    /**
     * @param $text
     * @return KeyboardButton
     */
    public static function create($text)
    {
        return new self($text);
    }

    /**
     * KeyboardButton constructor.
     * @param $text
     */
    public function __construct($text)
    {
        $this->text = $text;
    }

    /**
     * @param $url
     * @return $this
     */
    public function url($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param $callbackData
     * @return $this
     */
    public function callbackData($callbackData)
    {
        $this->callbackData = $callbackData;

        return $this;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function requestContact($active = true)
    {
        $this->requestContact = $active;

        return $this;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function requestLocation($active = true)
    {
        $this->requestLocation = $active;

        return $this;
    }

    /**
     * Specify data which should be serialized to JSON.
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return Collection::make([
            'url' => $this->url,
            'callback_data' => $this->callbackData,
            'request_contact' => $this->requestContact,
            'request_location' => $this->requestLocation,
            'text' => $this->text,
        ])->filter(function ($value, $key) {
            return !($value === false || is_null($value));
        })->toArray();
    }
}
