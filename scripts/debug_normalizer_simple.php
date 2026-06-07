<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiResponseContractService;

$client          = app(OpenAiClientService::class);
$contractService = app(AskAiResponseContractService::class);
$normalizer      = new AskAiIntentNormalizerService($client, $contractService);

$keys = $normalizer->buildKnownFieldKeys();
echo 'Known field keys count: ' . count($keys) . PHP_EOL;
echo 'faq_answers.roof_age_and_condition in keys: ' . (in_array('faq_answers.roof_age_and_condition', $keys) ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;

// Use one clear (less cryptic) phrase first to verify normalizer path works at all
$testPhrases = [
    // Less abstract — should clearly map to roof_age_and_condition
    'Is the roof in good shape?',
    // Then progressively more abstract (task spec phrases)
    'Does this home have a solid covering overhead?',
    'Tell me about the top covering of the house',
    'Is the covering above the home in good shape?',
    "What's the condition of the thing over the house?",
];

foreach ($testPhrases as $i => $phrase) {
    echo 'Phrase ' . ($i + 1) . ': "' . $phrase . '"' . PHP_EOL;

    $t      = microtime(true);
    $result = $normalizer->normalize($phrase, $keys);
    $ms     = round((microtime(true) - $t) * 1000);

    echo '  normalized_key => ' . ($result ?? 'null (unknown or error)') . PHP_EOL;
    echo '  elapsed_ms     => ' . $ms . PHP_EOL;
    echo PHP_EOL;

    sleep(3); // pace between calls to avoid rate limit
}
