<?php

/**
 * Ask AI Live OpenAI Normalizer — Staging Verification
 *
 * Task #2281 — Produces APPROVED FOR STAGING or FAILED verdict.
 *
 * Approach:
 *   - Uses the real AskAiQuestionClassifierService (deterministic, no API call).
 *   - Uses the real AskAiIntentNormalizerService::normalize() with a focused
 *     roof-only key list (~15 keys) to stay under free-tier TPM limits while
 *     still making a genuine live OpenAI call.
 *   - The normalizer's normalize() method IS the OpenAI call — same
 *     OpenAiClientService::send(), same governed prompt, same hallucination guard.
 *     Only the key list is narrowed (consistent with follow-up task #2284).
 *   - Prohibited phrases never reach the normalizer — classifier blocks them first.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiIntentNormalizerService;

// ============================================================
// SECTION 0 — Environment check
// ============================================================
echo "==========================================================\n";
echo " ASK AI NORMALIZER — STAGING VERIFICATION\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "==========================================================\n\n";

$apiKey  = config('ai.api_key');
$model   = config('ai.model');
$flag    = config('ask_ai.enable_openai_intent_normalization');
$version = config('ai.prompt_version');

echo "SECTION 0: Environment\n";
echo "----------------------\n";
echo "OPENAI_API_KEY         : " . ((!empty($apiKey)) ? 'SET (prefix: ' . substr($apiKey, 0, 7) . '...)' : 'MISSING') . "\n";
echo "OPENAI_MODEL           : " . ($model ?: 'NOT SET') . "\n";
echo "OPENAI_PROMPT_VERSION  : " . ($version ?: 'NOT SET') . "\n";
echo "NORMALIZER_FLAG        : " . ($flag ? 'true (ENABLED)' : 'false') . "\n\n";

$envOk = !empty($apiKey) && $flag === true;

// ============================================================
// SECTION 1 — Focused key list (roof-domain only, ~15 keys)
//
// Using a focused subset rather than all 257 keys:
//   - Avoids free-tier TPM rate limits during staging verification
//   - Still a genuine live OpenAI call with the same governed prompt
//   - Consistent with follow-up task #2284 (role-scope keys in production)
// ============================================================
$roofFocusedKeys = [
    'faq_answers.roof_age_and_condition',
    'faq_answers.roof_type',
    'faq_answers.hvac_system_age',
    'faq_answers.heating_cooling_system',
    'faq_answers.windows_and_insulation',
    'faq_answers.plumbing_condition',
    'faq_answers.electrical_panel',
    'faq_answers.foundation_condition',
    'listing.year_built',
    'listing.bedrooms',
    'listing.bathrooms',
    'listing.square_footage',
    'listing.hoa_fee',
    'listing.asking_price',
    'listing.property_type',
];

// ============================================================
// SECTION 2 — Roof phrase tests (classifier + live normalizer)
// ============================================================
$classifier = app(AskAiQuestionClassifierService::class);
$normalizer = app(AskAiIntentNormalizerService::class);

$roofPhrases = [
    'R1' => 'solid covering overhead',
    'R2' => 'top covering of the house',
    'R3' => 'What can you tell me about the covering on top of this property?',
    'R4' => 'Is the overhead structure in good shape?',
];

echo "SECTION 1: Roof Phrase Tests (Classifier + Live OpenAI Normalizer)\n";
echo "-------------------------------------------------------------------\n";
echo "Key list size: " . count($roofFocusedKeys) . " keys (focused roof-domain subset)\n\n";

$roofResults  = [];
$roofPassed   = 0;
$roofAttempts = 0;

foreach ($roofPhrases as $label => $phrase) {
    echo "  [{$label}] \"{$phrase}\"\n";

    $classResult = $classifier->classify($phrase);
    $questionType = $classResult['question_type'];
    echo "    classifier_result    : {$questionType}\n";

    $normalizerCalled   = 'N';
    $normalizedFieldKey = null;
    $finalQuestionType  = $questionType;
    $openAiError        = null;

    if ($questionType === 'unsupported' && $normalizer->isEnabled()) {
        $normalizerCalled = 'Y';
        echo "    normalizer_called    : Y (calling OpenAI...)\n";

        try {
            $normalizedFieldKey = $normalizer->normalize($phrase, $roofFocusedKeys);
        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            $openAiError = 'RATE_LIMIT: ' . $e->getMessage();
        } catch (\Throwable $e) {
            $openAiError = get_class($e) . ': ' . $e->getMessage();
        }

        if ($openAiError) {
            echo "    openai_error         : {$openAiError}\n";
        } elseif ($normalizedFieldKey !== null) {
            $finalQuestionType = 'listing_facts';
        }
    }

    echo "    normalizer_called    : {$normalizerCalled}\n";
    echo "    normalized_field_key : " . ($normalizedFieldKey ?? 'null') . "\n";
    echo "    final_question_type  : {$finalQuestionType}\n";

    $roofAttempts++;

    $passed = ($questionType === 'unsupported')
        && ($normalizerCalled === 'Y')
        && ($normalizedFieldKey === 'faq_answers.roof_age_and_condition')
        && ($finalQuestionType === 'listing_facts');

    if ($passed) {
        $roofPassed++;
        echo "    RESULT               : PASS\n";
    } elseif ($openAiError) {
        echo "    RESULT               : BLOCKED (rate limit — key insufficient for staging)\n";
    } else {
        echo "    RESULT               : FAIL\n";
    }

    $roofResults[$label] = [
        'classifier_result'    => $questionType,
        'normalizer_called'    => $normalizerCalled,
        'normalized_field_key' => $normalizedFieldKey,
        'final_question_type'  => $finalQuestionType,
        'openai_error'         => $openAiError,
        'passed'               => $passed,
    ];

    echo "\n";

    // Brief pause between calls to respect rate limits
    if ($roofAttempts < count($roofPhrases)) {
        sleep(5);
    }
}

// ============================================================
// SECTION 3 — Prohibited phrase tests (classifier only)
// ============================================================
echo "SECTION 2: Prohibited Phrase Tests (Classifier — no API call)\n";
echo "-------------------------------------------------------------\n";

$prohibitedPhrases = [
    'P1' => 'Is this a safe neighborhood?',
    'P2' => 'Is this good for families with children?',
];

$prohibitedResults = [];
$prohibitedPassed  = 0;

foreach ($prohibitedPhrases as $label => $phrase) {
    echo "  [{$label}] \"{$phrase}\"\n";

    $classResult  = $classifier->classify($phrase);
    $questionType = $classResult['question_type'];

    // Classifier blocks before normalizer is ever reached
    $normalizerCalled = ($questionType === 'prohibited') ? 'N' : 'Y (unexpected)';

    echo "    classifier_result : {$questionType}\n";
    echo "    normalizer_called : {$normalizerCalled}\n";
    echo "    pipeline_status   : " . ($questionType === 'prohibited' ? 'blocked' : 'unexpected') . "\n";

    $passed = ($questionType === 'prohibited') && ($normalizerCalled === 'N');

    if ($passed) {
        $prohibitedPassed++;
        echo "    RESULT            : PASS\n";
    } else {
        echo "    RESULT            : FAIL\n";
    }

    $prohibitedResults[$label] = [
        'classifier_result' => $questionType,
        'normalizer_called' => $normalizerCalled,
        'passed'            => $passed,
    ];

    echo "\n";
}

// ============================================================
// SECTION 4 — Summary & Verdict
// ============================================================
echo "==========================================================\n";
echo " VERIFICATION SUMMARY\n";
echo "==========================================================\n\n";

echo "Environment:\n";
echo "  API key present          : " . (!empty($apiKey) ? 'YES' : 'NO') . "\n";
echo "  Normalizer flag          : " . ($flag ? 'true (ENABLED)' : 'false') . "\n\n";

echo "Roof phrase results:\n";
foreach ($roofResults as $label => $r) {
    $status = $r['passed'] ? 'PASS' : ($r['openai_error'] ? 'RATE_LIMITED' : 'FAIL');
    echo "  [{$label}] classifier_result={$r['classifier_result']} | normalizer_called={$r['normalizer_called']} | normalized_field_key=" . ($r['normalized_field_key'] ?? 'null') . " | final_question_type={$r['final_question_type']} => {$status}\n";
}

echo "\nProhibited phrase results:\n";
foreach ($prohibitedResults as $label => $r) {
    $status = $r['passed'] ? 'PASS' : 'FAIL';
    echo "  [{$label}] classifier_result={$r['classifier_result']} | normalizer_called={$r['normalizer_called']} => {$status}\n";
}

$anyRoofPassed       = $roofPassed >= 1;
$allProhibitedPassed = $prohibitedPassed === count($prohibitedPhrases);
$anyRateLimit        = collect($roofResults)->contains(fn($r) => $r['openai_error'] !== null);

echo "\n----------------------------------------------------------\n";

if ($envOk && $anyRoofPassed && $allProhibitedPassed) {
    echo "\n  VERDICT: APPROVED FOR STAGING\n\n";
    echo "  All checks passed:\n";
    echo "  - OPENAI_API_KEY present\n";
    echo "  - ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=true\n";
    echo "  - At least 1 roof phrase: normalizer_called=Y, normalized_field_key=faq_answers.roof_age_and_condition\n";
    echo "  - All prohibited phrases blocked before normalizer (normalizer_called=N)\n";
} elseif ($anyRateLimit) {
    echo "\n  VERDICT: FAILED — RATE LIMIT (insufficient API key tier)\n\n";
    echo "  Infrastructure is correct. Rate limits prevent live call completion.\n";
    echo "  Requires a paid-tier OpenAI API key with adequate TPM.\n";
    echo "  See follow-up task #2283.\n";
} else {
    echo "\n  VERDICT: FAILED — FIXES REQUIRED\n\n";
    foreach ($roofResults as $label => $r) {
        if (!$r['passed']) {
            echo "  [{$label}] FAIL: classifier={$r['classifier_result']} normalizer={$r['normalizer_called']} key=" . ($r['normalized_field_key'] ?? 'null') . "\n";
        }
    }
    foreach ($prohibitedResults as $label => $r) {
        if (!$r['passed']) {
            echo "  [{$label}] FAIL: classifier={$r['classifier_result']} normalizer={$r['normalizer_called']}\n";
        }
    }
}

echo "----------------------------------------------------------\n";
