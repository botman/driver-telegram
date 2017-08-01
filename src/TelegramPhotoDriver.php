<?php

namespace BotMan\Drivers\Telegram;

use BotMan\BotMan\Messages\Attachments\Image;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\Telegram\Exceptions\TelegramAttachmentException;

class TelegramPhotoDriver extends TelegramDriver
{
    const DRIVER_NAME = 'TelegramPhoto';

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('from')) && ! is_null($this->event->get('photo'));
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        $message = new IncomingMessage(Image::PATTERN, $this->event->get('from')['id'], $this->event->get('chat')['id'],
            $this->event);
        $message->setImages($this->getImages());

        return [$message];
    }

    /**
     * Retrieve a image from an incoming message.
     * @return array A download for the image file.
     * @throws TelegramAttachmentException
     */
    private function getImages()
    {
        $photos = $this->event->get('photo');
        $largetstPhoto = array_pop($photos);
        $response = $this->http->get('https://api.telegram.org/bot'.$this->config->get('token').'/getFile', [
            'file_id' => $largetstPhoto['file_id'],
        ]);

        $path = json_decode($response->getContent());

        if (isset($path->result)) {
            $url = 'https://api.telegram.org/file/bot'.$this->config->get('token').'/'.$path->result->file_path;
        } else {
            throw new TelegramAttachmentException('File too large (max 20 MB).');
        }

        return [new Image($url, $largetstPhoto)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}
