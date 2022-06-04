<?php

namespace BotMan\Drivers\Telegram\Extensions\Attachments;

use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\Drivers\Telegram\Extensions\Attachments\Traits\AttachmentException;

class AudioException extends Audio
{
    use AttachmentException;
}
