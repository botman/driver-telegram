<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Users\User;
use PHPUnit_Framework_TestCase;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\Drivers\Telegram\TelegramDriver;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Drivers\Events\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\Drivers\Telegram\Exceptions\TelegramException;

class TelegramDriverTest extends PHPUnit_Framework_TestCase
{
    protected $telegramConfig = [
        'telegram' => [
            'token' => 'TELEGRAM-BOT-TOKEN',
        ],
    ];

    public function tearDown()
    {
        m::close();
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new TelegramDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Telegram', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'event' => [
                'text' => 'bar',
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ]);
        $this->assertTrue($driver->matchesRequest());
    }

    private function getRequest($responseData)
    {
        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        return $request;
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_calls_new_chat_member_event()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Hi Julia',
                'new_chat_member' => [
                    'id' => '456',
                    'first_name' => 'Marcel',
                    'last_name' => 'Pociot',
                    'username' => 'mpociot',
                ],
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('new_chat_member', $event->getName());
        $this->assertSame('Marcel', $event->getPayload()['first_name']);
    }

    /** @test */
    public function it_calls_left_chat_member_event()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Hi Julia',
                'left_chat_member' => [
                    'id' => '456',
                    'first_name' => 'Marcel',
                    'last_name' => 'Pociot',
                    'username' => 'mpociot',
                ],
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('left_chat_member', $event->getName());
        $this->assertSame('Marcel', $event->getPayload()['first_name']);
    }

    /** @test */
    public function it_calls_new_chat_title_event()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Hi Julia',
                'new_chat_title' => 'BotMan Chat',
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('new_chat_title', $event->getName());
        $this->assertSame('BotMan Chat', $event->getPayload());
    }

    /** @test */
    public function it_calls_new_chat_photo_event()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Hi Julia',
                'new_chat_photo' => [
                    'file_id' => 'asdf',
                    'file_size' => 1234,
                    'width' => 160,
                    'height' => 160,
                ],
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('new_chat_photo', $event->getName());
        $this->assertSame('asdf', $event->getPayload()['file_id']);
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ]);
        $this->assertFalse($driver->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
            'entities' => [],
        ]);
        $this->assertSame('from_id', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => 'chat_id',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
            'entities' => [],
        ]);
        $this->assertSame('chat_id', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_detects_users_from_interactive_messages()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'callback_query' => [
                'id' => '11717237123',
                'from' => [
                    'id' => 'from_id',
                ],
                'message' => [
                    'message_id' => '123',
                    'from' => [
                        'id' => 'from_id',
                    ],
                    'chat' => [
                        'id' => 'chat_id',
                    ],
                    'date' => '1480369277',
                    'text' => 'Telegram Text',
                ],
            ],
            'data' => 'FooBar',
        ]);

        $this->assertSame('from_id', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_detects_channels_from_interactive_messages()
    {
        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'callback_query' => [
                'id' => '11717237123',
                'from' => [
                    'id' => 'from_id',
                ],
                'message' => [
                    'message_id' => '123',
                    'from' => [
                        'id' => 'from_id',
                    ],
                    'chat' => [
                        'id' => 'chat_id',
                    ],
                    'date' => '1480369277',
                    'text' => 'Telegram Text',
                ],
            ],
            'data' => 'FooBar',
        ]);

        $this->assertSame('chat_id', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_payload_from_interactive_messages()
    {
        $payload = [
            'message_id' => '123',
            'from' => [
                'id' => 'from_id',
            ],
            'chat' => [
                'id' => 'chat_id',
            ],
            'date' => '1480369277',
            'text' => 'Telegram Text',
        ];

        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'callback_query' => [
                'id' => '11717237123',
                'from' => [
                    'id' => 'from_id',
                ],
                'message' => $payload,
            ],
            'data' => 'FooBar',
        ]);

        $this->assertSame($payload, $driver->getMessages()[0]->getPayload());
    }

    /** @test */
    public function it_can_originate_messages()
    {
        $botman = BotManFactory::create([], new ArrayCache());

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('');

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);
        $botman->say('Test', '12345', $driver);

        $this->assertInstanceOf(TelegramDriver::class, $botman->getDriver());
    }

    /** @test */
    public function it_returns_answer_from_interactive_messages()
    {
        $responseData = [
            'update_id' => '1234567890',
            'callback_query' => [
                'id' => '11717237123',
                'from' => [
                    'id' => 'from_id',
                ],
                'message' => [
                    'message_id' => '123',
                    'from' => [
                        'id' => 'from_id',
                    ],
                    'chat' => [
                        'id' => 'chat_id',
                    ],
                    'date' => '1480369277',
                    'text' => 'Telegram Text',
                ],
                'data' => 'FooBar',
            ],
        ];

        $html = m::mock(Curl::class);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $this->assertSame('FooBar', $driver->getConversationAnswer($message)->getText());
        $this->assertSame($message, $driver->getConversationAnswer($message)->getMessage());
    }

    /** @test */
    public function it_hides_keyboard()
    {
        $responseData = [
            'update_id' => '1234567890',
            'callback_query' => [
                'id' => '11717237123',
                'from' => [
                    'id' => 'from_id',
                ],
                'message' => [
                    'message_id' => '123',
                    'from' => [
                        'id' => 'from_id',
                    ],
                    'chat' => [
                        'id' => '1234',
                    ],
                    'date' => '1480369277',
                    'text' => 'Telegram Text',
                ],
                'data' => 'FooBar',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/editMessageReplyMarkup', [], [
                'chat_id' => '1234',
                'message_id' => '123',
                'inline_keyboard' => [],
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $driver->messagesHandled();
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload('Test', $message));
    }

    /** @test */
    public function it_can_reply_questions()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great')->value('great'))
            ->addButton(Button::create('Good')->value('good'));

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'How are you doing?',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Great',
                                'callback_data' => 'great',
                            ],
                        ],
                        [
                            [
                                'text' => 'Good',
                                'callback_data' => 'good',
                            ],
                        ],
                    ],
                ], true),
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_questions_with_additional_button_parameters()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great')->value('great')->additionalParameters(['foo' => 'bar']))
            ->addButton(Button::create('Good')->value('good'));

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'How are you doing?',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Great',
                                'callback_data' => 'great',
                                'foo' => 'bar',
                            ],
                        ],
                        [
                            [
                                'text' => 'Good',
                                'callback_data' => 'good',
                            ],
                        ],
                    ],
                ], true),
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_with_additional_parameters()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'Test',
                'foo' => 'bar',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload('Test', $message, [
            'foo' => 'bar',
        ]));
    }

    /** @test */
    public function it_is_configured()
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('');
        $htmlInterface = m::mock(Curl::class);

        $driver = new TelegramDriver($request, $this->telegramConfig, $htmlInterface);

        $this->assertTrue($driver->isConfigured());

        $driver = new TelegramDriver($request, [
            'telegram' => [
                'token' => null,
            ],
        ], $htmlInterface);

        $this->assertFalse($driver->isConfigured());

        $driver = new TelegramDriver($request, [], $htmlInterface);

        $this->assertFalse($driver->isConfigured());
    }

    /** @test */
    public function it_can_reply_message_objects()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
                'chat_id' => '12345',
                'text' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test'), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_image()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendPhoto', [], [
                'chat_id' => '12345',
                'photo' => 'http://image.url/foo.png',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', Image::url('http://image.url/foo.png')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_gif_image()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendDocument', [], [
                'chat_id' => '12345',
                'document' => 'http://image.url/foo.gif',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', Image::url('http://image.url/foo.gif')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_video()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendVideo', [], [
                'chat_id' => '12345',
                'video' => 'http://image.url/foo.mp4',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', Video::url('http://image.url/foo.mp4')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_audio()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendAudio', [], [
                'chat_id' => '12345',
                'audio' => 'http://image.url/foo.mp3',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test',
            Audio::url('http://image.url/foo.mp3')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_file()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendDocument', [], [
                'chat_id' => '12345',
                'document' => 'http://image.url/foo.pdf',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', File::url('http://image.url/foo.pdf')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_location()
    {
        $responseData = [
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendLocation', [], [
                'chat_id' => '12345',
                'latitude' => '123',
                'longitude' => '321',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', new Location('123', '321')), $message));
    }

    /** @test */
    public function it_throws_exception_in_get_user()
    {
        $response = new Response('{"ok":false,"error_code":400,"description":"Bad Request: wrong user_id specified"}', 400);

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/bot/getChatMember', [], [
            'chat_id' => '12345',
            'user_id' => 'from_id',
        ])->andReturn($response);

        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ], $htmlInterface);

        try {
            $driver->getUser($driver->getMessages()[0]);
        } catch (\Throwable $t) {
            $this->assertSame(TelegramException::class, get_class($t));
        }
    }

    /** @test */
    public function it_return_the_user()
    {
        $response = new Response('{"ok":true,"result":{"user":{"id":12345,"first_name":"Mario","last_name":"Rossi","username":"MRossi","language_code":"it-IT"},"status":"member"}}');

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/bot/getChatMember', [], [
            'chat_id' => '12345',
            'user_id' => 'from_id',
        ])->andReturn($response);

        $driver = $this->getDriver([
            'update_id' => '1234567890',
            'message' => [
                'message_id' => '123',
                'from' => [
                    'id' => 'from_id',
                ],
                'chat' => [
                    'id' => '12345',
                ],
                'date' => '1480369277',
                'text' => 'Telegram Text',
            ],
        ], $htmlInterface);

        $user = $driver->getUser($driver->getMessages()[0]);

        $this->assertSame(12345, $user->getId());
        $this->assertSame('Mario', $user->getFirstName());
        $this->assertSame('Rossi', $user->getLastName());
        $this->assertSame('MRossi', $user->getUsername());
        $this->assertSame(json_decode('{"user":{"id":12345,"first_name":"Mario","last_name":"Rossi","username":"MRossi","language_code":"it-IT"},"status":"member"}', true), $user->getInfo());

        $this->assertSame('member', $user->getStatus());
        $this->assertSame('it-IT', $user->getLanguageCode());
    }
}
