<?php

/**
 * Ask AI OpenAI Normalizer Staging Verification Script
 * Task #2281
 *
 * Run: php artisan tinker --no-interaction < scripts/ask_ai_normalizer_staging_verify.php
 * Or:  php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; ..." (not recommended)
 *
 * Uses the real AskAiRunnerV2Service pipeline with a live OpenAI call for the normalizer step.
 */

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
echo 'Date: ' . now()->toDateTimeString() . PHP_EOL;
echo 'Listing: ' . $listingType . ' #' . $listingId . PHP_EOL;
echo 'Model: ' . config('ai.model') . PHP_EOL;
echo 'Flag: ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=' . (config('ask_ai.enable_openai_intent_normalization') ? 'true' : 'false') . PHP_EOL;
echo PHP_EOL;

$runner = app(\App\Services\AskAi\AskAiRunnerV2Service::class);

// ─── NATURAL-LANGUAGE ROOF PHRASES ───────────────────────────────────────────
echo '--- ROOF PHRASE TESTS (normalizer should map to faq_answers.roof_age_and_condition) ---' . PHP_EOL;
echo PHP_EOL;

foreach ($roofPhrases as $i => $phrase) {
    $n = $i + 1;
    echo "Test R{$n}: \"{$phrase}\"" . PHP_EOL;

    $tStart = microtime(true);
    $result = $runner->run($listingType, $listingId, $phrase);
    $elapsed = round((microtime(true) - $tStart) * 1000);

    $trace = $result['trace'] ?? [];

    echo '  classifier_result   : ' . ($trace['classifier_result']    ?? 'n/a') . PHP_EOL;
    echo '  normalizer_called   : ' . ($trace['normalizer_called']     ?? 'n/a') . PHP_EOL;
    echo '  normalized_field_key: ' . ($trace['normalized_field_key']  ?? 'n/a') . PHP_EOL;
    echo '  faq_key_detected    : ' . ($trace['faq_key_detected']      ?? 'n/a') . PHP_EOL;
    echo '  final_question_type : ' . ($trace['final_question_type']   ?? 'n/a') . PHP_EOL;
    echo '  final_status        : ' . ($trace['final_status']          ?? 'n/a') . PHP_EOL;
    echo '  elapsed_ms          : ' . $elapsed . PHP_EOL;

    $answer = $result['final_response']['answer'] ?? null;
    if ($answer) {
        echo '  answer (80 chars)   : ' . substr($answer, 0, 80) . (strlen($answer) > 80 ? '…' : '') . PHP_EOL;
    } else {
        echo '  answer              : (none — status: ' . ($result['status'] ?? '?') . ')' . PHP_EOL;
    }

    // Determine pass/fail
    $normalizerCalled     = ($trace['normalizer_called'] ?? '') === 'Y';
    $normalizedKey        = $trace['normalized_field_key'] ?? null;
    $finalType            = $trace['final_question_type']  ?? '';
    $finalStatus          = $trace['final_status']         ?? '';
    $classifierResult     = $trace['classifier_result']    ?? '';

    // For roof phrases the classifier should return 'unsupported', normalizer should fire,
    // and map to faq_answers.roof_age_and_condition
    $pass = ($classifierResult === 'unsupported')
        && $normalizerCalled
        && ($normalizedKey === 'faq_answers.roof_age_and_condition')
        && ($finalType === 'listing_facts')
        && in_array($finalStatus, ['ready', 'insufficient_context'], true);

    $report["R{$n}"] = [
        'phrase'          => $phrase,
        'pass'            => $pass,
        'classifier'      => $classifierResult,
        'normalizer'      => $trace['normalizer_called'] ?? 'N',
        'normalized_key'  => $normalizedKey,
        'final_type'      => $finalType,
        'final_status'    => $finalStatus,
        'elapsed_ms'      => $elapsed,
    ];

    echo '  RESULT: ' . ($pass ? 'PASS ✓' : 'FAIL ✗') . PHP_EOL;
    echo PHP_EOL;

    // Brief pause between calls to avoid rate limiting
    sleep(1);
}

// ─── PROHIBITED PHRASES ───────────────────────────────────────────────────────
echo '--- PROHIBITED PHRASE TESTS (OpenAI must NOT be called) ---' . PHP_EOL;
echo PHP_EOL;

foreach ($prohibitedPhrases as $i => $phrase) {
    $n = $i + 1;
    echo "Test P{$n}: \"{$phrase}\"" . PHP_EOL;

    $result = $runner->run($listingType, $listingId, $phrase);
    $trace  = $result['trace'] ?? [];

    echo '  classifier_result   : ' . ($trace['classifier_result']  ?? 'n/a') . PHP_EOL;
    echo '  normalizer_called   : ' . ($trace['normalizer_called']  ?? 'n/a') . PHP_EOL;
    echo '  final_question_type : ' . ($trace['final_question_type'] ?? 'n/a') . PHP_EOL;
    echo '  final_status        : ' . ($trace['final_status']        ?? 'n/a') . PHP_EOL;
    echo '  success             : ' . ($result['success'] ? 'true' : 'false') . PHP_EOL;
    echo '  status              : ' . ($result['status']  ?? 'n/a') . PHP_EOL;

    $classifierResult = $trace['classifier_result'] ?? '';
    $normalizerCalled = ($trace['normalizer_called'] ?? '') === 'Y';
    $status           = $result['status'] ?? '';

    // Prohibited questions should be blocked before the normalizer
    $pass = ($classifierResult === 'prohibited')
        && !$normalizerCalled
        && in_array($status, ['blocked', 'failed'], true);

    $report["P{$n}"] = [
        'phrase'         => $phrase,
        'pass'           => $pass,
        'classifier'     => $classifierResult,
        'normalizer'     => $trace['normalizer_called'] ?? 'N',
        'status'         => $status,
    ];

    echo '  RESULT: ' . ($pass ? 'PASS ✓' : 'FAIL ✗') . PHP_EOL;
    echo PHP_EOL;
}

// ─── SUMMARY ──────────────────────────────────────────────────────────────────
echo '=== SUMMARY ===' . PHP_EOL;
$allPass = true;
foreach ($report as $key => $row) {
    $status = $row['pass'] ? 'PASS' : 'FAIL';
    if (!$row['pass']) $allPass = false;
    echo "  [{$status}] {$key}: " . substr($row['phrase'], 0, 60) . PHP_EOL;
}
echo PHP_EOL;

if ($allPass) {
    echo '>>> VERDICT: APPROVED FOR STAGING <<<' . PHP_EOL;
} else {
    echo '>>> VERDICT: FAILED – FIXES REQUIRED <<<' . PHP_EOL;
}
echo PHP_EOL;
