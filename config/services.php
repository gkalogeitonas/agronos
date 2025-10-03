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

    'influxdb' => [
        'url' => env('INFLUXDB_URL'),
        'token' => env('INFLUXDB_TOKEN'),
        'org' => env('INFLUXDB_ORG'),
        'bucket' => env('INFLUXDB_BUCKET'),
        // Enable fake client during tests or when explicitly requested
        'fake' => env('INFLUXDB_FAKE', env('APP_ENV') === 'testing'),
    ],
    'emqx' => [
        // Management API base url (include scheme and host, without path)
        'url' => env('EMQX_API_URL'),
        // Management application id/secret configured for EMQX management API
        'api_key' => env('EMQX_API_KEY', env('MQTT_API_KEY')),
        'secret_key' => env('EMQX_API_SECRET', env('MQTT_API_SECRET')),
        // API base path (most EMQX management APIs are under /api/v5)
        'base_path' => env('EMQX_API_BASE_PATH', '/api/v5'),
        // The authenticator id to use for built_in_database (default name used in EMQX dashboard)
        'authenticator_id' => env('EMQX_AUTHENTICATOR_ID', 'password_based:built_in_database'),
    ],
];
