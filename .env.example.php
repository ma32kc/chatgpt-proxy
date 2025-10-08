<?php

declare(strict_types=1);

return [
    // HMAC secret key
    'secret' => 'change_this_secret',

    // OpenAI API key
    'api_key' => 'sk-proj-XXXXXXXXXXXXXXXXXXXXXXXXXXXX',

    // Proxy key
    'proxy_key' => 'change_this_proxy_key',

    // Job lifetime
    'ttl' => 86400,

    // How many times to retry failed OpenAI calls
    'max_retries' => 3,

    // Requests per IP per hour
    'rate_limit' => 100,

    // OpenAI endpoint and model
    'openai_url' => 'https://api.openai.com/v1/chat/completions',
    'model' => 'gpt-4',

    // HTTP timeout for API calls
    'timeout' => 60,

    // Sleep interval
    'daemon_sleep' => 10,
];
