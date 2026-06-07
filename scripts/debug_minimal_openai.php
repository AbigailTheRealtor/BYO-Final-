<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('ai.api_key');
$model  = config('ai.model');

echo 'Model: ' . $model . PHP_EOL;
echo 'API key prefix: ' . substr($apiKey, 0, 7) . '...' . PHP_EOL;
echo PHP_EOL;

// Minimal call with just 5 field keys (not 257) to minimize token usage
$payload = [
    'task'        => 'intent_normalization_v1',
    'instruction' => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}.',
    'question'    => 'Is the roof in good shape?',
    'field_keys'  => [
        'faq_answers.roof_age_and_condition',
        'listing.bedrooms',
        'listing.rent_amount',
        'faq_answers.hvac_system_age',
        'listing.pets_allowed',
    ],
    'governance'  => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}.',
];

$client = app(\App\Services\Ai\OpenAiClientService::class);

try {
    echo 'Sending minimal payload...' . PHP_EOL;
    $result = $client->send($payload, ['timeout_seconds' => 15, 'max_tokens' => 20]);
    echo 'SUCCESS' . PHP_EOL;
    echo 'data: '; print_r($result['data']);
    echo 'model: '   . ($result['model']        ?? 'n/a') . PHP_EOL;
    echo 'tokens: '  . ($result['total_tokens'] ?? 'n/a') . PHP_EOL;
    echo 'attempts: '. ($result['attempt_count'] ?? 'n/a') . PHP_EOL;
} catch (\OpenAI\Exceptions\RateLimitException $e) {
    echo 'RATE_LIMIT: ' . $e->getMessage() . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR (' . get_class($e) . '): ' . $e->getMessage() . PHP_EOL;
}
