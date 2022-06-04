<?php

namespace BotMan\Drivers\Telegram\Extensions\Attachments;

use BotMan\BotMan\Messages\Attachments\File;
use BotMan\Drivers\Telegram\Extensions\Attachments\Traits\AttachmentException;

class FileException extends File
{
    use AttachmentException;
}
