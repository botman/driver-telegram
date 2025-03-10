<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram bot token you received after creating
    | the chatbot through Telegram.
    |
    */
    'token' => env('TELEGRAM_TOKEN'),
    'api_secret_token' => env('TELEGRAM_API_SECRET_TOKEN', null),
    'test_environment' => env('TELEGRAM_TEST_ENVIRONMENT', false),
];
