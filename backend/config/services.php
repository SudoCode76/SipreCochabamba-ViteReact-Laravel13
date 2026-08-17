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

    'repository' => [
        'endpoint' => env('REPOSITORY_API_URL', 'https://repositoriogamcdev.cochabamba.bo/api/v1/repository/sipre'),
        'system_id' => env('REPOSITORY_SYSTEM_ID', '00e8a371-8927-49b6-a6aa-0c600e4b6a19'),
        'collector' => env('REPOSITORY_COLLECTOR', 'SISTEMA SIPRE'),
        'timeout' => env('REPOSITORY_TIMEOUT', 60),
        'connect_timeout' => env('REPOSITORY_CONNECT_TIMEOUT', 10),
        'max_pdf_download_bytes' => env('REPOSITORY_MAX_PDF_DOWNLOAD_BYTES', 25 * 1024 * 1024),
    ],

];
