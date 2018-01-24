<?php

namespace Tests;

use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;

class TelegramKeyboardTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_can_be_created()
    {
        $keyboard = Keyboard::create();
        $this->assertInstanceOf(Keyboard::class, $keyboard);
    }

    /** @test */
    public function it_can_have_multiple_rows()
    {
        $keyboard = Keyboard::create()->addRow();
        $keyboardArray = $keyboard->toArray();
        $replyMarkup = json_decode($keyboardArray['reply_markup'], true);

        $this->assertCount(1, $replyMarkup[Keyboard::TYPE_INLINE]);

        $keyboard = Keyboard::create()->addRow()->addRow();
        $keyboardArray = $keyboard->toArray();
        $replyMarkup = json_decode($keyboardArray['reply_markup'], true);

        $this->assertCount(2, $replyMarkup[Keyboard::TYPE_INLINE]);
    }

    /** @test */
    public function it_can_have_multiple_buttons_in_each_row()
    {
        $keyboard = Keyboard::create()->addRow(
            KeyboardButton::create('test')
        );
        $keyboardArray = $keyboard->toArray();
        $replyMarkup = json_decode($keyboardArray['reply_markup'], true);

        $this->assertCount(1, $replyMarkup[Keyboard::TYPE_INLINE][0]);

        $keyboard = Keyboard::create()->addRow(
            KeyboardButton::create('test'),
            KeyboardButton::create('test')
        );
        $keyboardArray = $keyboard->toArray();
        $replyMarkup = json_decode($keyboardArray['reply_markup'], true);

        $this->assertCount(2, $replyMarkup[Keyboard::TYPE_INLINE][0]);
    }
}
