<?php

/**
 * Debug: show raw OpenAI response for roof phrases.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(\App\Services\Ai\OpenAiClientService::class);

$roofFocusedKeys = [
    'faq_answers.roof_age_and_condition',
    'faq_answers.hvac_system_age',
    'faq_answers.heating_cooling_system',
    'listing.year_built',
    'listing.bedrooms',
    'listing.square_footage',
];

$testPhrases = [
    'top covering of the house',
    'What can you tell me about the covering on top of this property?',
];

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
    'question'    => $testPhrases[0],
    'field_keys'  => array_values($roofFocusedKeys),
    'governance'  => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}. No other output is permitted.',
];

echo "Testing phrase: \"{$testPhrases[0]}\"\n";
echo "Key list: " . implode(', ', $roofFocusedKeys) . "\n\n";

try {
    $result = $client->send($payload, ['timeout_seconds' => 15, 'max_tokens' => 20]);
    echo "SUCCESS\n";
    echo "data: "; var_dump($result['data']);
    echo "model: "   . ($result['model']        ?? 'n/a') . "\n";
    echo "tokens: "  . ($result['total_tokens'] ?? 'n/a') . "\n";
    echo "raw_response: "; var_dump($result['raw_response'] ?? 'NOT PRESENT');
} catch (\OpenAI\Exceptions\RateLimitException $e) {
    echo "RATE_LIMIT: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "ERROR (" . get_class($e) . "): " . $e->getMessage() . "\n";
}
