<?php

namespace BotMan\Drivers\Telegram;

class TelegramInlineQueryDriver extends TelegramDriver
{
    const DRIVER_NAME = 'TelegramInlineQuery';

    /**
     * @var array
     */
    protected static $listeners = [];

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->payload->get('inline_query'));
    }

    /**
     * @return bool
     */
    public function hasMatchingEvent()
    {
        return false;
    }

    /**
     * Load Telegram messages.
     */
    public function loadMessages()
    {
        $resp = $this->sendPayload($this->buildServicePayloadInline());

        return $resp;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }

    /*
     * Preparing results for response.
     */
    public function buildServicePayloadInline()
    {
        $this->endpoint = 'answerInlineQuery';

        $inline_query = $this->payload->get('inline_query');

        $data = ['inline_query_id' => $inline_query['id'], 'results' => '[]'];

        if (isset($this->config['cache_time'])) {
            $data['cache_time'] = $this->config['cache_time'];
        }

        foreach (self::$listeners as $listener) {
            $results = $listener($inline_query);

            if (is_array($results)) {
                $data['results'] = '[' . implode(',', $results) . ']';
                break;
            }
        }

        return $data;
    }

    /*
     * Adding listeners to prepare results for inline request.
     */
    public static function listen($closure)
    {
        array_unshift(self::$listeners, $closure);

        self::$listeners = array_unique(self::$listeners);
    }
}
