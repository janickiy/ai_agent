<?php

declare(strict_types=1);

return [
    'prompt_version' => 'news-analysis-v1',
    'providers' => [
        'rules' => ['model' => 'deterministic-rules-v1'],
        'gigachat' => [
            'auth_url' => 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth',
            'api_url' => 'https://api.giga.chat/v1',
            'scope' => 'GIGACHAT_API_PERS',
            'model' => 'GigaChat-2-Max',
            'embedding_model' => 'EmbeddingsGigaR',
            'embedding_fallback' => true,
            'timeout' => 120,
            'connect_timeout' => 10,
            'max_attempts' => 5,
            'verify_ssl' => true,
        ],
        'yandexgpt' => [
            'api_url' => 'https://ai.api.cloud.yandex.net/v1',
            'folder_id' => '',
            'model' => 'yandexgpt/latest',
            'embedding_model' => 'text-search-doc/latest',
            'timeout' => 120,
            'connect_timeout' => 10,
            'max_attempts' => 5,
            'verify_ssl' => true,
        ],
        'openai' => [
            'api_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-5.6',
            'embedding_model' => 'text-embedding-3-small',
            'organization' => '',
            'project' => '',
            'timeout' => 120,
            'connect_timeout' => 10,
            'max_attempts' => 5,
            'verify_ssl' => true,
        ],
    ],
];
