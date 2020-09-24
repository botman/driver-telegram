<?php

namespace BotMan\Drivers\Telegram;

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Attachments\Contact;

class TelegramContactDriver extends TelegramDriver
{
    const DRIVER_NAME = 'TelegramContact';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from')) && ! is_null($this->event->get('contact'));
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
        $message = new IncomingMessage(Contact::PATTERN, $this->event->get('from')['id'], $this->event->get('chat')['id'], $this->event);
        $message->setContact(new Contact(
            $this->event->get('contact')['phone_number'] ?? '',
            $this->event->get('contact')['first_name'] ?? '',
            $this->event->get('contact')['last_name'] ?? '',
            $this->event->get('contact')['user_id'],
            $this->event->get('contact')['vcard'] ?? ''
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
