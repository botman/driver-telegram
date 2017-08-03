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
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        $message = new IncomingMessage(Audio::PATTERN, $this->event->get('from')['id'], $this->event->get('chat')['id'],
            $this->event);
        $message->setAudio($this->getAudio());

        return [$message];
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
        $response = $this->http->get('https://api.telegram.org/bot'.$this->config->get('token').'/getFile', [
            'file_id' => $audio['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            throw new TelegramAttachmentException($responseData->description);
        }

        $url = 'https://api.telegram.org/file/bot'.$this->config->get('token').'/'.$responseData->result->file_path;

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
