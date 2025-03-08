<?php

namespace BotMan\Drivers\Telegram;

use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class TelegramLocationDriver extends TelegramDriver
{
    const DRIVER_NAME = 'TelegramLocation';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from'))
            && ! is_null($this->event->get('location'))
            && $this->isValidToken();
    }

    /**
     * @return bool
     */
    public function hasMatchingEvent()
    {
        return false;
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load Telegram messages.
     */
    public function loadMessages()
    {
        $message = new IncomingMessage(
            Location::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setLocation(new Location(
            $this->event->get('location')['latitude'],
            $this->event->get('location')['longitude'],
            $this->event->get('location')
        ));

        $this->messages = [$message];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}
