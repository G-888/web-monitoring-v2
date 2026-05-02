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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'verify_ssl' => filter_var(env('TELEGRAM_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'seo_search' => [
        'enabled' => env('SEO_SEARCH_ENABLED', false),
        'providers' => array_filter(array_map('trim', explode(',', env('SEO_SEARCH_PROVIDERS', 'commoncrawl,wayback,urlscan,crtsh,google,brave')))),
        'result_limit' => (int) env('SEO_SEARCH_RESULT_LIMIT', 10),
        'bing_key' => env('BING_SEARCH_KEY'),
        'google_key' => env('GOOGLE_SEARCH_KEY'),
        'google_cx' => env('GOOGLE_SEARCH_CX'),
        'brave_key' => env('BRAVE_SEARCH_KEY'),
        'urlscan_key' => env('URLSCAN_API_KEY'),
        'commoncrawl_enabled' => env('SEO_COMMONCRAWL_ENABLED', true),
        'commoncrawl_indexes' => (int) env('SEO_COMMONCRAWL_INDEXES', 2),
        'wayback_enabled' => env('SEO_WAYBACK_ENABLED', true),
        'urlscan_enabled' => env('SEO_URLSCAN_ENABLED', true),
        'crtsh_enabled' => env('SEO_CRTSH_ENABLED', true),
    ],

    'log_ai' => [
        'enabled' => env('LOG_AI_ENABLED', false),
        'default_provider' => env('LOG_AI_PROVIDER', 'openrouter_free'),
        'fallback_enabled' => env('LOG_AI_FALLBACK_ENABLED', true),
        'timeout' => (int) env('LOG_AI_TIMEOUT', 30),
        'verify_ssl' => env('LOG_AI_VERIFY_SSL', true),
        'providers' => [
            'openrouter_free' => [
                'api_key' => env('OPENROUTER_API_KEY'),
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
                'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
            ],
            'groq_free' => [
                'api_key' => env('GROQ_API_KEY'),
                'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
                'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            ],
        ],
    ],

    'ripgrep' => [
        'allowed_paths' => env('RIPGREP_ALLOWED_PATHS', storage_path('logs')),
    ],

];
