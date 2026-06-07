<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiResponseContractService;

$client = app(OpenAiClientService::class);
$contractService = app(AskAiResponseContractService::class);
$normalizer = new AskAiIntentNormalizerService($client, $contractService);

$keys = $normalizer->buildKnownFieldKeys();
echo 'Known field keys count: ' . count($keys) . PHP_EOL;

// Send directly through the OpenAI client with the same payload the normalizer builds
// by reflecting into buildPayload via the same logic, but inline here for debug

$question = 'Does this home have a solid covering overhead?';

// Build the payload the same way AskAiIntentNormalizerService::buildPayload does
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

echo PHP_EOL;
echo 'Question: "' . $question . '"' . PHP_EOL;
echo 'Calling OpenAI...' . PHP_EOL;

try {
    $callOptions = ['timeout_seconds' => 10, 'max_tokens' => 20];
    $result = $client->send($payload, $callOptions);
    echo 'Raw result data:' . PHP_EOL;
    print_r($result['data'] ?? 'NO DATA');
    echo PHP_EOL;
    echo 'Model: ' . ($result['model'] ?? 'n/a') . PHP_EOL;
    echo 'Total tokens: ' . ($result['total_tokens'] ?? 'n/a') . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
