<?php

namespace BotMan\Drivers\Telegram\Extensions\Attachments;

use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\Drivers\Telegram\Extensions\Attachments\Traits\AttachmentException;

class VideoException extends Video
{
    use AttachmentException;
}
