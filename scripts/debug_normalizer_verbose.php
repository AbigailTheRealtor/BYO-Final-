<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiResponseContractService;

$client          = app(OpenAiClientService::class);
$contractService = app(AskAiResponseContractService::class);

// Rebuild known field keys
$normalizer = app(\App\Services\AskAi\AskAiIntentNormalizerService::class);
$keys       = $normalizer->buildKnownFieldKeys();

echo 'Keys count: ' . count($keys) . PHP_EOL;
echo PHP_EOL;

// Build payload directly — same as normalizer::buildPayload
$question = 'Is the roof in good shape?';

$payload = [
    'task'        => 'intent_normalization_v1',
    'instruction' => implode(' ', [
        'You are a real estate field-key resolver.',
        'Given a user question about a property listing and a list of canonical field keys,',
        'identify which single field key the question is asking about.',
        'Return ONLY a valid JSON object with exactly one key: "normalized_key".',
        'Its value must be exactly one entry from the provided field_keys list,',
        'OR the literal string "unknown" if no single field key matches.',
        'You MUST NOT generate a final answer to the question.',
        'You MUST NOT reference, infer, or imply any protected class characteristics',
        '(race, religion, national origin, sex, disability, familial status, or any similar category).',
        'Your entire response must be a valid JSON object with no additional text.',
    ]),
    'question'    => $question,
    'field_keys'  => array_values($keys),
    'governance'  => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}. No other output is permitted.',
];

echo 'Sending to OpenAI: "' . $question . '"' . PHP_EOL;

try {
    $result = $client->send($payload, ['timeout_seconds' => 10, 'max_tokens' => 20]);
    echo 'SUCCESS' . PHP_EOL;
    echo 'data: ';
    print_r($result['data']);
    echo 'model: ' . ($result['model'] ?? 'n/a') . PHP_EOL;
    echo 'tokens: ' . ($result['total_tokens'] ?? 'n/a') . PHP_EOL;
    echo 'attempts: ' . ($result['attempt_count'] ?? 'n/a') . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR (' . get_class($e) . '): ' . $e->getMessage() . PHP_EOL;
    echo 'Code: ' . $e->getCode() . PHP_EOL;
}
