<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Directly test the normalizer with a roof phrase
$normalizer = app(\App\Services\AskAi\AskAiIntentNormalizerService::class);

echo '=== NORMALIZER DEBUG ===' . PHP_EOL;
echo 'isEnabled: ' . ($normalizer->isEnabled() ? 'YES' : 'NO') . PHP_EOL;

$keys = $normalizer->buildKnownFieldKeys();
echo 'Known field keys count: ' . count($keys) . PHP_EOL;
echo 'Roof key present: ' . (in_array('faq_answers.roof_age_and_condition', $keys) ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;

$testPhrases = [
    'Does this home have a solid covering overhead?',
    'Tell me about the top covering of the house',
    'Is the covering above the home in good shape?',
    "What's the condition of the thing over the house?",
];

foreach ($testPhrases as $i => $phrase) {
    echo "Phrase " . ($i+1) . ": \"$phrase\"" . PHP_EOL;
    $result = $normalizer->normalize($phrase, $keys);
    echo "  normalized_key => " . ($result ?? 'null (OpenAI returned unknown or error)') . PHP_EOL;
    echo PHP_EOL;
    sleep(1);
}
