<?php

declare(strict_types=1);

return [
    'default' => env('AI_PROVIDER', 'rules'),
    'prompt_version' => 'news-analysis-v1',
    'providers' => [
        'rules' => ['model' => 'deterministic-rules-v1'],
        'gigachat' => [
            'auth_url' => env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth'),
            'api_url' => env('GIGACHAT_API_URL', 'https://api.giga.chat/v1'),
            'auth_key' => env('GIGACHAT_AUTH_KEY'),
            'client_id' => env('GIGACHAT_CLIENT_ID'),
            'client_secret' => env('GIGACHAT_CLIENT_SECRET'),
            'scope' => env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
            'model' => env('GIGACHAT_MODEL', 'GigaChat-2-Max'),
            'embedding_model' => env('GIGACHAT_EMBEDDING_MODEL', 'EmbeddingsGigaR'),
            'embedding_fallback' => filter_var(env('GIGACHAT_EMBEDDING_FALLBACK', true), FILTER_VALIDATE_BOOL),
            'timeout' => (int) env('GIGACHAT_TIMEOUT', 120),
            'connect_timeout' => (int) env('GIGACHAT_CONNECT_TIMEOUT', 10),
            'max_attempts' => (int) env('GIGACHAT_MAX_ATTEMPTS', 5),
            'verify_ssl' => filter_var(env('GIGACHAT_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
        ],
    ],
];
