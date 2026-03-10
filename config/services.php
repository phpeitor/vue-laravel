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
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'hsm' => [
        'base_url' => env('HSM_STATUS_BASE_URL', 'http://127.0.0.1:4502'),
    ],
    'chat' => [
        'thread_base_url' => env('CHAT_THREAD_BASE_URL'),
    ],
    'whatsapp' => [
        'new_url'  => env('WHATSAPP_NEW_URL'),
        'send_url' => env('WHATSAPP_SEND_URL'),
        'sync_url' => env('WHATSAPP_SYNC_URL'),
    ],
];
