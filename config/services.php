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

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'vision_model' => env('GROQ_VISION_MODEL', 'llama-3.2-11b-vision-preview'),
        'fallback_models' => array_filter(array_map('trim', explode(',', env('GROQ_FALLBACK_MODELS', 'llama-3.1-8b-instant,gemma2-9b-it')))),
        'verify_ssl' => env('GROQ_VERIFY_SSL', true),
        'timeout_seconds' => env('GROQ_TIMEOUT_SECONDS', 20),
        'max_tokens' => env('GROQ_MAX_TOKENS', 4096),
        'require_json_response' => env('GROQ_REQUIRE_JSON_RESPONSE', true),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
    ],

    'googlefit' => [
        'client_id'     => env('GOOGLE_FIT_CLIENT_ID'),
        'client_secret' => env('GOOGLE_FIT_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_FIT_REDIRECT_URI'),
    ],

    'garmin' => [
        'client_id'     => env('GARMIN_CLIENT_ID'),
        'client_secret' => env('GARMIN_CLIENT_SECRET'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    'apns' => [
        'key_id'           => env('APNS_KEY_ID'),
        'team_id'          => env('APNS_TEAM_ID'),
        'bundle_id'        => env('APNS_BUNDLE_ID'),
        'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
    ],

];
