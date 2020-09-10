<?php

namespace BotMan\Drivers\Telegram;

use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\Telegram\Exceptions\TelegramAttachmentException;

class TelegramAudioDriver extends TelegramDriver
{
    const DRIVER_NAME = 'TelegramAudio';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from')) && (! is_null($this->event->get('audio')) || ! is_null($this->event->get('voice')));
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
            Audio::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setAudio($this->getAudio());

        $this->messages = [$message];
    }

    /**
     * Retrieve a image from an incoming message.
     * @return array A download for the audio file.
     * @throws TelegramAttachmentException
     */
    private function getAudio()
    {
        $audio = $this->event->get('audio');
        if ($this->event->has('voice')) {
            $audio = $this->event->get('voice');
        }
        $response = $this->http->get($this->buildApiUrl('getFile'), [
            'file_id' => $audio['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            throw new TelegramAttachmentException('Error retrieving file url: '.$responseData->description);
        }

        $url = $this->buildFileApiUrl($responseData->result->file_path);

        return [new Audio($url, $audio)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}
