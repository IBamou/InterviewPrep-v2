<?php

return [

    'default_provider' => env('AI_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('AI_API_KEY'),
            'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
        ],
        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],
    ],

    'defaults' => [
        'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
        'timeout' => (int) env('AI_TIMEOUT', 30),
        'retry' => (int) env('AI_RETRY', 2),
        'retry_delay' => (int) env('AI_RETRY_DELAY', 500),
    ],

];
