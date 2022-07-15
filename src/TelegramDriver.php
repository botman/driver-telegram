<?php

namespace BotMan\Drivers\Telegram;

use BotMan\Drivers\Telegram\Exceptions\TelegramConnectionException;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\Drivers\Telegram\Extensions\User;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Drivers\Events\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Messages\Attachments\Location;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\Telegram\Exceptions\TelegramException;

class TelegramDriver extends HttpDriver
{
    const DRIVER_NAME = 'Telegram';
    const API_URL = 'https://api.telegram.org/bot';
    const FILE_API_URL = 'https://api.telegram.org/file/bot';
    const LOGIN_EVENT = 'telegram_login';
    const GENERIC_EVENTS = [
        'new_chat_members',
        'left_chat_member',
        'new_chat_title',
        'new_chat_photo',
        'group_chat_created'
    ];

    protected $endpoint = 'sendMessage';

    protected $messages = [];

    /** @var Collection */
    protected $queryParameters;

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));

        $message = $this->payload->get('message');
        if (empty($message)) {
            $message = $this->payload->get('edited_message');
        }
        if (empty($message)) {
            $message = $this->payload->get('channel_post');
            $message['from'] = ['id' => 0];
        }
        $this->event = Collection::make($message);
        $this->config = Collection::make($this->config->get('telegram'));
        $this->queryParameters = Collection::make($request->query);
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return User
     * @throws TelegramException
     * @throws TelegramConnectionException
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $parameters = [
            'chat_id' => $matchingMessage->getRecipient(),
            'user_id' => $matchingMessage->getSender(),
        ];

        if ($this->config->get('throw_http_exceptions')) {
            $response = $this->postWithExceptionHandling($this->buildApiUrl('getChatMember'), [], $parameters);
        } else {
            $response = $this->http->post($this->buildApiUrl('getChatMember'), [], $parameters);
        }

        $responseData = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) {
            throw new TelegramException('Error retrieving user info: '.$responseData['description']);
        }

        $userData = Collection::make($responseData['result']['user']);

        return new User(
            $userData->get('id'),
            $userData->get('first_name'),
            $userData->get('last_name'),
            $userData->get('username'),
            $responseData['result']
        );
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        $noAttachments = $this->event->keys()->filter(function ($key) {
            return in_array($key, ['audio', 'voice', 'video', 'photo', 'location', 'contact', 'document']);
        })->isEmpty();

        return $noAttachments && (! is_null($this->event->get('from')) || ! is_null($this->payload->get('callback_query'))) && ! is_null($this->payload->get('update_id'));
    }

    /**
     * @return bool
     */
    public function hasMatchingEvent()
    {
        $event = false;

        if ($this->isValidLoginRequest()) {
            $event = new GenericEvent($this->queryParameters->except('hash'));
            $event->setName(self::LOGIN_EVENT);
        }

        foreach (self::GENERIC_EVENTS as $genericEvent) {
            if ($this->event->has($genericEvent)) {
                $event = new GenericEvent($this->event->get($genericEvent));
                $event->setName($genericEvent);

                return $event;
            }
        }

        return $event;
    }

    /**
     * Check if the query parameters contain information about a
     * valid Telegram login request.
     *
     * @return bool
     */
    protected function isValidLoginRequest()
    {
        $check_hash = (string)$this->queryParameters->get('hash');

        // Get sorted array of values
        $check = $this->queryParameters
            ->except('hash')
            ->map(function ($value, $key) {
                return $key.'='.$value;
            })
            ->values()
            ->sort();
        $check_string = implode("\n", $check->toArray());

        $secret = hash('sha256', $this->config->get('token'), true);
        $hash = hash_hmac('sha256', $check_string, $secret);

        if (strcmp($hash, $check_hash) !== 0) {
            return false;
        }
        if ((time() - $this->queryParameters->get('auth_date')) > 86400) {
            return false;
        }

        return true;
    }

    /**
     * This hide the inline keyboard, if is an interactive message.
     */
    public function messagesHandled()
    {
        $callback = $this->payload->get('callback_query');
        $hideInlineKeyboard = $this->config->get('hideInlineKeyboard', true);

        if ($callback !== null && $hideInlineKeyboard) {
            $callback['message']['chat']['id'];
            $this->removeInlineKeyboard(
                $callback['message']['chat']['id'],
                $callback['message']['message_id']
            );
        }
    }

    /**
     * @param  \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        if ($this->payload->get('callback_query') !== null) {
            $callback = Collection::make($this->payload->get('callback_query'));

            return Answer::create($callback->get('data'))
                ->setInteractiveReply(true)
                ->setMessage($message)
                ->setValue($callback->get('data'));
        }

        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load Telegram messages.
     */
    public function loadMessages()
    {
        if ($this->payload->get('callback_query') !== null) {
            $callback = Collection::make($this->payload->get('callback_query'));

            $messages = [
                new IncomingMessage(
                    $callback->get('data'),
                    $callback->get('from')['id'],
                    $callback->get('message')['chat']['id'],
                    $callback->get('message')
                ),
            ];
        } elseif ($this->isValidLoginRequest()) {
            $messages = [
                new IncomingMessage('', $this->queryParameters->get('id'), $this->queryParameters->get('id'), $this->queryParameters),
            ];
        } else {
            $event = $this->event->all();

            $messages = [
                new IncomingMessage(
                    $this->event->get('text'),
                    isset($event['from']['id']) ? $event['from']['id'] : null,
                    isset($event['chat']['id']) ? $event['chat']['id'] : null,
                    $this->event
                ),
            ];
        }

        $this->messages = $messages;
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function types(IncomingMessage $matchingMessage)
    {
        $parameters = [
            'chat_id' => $matchingMessage->getRecipient(),
            'action' => 'typing',
        ];

        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl('sendChatAction'), [], $parameters);
        }
        return $this->http->post($this->buildApiUrl('sendChatAction'), [], $parameters);
    }

    /**
     * Convert a Question object into a valid
     * quick reply response object.
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $replies = Collection::make($question->getButtons())->map(function ($button) {
            return [
                array_merge([
                    'text' => (string) $button['text'],
                    'callback_data' => (string) $button['value'],
                ], $button['additional']),
            ];
        });

        return $replies->toArray();
    }

    /**
     * Removes the inline keyboard from an interactive
     * message.
     * @param  int $chatId
     * @param  int $messageId
     * @return Response
     */
    private function removeInlineKeyboard($chatId, $messageId)
    {
        $parameters = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_keyboard' => [],
        ];
        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl('editMessageReplyMarkup'), [], $parameters);
        }
        return $this->http->post($this->buildApiUrl('editMessageReplyMarkup'), [], $parameters);
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $this->endpoint = 'sendMessage';

        $recipient = $matchingMessage->getRecipient() === '' ? $matchingMessage->getSender() : $matchingMessage->getRecipient();
        $defaultAdditionalParameters = $this->config->get('default_additional_parameters', []);
        $parameters = array_merge_recursive([
            'chat_id' => $recipient,
        ], $additionalParameters + $defaultAdditionalParameters);

        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            $parameters['text'] = $message->getText();
            $parameters['reply_markup'] = json_encode([
                'inline_keyboard' => $this->convertQuestion($message),
            ], true);
        } elseif ($message instanceof OutgoingMessage) {
            if ($message->getAttachment() !== null) {
                $attachment = $message->getAttachment();
                $parameters['caption'] = $message->getText();
                if ($attachment instanceof Image) {
                    if (strtolower(pathinfo($attachment->getUrl(), PATHINFO_EXTENSION)) === 'gif') {
                        $this->endpoint = 'sendDocument';
                        $parameters['document'] = $attachment->getUrl();
                    } else {
                        $this->endpoint = 'sendPhoto';
                        $parameters['photo'] = $attachment->getUrl();
                    }
                    // If has a title, overwrite the caption
                    if ($attachment->getTitle() !== null) {
                        $parameters['caption'] = $attachment->getTitle();
                    }
                } elseif ($attachment instanceof Video) {
                    $this->endpoint = 'sendVideo';
                    $parameters['video'] = $attachment->getUrl();
                } elseif ($attachment instanceof Audio) {
                    $this->endpoint = 'sendAudio';
                    $parameters['audio'] = $attachment->getUrl();
                } elseif ($attachment instanceof File) {
                    $this->endpoint = 'sendDocument';
                    $parameters['document'] = $attachment->getUrl();
                } elseif ($attachment instanceof Location) {
                    $this->endpoint = 'sendLocation';
                    $parameters['latitude'] = $attachment->getLatitude();
                    $parameters['longitude'] = $attachment->getLongitude();
                    if (isset($parameters['title'], $parameters['address'])) {
                        $this->endpoint = 'sendVenue';
                    }
                } elseif ($attachment instanceof Contact) {
                    $this->endpoint = 'sendContact';
                    $parameters['phone_number'] = $attachment->getPhoneNumber();
                    $parameters['first_name'] = $attachment->getFirstName();
                    $parameters['last_name'] = $attachment->getLastName();
                    $parameters['user_id'] = $attachment->getUserId();
                    if (null !== $attachment->getVcard()) {
                        $parameters['vcard'] = $attachment->getVcard();
                    }
                }
            } else {
                $parameters['text'] = $message->getText();
            }
        } else {
            $parameters['text'] = $message;
        }

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl($this->endpoint), [], $payload);
        }
        return $this->http->post($this->buildApiUrl($this->endpoint), [], $payload);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('token'));
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'chat_id' => $matchingMessage->getRecipient(),
        ], $parameters);

        if ($this->config->get('throw_http_exceptions')) {
            return $this->postWithExceptionHandling($this->buildApiUrl($endpoint), [], $parameters);
        }
        return $this->http->post($this->buildApiUrl($endpoint), [], $parameters);
    }

    /**
     * Generate the Telegram API url for the given endpoint.
     *
     * @param $endpoint
     * @return string
     */
    protected function buildApiUrl($endpoint)
    {
        return self::API_URL.$this->config->get('token').'/'.$endpoint;
    }

    /**
     * Generate the Telegram File-API url for the given endpoint.
     *
     * @param $endpoint
     * @return string
     */
    protected function buildFileApiUrl($endpoint)
    {
        return self::FILE_API_URL.$this->config->get('token').'/'.$endpoint;
    }

    /**
     * @param $url
     * @param array $urlParameters
     * @param array $postParameters
     * @param array $headers
     * @param bool $asJSON
     * @param int $retryCount
     * @return Response
     * @throws TelegramConnectionException
     */
    private function postWithExceptionHandling(
        $url,
        array $urlParameters = [],
        array $postParameters = [],
        array $headers = [],
        $asJSON = false,
        int $retryCount = 0
    ) {
        $response = $this->http->post($url, $urlParameters, $postParameters, $headers, $asJSON);
        $responseData = json_decode($response->getContent(), true);
        if ($response->isOk() && isset($responseData['ok']) && true ===  $responseData['ok']) {
            return $response;
        } elseif ($this->config->get('retry_http_exceptions') && $retryCount <= $this->config->get('retry_http_exceptions')) {
            $retryCount++;
            if ($response->getStatusCode() == 429 && isset($responseData['retry_after']) && is_numeric($responseData['retry_after'])) {
                usleep($responseData['retry_after'] * 1000000);
            } else {
                $multiplier = $this->config->get('retry_http_exceptions_multiplier')??2;
                usleep($retryCount*$multiplier* 1000000);
            }
            return $this->postWithExceptionHandling($url, $urlParameters, $postParameters, $headers, $asJSON, $retryCount);
        }
        $responseData['description'] = $responseData['description'] ?? 'No description from Telegram';
        $responseData['error_code'] = $responseData['error_code'] ?? 'No error code from Telegram';
        $responseData['parameters'] = $responseData['parameters'] ?? 'No parameters from Telegram';


        $message = "Status Code: {$response->getStatusCode()}\n".
            "Description: ".print_r($responseData['description'], true)."\n".
            "Error Code: ".print_r($responseData['error_code'], true)."\n".
            "Parameters: ".print_r($responseData['parameters'], true)."\n".
            "URL: $url\n".
            "URL Parameters: ".print_r($urlParameters, true)."\n".
            "Post Parameters: ".print_r($postParameters, true)."\n".
            "Headers: ". print_r($headers, true)."\n";

        $message = str_replace($this->config->get('token'), 'TELEGRAM-TOKEN-HIDDEN', $message);
        throw new TelegramConnectionException($message);
    }
}
