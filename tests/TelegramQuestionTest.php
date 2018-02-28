<?php

namespace Tests;

use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\Drivers\Telegram\Extensions\Question;
use PHPUnit_Framework_TestCase;

class TelegramQuestionTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_can_have_buttons_in_multiple_rows()
    {
        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great')->value('great'))
            ->addButton(Button::create('Good')->value('good'));
        
        $buttons = $question->getButtonsToArray();

        $this->assertCount(2, $buttons);
    }

    /** @test */
    public function it_can_have_buttons_in_one_row()
    {
        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great')->value('great'))
            ->addButton(Button::create('Good')->value('good'));

        $buttons = $question->buttonsInSingleRow()->getButtonsToArray();

        $this->assertCount(1, $buttons);
    }
}
