<?php

namespace BotMan\Drivers\Telegram\Extensions\Attachments;

use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\Drivers\Telegram\Extensions\Attachments\Traits\AttachmentException;

class ImageException extends Image
{
    use AttachmentException;
}
