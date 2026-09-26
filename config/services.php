<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway (stub)
    |--------------------------------------------------------------------------
    |
    | Provider WA belum dipilih. Nilai-nilai ini hanya placeholder sehingga
    | job dapat membedakan konfigurasi yang belum tersedia tanpa memilih
    | Fonnte/Wablas/WA Business API di task ini.
    |
    */
    'wa' => [
        'url' => env('WA_GATEWAY_URL'),
        'token' => env('WA_GATEWAY_TOKEN'),
        'device_id' => env('WA_GATEWAY_DEVICE_ID'),
    ],

];
