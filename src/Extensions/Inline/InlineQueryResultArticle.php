<?php

namespace BotMan\Drivers\Telegram\Extensions;

class InlineQueryResultArticle
{
    public $data = [];

    public $type = 'article';

    public function __construct(array $data)
    {
        if (isset($data['message_text'])) {
            $data['input_message_content']['message_text'] = $data['message_text'];
            $data['input_message_content']['parse_mode'] = 'HTML';
        }

        $this->data = $data;
        $this->data['type'] = $this->type;
    }

    public function toJson()
    {
        return json_encode($this->data);
    }
}
