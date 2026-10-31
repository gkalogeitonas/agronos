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
    'mqtt' => [
        // MQTT broker host and port as reachable from the app (used by devices:simulate --via=mqtt)
        'host' => env('MQTT_HOST', 'mqtt'),
        'port' => (int) env('MQTT_PORT', 1883),
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
        // Base URL of the app as reachable from the EMQX container; EMQX posts MQTT messages to its webhooks (see mqtt:setup-webhook)
        'webhook_base_url' => env('EMQX_WEBHOOK_BASE_URL', 'http://web'),
        // Pre-shared secret EMQX sends in the X-EMQX-Secret header; the webhooks reject every request while it is empty
        'webhook_secret' => env('EMQX_WEBHOOK_SECRET'),
        // Every Agronos MQTT topic lives under this prefix so the broker can be shared (ADR-0007); re-run mqtt:setup-webhook after changing it
        'topic_prefix' => env('MQTT_TOPIC_PREFIX', 'agronos'),
    ],
];
