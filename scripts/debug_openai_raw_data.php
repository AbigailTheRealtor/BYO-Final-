<?php

/**
 * Debug: capture raw decoded JSON from OpenAI for the normalizer payload.
 * Bypasses normalize()'s null-guard so we see exactly what OpenAI returns.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(\App\Services\Ai\OpenAiClientService::class);

// Use an even smaller key list — just 6 keys, highly focused
$minimalKeys = [
    'faq_answers.roof_age_and_condition',
    'faq_answers.hvac_system_age',
    'faq_answers.foundation_condition',
    'listing.year_built',
    'listing.bedrooms',
    'listing.square_footage',
];

$phrases = [
    'top covering of the house',
    'What can you tell me about the covering on top of this property?',
    'Is the roof in good condition?',
];

foreach ($phrases as $i => $phrase) {
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
            'You MUST NOT reference, infer, or imply any protected class characteristics.',
            'Your entire response must be a valid JSON object with no additional text.',
        ]),
        'question'    => $phrase,
        'field_keys'  => array_values($minimalKeys),
        'governance'  => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}.',
    ];

    echo "--- Phrase " . ($i + 1) . ": \"{$phrase}\" ---\n";

    try {
        $result = $client->send($payload, ['timeout_seconds' => 15, 'max_tokens' => 20]);
        echo "HTTP call: SUCCESS\n";
        echo "Raw data from OpenAI: ";
        print_r($result['data']);
        echo "Tokens used: " . ($result['total_tokens'] ?? 'n/a') . "\n";
    } catch (\OpenAI\Exceptions\RateLimitException $e) {
        echo "RATE_LIMIT: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "EXCEPTION (" . get_class($e) . "): " . $e->getMessage() . "\n";
    }

    echo "\n";

    if ($i < count($phrases) - 1) {
        echo "(waiting 8s...)\n\n";
        sleep(8);
    }
}
