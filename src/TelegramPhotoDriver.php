<?php

namespace BotMan\Drivers\Telegram;

use BotMan\BotMan\Messages\Attachments\Image;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\Drivers\Telegram\Exceptions\TelegramAttachmentException;
use BotMan\Drivers\Telegram\Extensions\Attachments\ImageException;

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
        return !is_null($this->event->get('from')) && !is_null($this->event->get('photo'));
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
            Image::PATTERN,
            $this->event->get('from')['id'],
            $this->event->get('chat')['id'],
            $this->event
        );
        $message->setImages($this->getImages());

        $this->messages = [$message];
    }

    /**
     * Retrieve a image from an incoming message.
     * @return array A download for the image file.
     * @throws TelegramAttachmentException
     */
    private function getImages()
    {
        $photos = $this->event->get('photo');
        $caption = $this->event->get('caption');

        if (empty($photos)) {
            return [];
        }

        // Order by size in descending order
        usort($photos, function ($a, $b) {
            $aSize = $a['width'] * $a['height'];
            $bSize = $b['width'] * $b['height'];

            if ($aSize == $bSize) {
                return 0;
            }

            return ($aSize > $bSize) ? -1 : 1;
        });

        $photo = reset($photos);

        $response = $this->http->get($this->buildApiUrl('getFile'), [
            'file_id' => $photo['file_id'],
        ]);

        $responseData = json_decode($response->getContent());

        if ($response->getStatusCode() !== 200) {
            return [new ImageException($responseData->description)];
        }

        $url = $this->buildFileApiUrl($responseData->result->file_path);

        return [(new Image($url, $photo))->title($caption)];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }
}
