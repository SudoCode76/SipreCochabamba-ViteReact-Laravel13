<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ciudadania_digital' => [
        'base_url' => env('CIUDADANIA_DIGITAL_BASE_URL'),
        'prefix' => env('CIUDADANIA_DIGITAL_PREFIX', ''),
        'client_id' => env('CIUDADANIA_DIGITAL_CLIENT_ID'),
        'secret_id' => env('CIUDADANIA_DIGITAL_SECRET_ID'),
        'redirect_uri' => env('CIUDADANIA_DIGITAL_REDIRECT_URI'),
        'login_redirect_uri' => env('CIUDADANIA_DIGITAL_LOGIN_REDIRECT_URI'),
        'approval_redirect_uri' => env('CIUDADANIA_DIGITAL_APPROVAL_REDIRECT_URI'),
        'logout_redirect_uri' => env('CIUDADANIA_DIGITAL_LOGOUT_REDIRECT_URI'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
        'timeout' => env('CIUDADANIA_DIGITAL_TIMEOUT', 30),
    ],

];
