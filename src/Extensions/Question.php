<?php

namespace BotMan\Drivers\Telegram\Extensions;

use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use Illuminate\Support\Collection;

class Question extends BotManQuestion
{

    protected $buttonsInSingleRow = false;

    public function getButtonsToArray()
    {
        $replies = Collection::make($this->getButtons())->map(function ($button) {
            return [
                array_merge([
                    'text'          => (string)$button['text'],
                    'callback_data' => (string)$button['value'],
                ], $button['additional']),
            ];
        });

        if ($this->buttonsInSingleRow) {

            return [$replies->flatten(1)->toArray()];
        }

        return $replies->toArray();
    }

    public function buttonsInSingleRow()
    {
        $this->buttonsInSingleRow = true;

        return $this;
    }

    public function buttonsInMultipleRows()
    {
        $this->buttonsInSingleRow = false;

        return $this;
    }

}