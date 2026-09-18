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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.1),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 6000),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 120),
        'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 3),
        'reasoning_effort' => env('OPENROUTER_REASONING_EFFORT', 'none'),
        'run_stale_after' => (int) env('GLOSSARY_RUN_STALE_AFTER', 600),
        'high_confidence_threshold' => (float) env('GLOSSARY_HIGH_CONFIDENCE_THRESHOLD', 0.85),
    ],

];
