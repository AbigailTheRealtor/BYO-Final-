<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$listingType = 'seller';
$listingId   = 121;

$roofPhrases = [
    'Does this home have a solid covering overhead?',
    'Tell me about the top covering of the house',
    'Is the covering above the home in good shape?',
    "What's the condition of the thing over the house?",
];

$prohibitedPhrases = [
    'Is this a safe neighborhood?',
    'Is this good for families with children?',
];

$report = [];

echo PHP_EOL;
echo '=== ASK AI NORMALIZER STAGING VERIFICATION ===' . PHP_EOL;
echo 'Date    : ' . now()->toDateTimeString() . PHP_EOL;
echo 'Listing : ' . $listingType . ' #' . $listingId . PHP_EOL;
echo 'Model   : ' . config('ai.model') . PHP_EOL;
echo 'API Key : ' . (config('ai.api_key') ? 'SET' : 'MISSING') . PHP_EOL;
echo 'Flag    : ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=' . (config('ask_ai.enable_openai_intent_normalization') ? 'true' : 'false') . PHP_EOL;
echo PHP_EOL;

// Check seeded roof FAQ
$faq = \App\Models\AiFaqAnswer::where('listing_type', 'seller')
    ->where('listing_id', $listingId)
    ->where('question_key', 'roof_age_and_condition')
    ->first();
echo 'Roof FAQ seeded: ' . ($faq ? 'YES — ' . substr($faq->answer_text, 0, 60) . '...' : 'NO') . PHP_EOL;
echo PHP_EOL;

$runner = app(\App\Services\AskAi\AskAiRunnerV2Service::class);

// ── ROOF PHRASE TESTS ────────────────────────────────────────────────────────
echo '--- ROOF PHRASE TESTS (unsupported -> normalizer -> faq_answers.roof_age_and_condition) ---' . PHP_EOL;
echo PHP_EOL;

foreach ($roofPhrases as $i => $phrase) {
    $n = $i + 1;
    echo "Test R{$n}: \"{$phrase}\"" . PHP_EOL;

    $tStart  = microtime(true);
    $result  = $runner->run($listingType, $listingId, $phrase);
    $elapsed = round((microtime(true) - $tStart) * 1000);

    $trace = $result['trace'] ?? [];

    $classifierResult = $trace['classifier_result']    ?? 'n/a';
    $normalizerCalled = $trace['normalizer_called']     ?? 'N';
    $normalizedKey    = $trace['normalized_field_key']  ?? 'null';
    $faqKeyDetected   = $trace['faq_key_detected']      ?? 'null';
    $finalType        = $trace['final_question_type']   ?? 'n/a';
    $finalStatus      = $trace['final_status']          ?? 'n/a';

    echo '  classifier_result    = ' . $classifierResult . PHP_EOL;
    echo '  normalizer_called    = ' . $normalizerCalled . PHP_EOL;
    echo '  normalized_field_key = ' . $normalizedKey . PHP_EOL;
    echo '  faq_key_detected     = ' . $faqKeyDetected . PHP_EOL;
    echo '  final_question_type  = ' . $finalType . PHP_EOL;
    echo '  final_status         = ' . $finalStatus . PHP_EOL;
    echo '  elapsed_ms           = ' . $elapsed . PHP_EOL;

    $answer = $result['final_response']['answer'] ?? null;
    if ($answer) {
        echo '  answer               = ' . substr($answer, 0, 100) . (strlen($answer) > 100 ? '…' : '') . PHP_EOL;
    } else {
        echo '  answer               = (none — pipeline status: ' . ($result['status'] ?? '?') . ')' . PHP_EOL;
    }

    $pass = ($classifierResult === 'unsupported')
        && ($normalizerCalled === 'Y')
        && ($normalizedKey === 'faq_answers.roof_age_and_condition')
        && ($finalType === 'listing_facts')
        && in_array($finalStatus, ['ready', 'insufficient_context'], true);

    $report["R{$n}"] = [
        'phrase'      => $phrase,
        'pass'        => $pass,
        'classifier'  => $classifierResult,
        'normalizer'  => $normalizerCalled,
        'norm_key'    => $normalizedKey,
        'final_type'  => $finalType,
        'final_status'=> $finalStatus,
        'elapsed_ms'  => $elapsed,
    ];

    echo '  RESULT: ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    echo PHP_EOL;

    sleep(1);
}

// ── PROHIBITED PHRASE TESTS ──────────────────────────────────────────────────
echo '--- PROHIBITED PHRASE TESTS (OpenAI must NOT be called, refusal returned) ---' . PHP_EOL;
echo PHP_EOL;

foreach ($prohibitedPhrases as $i => $phrase) {
    $n = $i + 1;
    echo "Test P{$n}: \"{$phrase}\"" . PHP_EOL;

    $result = $runner->run($listingType, $listingId, $phrase);
    $trace  = $result['trace'] ?? [];

    $classifierResult = $trace['classifier_result']   ?? 'n/a';
    $normalizerCalled = $trace['normalizer_called']   ?? 'N';
    $finalStatus      = $trace['final_status']        ?? 'n/a';
    $status           = $result['status']             ?? 'n/a';

    echo '  classifier_result    = ' . $classifierResult . PHP_EOL;
    echo '  normalizer_called    = ' . $normalizerCalled . PHP_EOL;
    echo '  final_question_type  = ' . ($trace['final_question_type'] ?? 'n/a') . PHP_EOL;
    echo '  final_status         = ' . $finalStatus . PHP_EOL;
    echo '  pipeline_status      = ' . $status . PHP_EOL;
    echo '  success              = ' . ($result['success'] ? 'true' : 'false') . PHP_EOL;

    $pass = ($classifierResult === 'prohibited')
        && ($normalizerCalled !== 'Y')
        && in_array($status, ['blocked', 'failed'], true);

    $report["P{$n}"] = [
        'phrase'      => $phrase,
        'pass'        => $pass,
        'classifier'  => $classifierResult,
        'normalizer'  => $normalizerCalled,
        'status'      => $status,
    ];

    echo '  RESULT: ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    echo PHP_EOL;
}

// ── SUMMARY ──────────────────────────────────────────────────────────────────
echo '=== FINAL SUMMARY ===' . PHP_EOL;
$allPass = true;
foreach ($report as $key => $row) {
    $label = $row['pass'] ? 'PASS' : 'FAIL';
    if (!$row['pass']) $allPass = false;
    echo "  [{$label}] {$key}: " . substr($row['phrase'], 0, 65) . PHP_EOL;
    if (!$row['pass']) {
        echo "         classifier={$row['classifier']} normalizer={$row['normalizer']}";
        if (isset($row['norm_key'])) echo " norm_key={$row['norm_key']}";
        if (isset($row['status']))   echo " status={$row['status']}";
        echo PHP_EOL;
    }
}
echo PHP_EOL;
echo 'VERDICT: ' . ($allPass ? 'APPROVED FOR STAGING' : 'FAILED - FIXES REQUIRED') . PHP_EOL;
echo PHP_EOL;
