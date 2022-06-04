<?php

namespace BotMan\Drivers\Telegram\Extensions\Attachments\Traits;

trait AttachmentException
{
    /** @var string */
    protected $exception;

    public function __construct($exception)
    {
        parent::__construct(null);
        $this->exception = $exception;
    }

    public function getException()
    {
        return $this->exception;
    }
}
