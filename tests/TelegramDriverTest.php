<?php

namespace Tests;

use BotMan\Drivers\Telegram\Exceptions\TelegramConnectionException;
use Mockery as m;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Users\User;
use PHPUnit_Framework_TestCase;
use Illuminate\Support\Collection;
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
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\Drivers\Telegram\Exceptions\TelegramException;

class TelegramDriverTest extends PHPUnit_Framework_TestCase
{
    protected $telegramConfig = [
        'telegram' => [
            'token' => 'TELEGRAM-BOT-TOKEN',
        ],
    ];

    protected $telegramConfigWithDefaultAdditionalParameters = [
        'telegram' => [
            'token' => 'TELEGRAM-BOT-TOKEN',
            'default_additional_parameters' => [
                'foo' => 'bar'
            ]
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
    public function it_returns_the_message_object_by_reference()
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
        $messages = $driver->getMessages();
        $hash = spl_object_hash($messages[0]);
        $this->assertSame($hash, spl_object_hash($driver->getMessages()[0]));
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
                'new_chat_members' => [
                    [
                        'id' => '456',
                        'first_name' => 'Marcel',
                        'last_name' => 'Pociot',
                        'username' => 'mpociot',
                    ],
                ],
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('new_chat_members', $event->getName());
        $this->assertSame('Marcel', $event->getPayload()[0]['first_name']);
    }

    /** @test */
    public function it_calls_group_chat_created_event()
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
                'group_chat_created' => true,
            ],
        ]);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('group_chat_created', $event->getName());
        $this->assertTrue($event->getPayload());
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
    public function it_calls_telegram_login_event()
    {
        $token = 'randomtoken';

        $queryParameters = [
            'id' => '12345',
            'first_name' => 'Marcel',
            'last_name' => 'Pociot',
            'username' => 'marcelpociot',
            'photo_url' => 'https://some/picture.jpg',
            'auth_date' => time(),
        ];

        // Calculate hash
        $check = Collection::make($queryParameters)
            ->except('hash')
            ->map(function ($value, $key) {
                return $key.'='.$value;
            })
            ->values()
            ->sort();
        $check_string = implode("\n", $check->toArray());

        $secret = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $check_string, $secret);

        $queryParameters['hash'] = $hash;

        $request = new Request($queryParameters);

        $driver = new TelegramDriver($request, [
            'telegram' => [
                'token' => $token,
            ],
        ], m::mock(Curl::class));

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame(TelegramDriver::LOGIN_EVENT, $event->getName());
        $this->assertSame('12345', $event->getPayload()['id']);
        $this->assertSame('Marcel', $event->getPayload()['first_name']);
        $this->assertSame('Pociot', $event->getPayload()['last_name']);
        $this->assertSame('marcelpociot', $event->getPayload()['username']);
        $this->assertSame('https://some/picture.jpg', $event->getPayload()['photo_url']);
    }

    /** @test */
    public function it_does_not_call_telegram_login_event_with_outdated_auth_date()
    {
        $token = 'randomtoken';

        $queryParameters = [
            'id' => '12345',
            'first_name' => 'Marcel',
            'last_name' => 'Pociot',
            'username' => 'marcelpociot',
            'photo_url' => 'https://some/picture.jpg',
            'auth_date' => time() - 90000,
        ];

        // Calculate hash
        $check = Collection::make($queryParameters)
            ->except('hash')
            ->map(function ($value, $key) {
                return $key.'='.$value;
            })
            ->values()
            ->sort();
        $check_string = implode("\n", $check->toArray());

        $secret = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $check_string, $secret);

        $queryParameters['hash'] = $hash;

        $request = new Request($queryParameters);

        $driver = new TelegramDriver($request, [
            'telegram' => [
                'token' => $token,
            ],
        ], m::mock(Curl::class));

        $this->assertFalse($driver->hasMatchingEvent());
    }

    /** @test */
    public function it_does_not_call_telegram_login_event_with_invalid_hash()
    {
        $token = 'randomtoken';

        $queryParameters = [
            'id' => '12345',
            'first_name' => 'Marcel',
            'last_name' => 'Pociot',
            'username' => 'marcelpociot',
            'photo_url' => 'https://some/picture.jpg',
            'auth_date' => time(),
            'hash' => 'kajhsdkjhaskjdh',
        ];

        $request = new Request($queryParameters);

        $driver = new TelegramDriver($request, [
            'telegram' => [
                'token' => $token,
            ],
        ], m::mock(Curl::class));

        $this->assertFalse($driver->hasMatchingEvent());
    }

    /** @test */
    public function telegram_login_event_has_the_correct_chat_id()
    {
        $token = 'randomtoken';

        $queryParameters = [
            'id' => '12345',
            'first_name' => 'Marcel',
            'last_name' => 'Pociot',
            'username' => 'marcelpociot',
            'photo_url' => 'https://some/picture.jpg',
            'auth_date' => time(),
        ];

        // Calculate hash
        $check = Collection::make($queryParameters)
            ->except('hash')
            ->map(function ($value, $key) {
                return $key.'='.$value;
            })
            ->values()
            ->sort();
        $check_string = implode("\n", $check->toArray());

        $secret = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $check_string, $secret);

        $queryParameters['hash'] = $hash;

        $request = new Request($queryParameters);

        $driver = new TelegramDriver($request, [
            'telegram' => [
                'token' => $token,
            ],
        ], m::mock(Curl::class));

        $message = $driver->getMessages();

        $this->assertSame('12345', $message[0]->getSender());
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
    public function it_hides_keyboard_by_default()
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
    public function it_does_not_hide_keyboard_if_configured()
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
        $html->shouldNotReceive('post');

        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $telegramConfig = [
            'telegram' => [
                'token' => 'TELEGRAM-BOT-TOKEN',
                'hideInlineKeyboard' => false
            ],
        ];
        $driver = new TelegramDriver($request, $telegramConfig, $html);

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
    public function it_can_reply_with_default_additional_parameters()
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

        $driver = new TelegramDriver($request, $this->telegramConfigWithDefaultAdditionalParameters, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload('Test', $message));
    }

    /** @test */
    public function it_can_reply_with_default_additional_parameters_and_runtime_additional_parameters()
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
                'baz' => 'foo',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfigWithDefaultAdditionalParameters, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload('Test', $message, [
            'baz' => 'foo',
        ]));
    }

    /** @test */
    public function it_can_reply_with_default_additional_parameters_overriden_by_runtime_additional_parameters()
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
                'foo' => 'baz',
                'baz' => 'foo',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfigWithDefaultAdditionalParameters, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload('Test', $message, [
            'foo' => 'baz',
            'baz' => 'foo',
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
    public function it_does_not_throw_an_exception_when_a_message_cant_be_sent_and_not_configured()
    {
        $response = new Response('{
    "ok": false,
    "error_code": 400,
    "description": "Bad Request: can\'t parse entities: Can\'t find end of Italic entity at byte offset 10"
}', 400);


        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
            'chat_id' => null,
            'parse_mode' => 'MarkdownV2',
            'text' => 'unparsable_string'
        ])->once()->andReturn($response);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($response);

        $driver = new TelegramDriver($request, $this->telegramConfig, $htmlInterface);

        $message = $driver->getMessages()[0];
        $throwable = null;
        try {
            $driver->sendPayload($driver->buildServicePayload(
                \BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('unparsable_string'),
                $message,
                ['parse_mode' => 'MarkdownV2']
            ));
        } catch (\Throwable $t) {
            $throwable = $t;
            throw $t;
        }
        $this->assertNull($throwable);
    }

    /** @test */
    public function it_throws_an_exception_when_a_message_cant_be_sent_and_configured()
    {
        $response = new Response('{
    "ok": false,
    "error_code": 400,
    "description": "Bad Request: can\'t parse entities: Can\'t find end of Italic entity at byte offset 10"
}', 400);


        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
            'chat_id' => null,
            'parse_mode' => 'MarkdownV2',
            'text' => 'unparsable_string'
        ], [], false)->once()->andReturn($response);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($response);

        $configurationWithHttpExceptions = $this->telegramConfig;
        $configurationWithHttpExceptions['telegram']['throw_http_exceptions'] = true;

        $driver = new TelegramDriver($request, $configurationWithHttpExceptions, $htmlInterface);

        $message = $driver->getMessages()[0];
        $throwable = null;
        try {
            $driver->sendPayload($driver->buildServicePayload(
                \BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('unparsable_string'),
                $message,
                ['parse_mode' => 'MarkdownV2']
            ));
        } catch (\Throwable $t) {
            $throwable = $t;
        }
        $this->assertNotNull($throwable);
        $this->assertSame(TelegramConnectionException::class, get_class($throwable));
        $this->assertNotContains($configurationWithHttpExceptions['telegram']['token'], $throwable->getMessage());
        $this->assertContains('TELEGRAM-TOKEN-HIDDEN', $throwable->getMessage());
    }

    /** @test */
    public function it_retries_after_throwing_an_exception_when_a_message_cant_be_sent_and_configured()
    {
        $responseFailed = new Response('', 500);
        $responseSucceeds = new Response('{"ok": true}', 200);

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
            'chat_id' => null,
            'parse_mode' => 'MarkdownV2',
            'text' => 'a message'
        ], [], false)->times(3)->andReturn(clone $responseFailed, clone $responseFailed, $responseSucceeds);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($responseFailed);

        $configurationWithHttpExceptions = $this->telegramConfig;
        $configurationWithHttpExceptions['telegram']['throw_http_exceptions'] = true;
        $configurationWithHttpExceptions['telegram']['retry_http_exceptions'] = 5;
        $configurationWithHttpExceptions['telegram']['retry_http_exceptions_multiplier'] = 0.1; // to keep the tests going at a reasonable speed.

        $driver = new TelegramDriver($request, $configurationWithHttpExceptions, $htmlInterface);


        $message = $driver->getMessages()[0];
        $throwable = null;
        $duration = 0.0;
        try {
            $start = microtime(true);
            $driver->sendPayload($driver->buildServicePayload(
                \BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('a message'),
                $message,
                ['parse_mode' => 'MarkdownV2']
            ));
            $duration = microtime(true) - $start;
        } catch (\Throwable $t) {
            $throwable = $t;
        }
        $this->assertNull($throwable);
        $this->assertGreaterThanOrEqual(0.1+0.2, $duration);
    }

    /** @test */
    public function it_respects_the_back_off_in_a_429_response()
    {
        $response429 = new Response('{"retry_after": 1.5}', 429);
        $responseSucceeds = new Response('{"ok": true}', 200);

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendMessage', [], [
            'chat_id' => null,
            'parse_mode' => 'MarkdownV2',
            'text' => 'a message'
        ], [], false)->times(2)->andReturn($response429, $responseSucceeds);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($response429);

        $configurationWithHttpExceptions = $this->telegramConfig;
        $configurationWithHttpExceptions['telegram']['throw_http_exceptions'] = true;
        $configurationWithHttpExceptions['telegram']['retry_http_exceptions'] = 5;

        $driver = new TelegramDriver($request, $configurationWithHttpExceptions, $htmlInterface);


        $message = $driver->getMessages()[0];
        $throwable = null;
        $duration = 0.0;
        try {
            $start = microtime(true);
            $driver->sendPayload($driver->buildServicePayload(
                \BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('a message'),
                $message,
                ['parse_mode' => 'MarkdownV2']
            ));
            $duration = microtime(true) - $start;
        } catch (\Throwable $t) {
            $throwable = $t;
        }
        $this->assertNull($throwable);
        $this->assertGreaterThanOrEqual(1.5, $duration);
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
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create(
            'Test',
            Audio::url('http://image.url/foo.mp3')
        ), $message));
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
    public function it_can_reply_message_objects_with_contact()
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
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendContact', [], [
                'chat_id' => '12345',
                'phone_number' => '0775269856',
                'first_name' => 'Daniele',
                'last_name' => 'Rapisarda',
                'user_id' => '123',
                'caption' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', new Contact('0775269856', 'Daniele', 'Rapisarda', '123')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_contact_with_vcard()
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

        $vcard = 'BEGIN:VCARD
VERSION:4.0
N:Gump;Forrest;;Mr.;
FN:Forrest Gump
ORG:Bubba Gump Shrimp Co.
TITLE:Shrimp Man
PHOTO;MEDIATYPE=image/gif:http://www.example.com/dir_photos/my_photo.gif
TEL;TYPE=work,voice;VALUE=uri:tel:+1-111-555-1212
TEL;TYPE=home,voice;VALUE=uri:tel:+1-404-555-1212
ADR;TYPE=WORK;PREF=1;LABEL="100 Waters Edge\nBaytown\, LA 30314\nUnited States of America":;;100 Waters Edge;Baytown;LA;30314;United States of America
ADR;TYPE=HOME;LABEL="42 Plantation St.\nBaytown\, LA 30314\nUnited States of America":;;42 Plantation St.;Baytown;LA;30314;United States of America
EMAIL:forrestgump@example.com
REV:20080424T195243Z
x-qq:21588891
END:VCARD';

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://api.telegram.org/botTELEGRAM-BOT-TOKEN/sendContact', [], [
                'chat_id' => '12345',
                'phone_number' => '0775269856',
                'first_name' => 'Daniele',
                'last_name' => 'Rapisarda',
                'user_id' => '123',
                'caption' => 'Test',
                'vcard' => $vcard,
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new TelegramDriver($request, $this->telegramConfig, $html);

        $message = $driver->getMessages()[0];
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', new Contact('0775269856', 'Daniele', 'Rapisarda', '123', $vcard)), $message));
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

        $throwable = null;
        try {
            $driver->getUser($driver->getMessages()[0]);
        } catch (\Throwable $t) {
            $throwable = $t;
        }
        $this->assertNotNull($throwable);
        $this->assertSame(TelegramException::class, get_class($throwable));
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
