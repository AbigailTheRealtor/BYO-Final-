<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiRateLimitService;
use App\Services\AskAi\AskAiUsageLoggerService;
use App\Services\AskAi\AskAiComplianceGuardrailService;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Support\AskAi\AskAiOwnerQuestionSelection;
use App\Support\AskAi\AskAiPublicListingAccess;

class AskAiListingQuestionController extends Controller
{
    private AskAiRunnerV2Service $runner;
    private AskAiUsageLoggerService $logger;
    private AskAiRateLimitService $rateLimiter;
    private AskAiViewerAuthorizationService $viewerAuthorization;

    public function __construct(
        AskAiRunnerV2Service $runner,
        AskAiUsageLoggerService $logger,
        AskAiRateLimitService $rateLimiter,
        AskAiViewerAuthorizationService $viewerAuthorization
    ) {
        $this->runner              = $runner;
        $this->logger              = $logger;
        $this->rateLimiter         = $rateLimiter;
        $this->viewerAuthorization = $viewerAuthorization;
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_type' => ['required', 'string'],
            'listing_id'   => ['required', 'integer'],
            'question'     => ['required_without:question_key', 'nullable', 'string', 'max:1000'],
            // A SELECTED question: the canonical key of one suggested-question registry entry.
            // Sent by the owner question picker so the server answers the registry's own
            // question for that key instead of re-interpreting display text.
            'question_key' => ['nullable', 'string', 'max:200'],
            'options'      => ['nullable', 'array'],
        ]);

        $startTime    = microtime(true);
        $listingType  = $validated['listing_type'];
        $listingId    = (int) $validated['listing_id'];
        $question     = (string) ($validated['question'] ?? '');
        $questionKey  = $validated['question_key'] ?? null;
        $questionHash = hash('sha256', $questionKey !== null ? 'key:' . $questionKey : $question);

        // Authorization is per FACT, not per listing. Everyone who can see a listing's public
        // page may ask about it; what they may learn is decided by the scope the runner is
        // handed, and every fact is filtered by the existing visibility policy for it.
        //
        //   owner                      -> 'owner'  (public facts + owner-permitted facts)
        //   guest / logged-in non-owner -> 'public' (exactly the same answers as each other)
        //
        // A non-owner is admitted only where the listing's own public page would render for
        // them — never a draft, an unapproved or archived listing, a Hire listing, a missing
        // record or an unknown type. Those answer 404, the same as the page.
        $scope = $this->viewerAuthorization->resolveScope(Auth::id(), $listingType, $listingId);
        if ($scope !== AskAiViewerAuthorizationService::SCOPE_OWNER) {
            if (! AskAiPublicListingAccess::isPubliclyViewable($listingType, $listingId)) {
                return response()->json([
                    'success'             => false,
                    'status'              => 'not_found',
                    'answer'              => null,
                    'refusal_message'     => null,
                    'disclosures'         => null,
                    'source_attribution'  => null,
                    'error'               => 'This listing is not available.',
                    'follow_up_questions' => [],
                ], 404);
            }

            // Never a wider tier than a guest's, whatever resolveScope() found.
            $scope = AskAiViewerAuthorizationService::SCOPE_PUBLIC;
        }

        $rateLimitResult = $this->rateLimiter->check($request, $listingType, $listingId);
        if ($rateLimitResult !== null) {
            $retryAfter = $rateLimitResult['retry_after'];

            try {
                $this->logger->logListingQuestion([
                    'listing_type'      => $listingType,
                    'listing_id'        => $listingId,
                    'user_id'           => auth()->id(),
                    'ip_address'        => $request->ip(),
                    'question_hash'     => $questionHash,
                    'question_type'     => null,
                    'status'            => 'rate_limited',
                    'success'           => false,
                    'model'             => null,
                    'response_time_ms'  => null,
                    'error_code'        => $rateLimitResult['limit_type'],
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'api_request_id'    => null,
                ]);
            } catch (\Throwable $logEx) {
            }

            return response()->json([
                'error' => [
                    'message'     => 'You have exceeded the Ask AI rate limit. Please try again later.',
                    'retry_after' => $retryAfter,
                    'limit_type'  => $rateLimitResult['limit_type'],
                ],
            ], 429)->header('Retry-After', $retryAfter);
        }

        // A selected question is resolved by EXACT key against the registry — never fuzzily,
        // never from the display text sent beside it. A key that names no single entry for
        // this role is refused here with the runner's own deterministic refusal, before the
        // runner (and so any model path) is reached at all.
        $selected = null;
        if ($questionKey !== null) {
            $selected = AskAiOwnerQuestionSelection::resolve($listingType, $questionKey);
            if ($selected === null) {
                try {
                    $this->logger->logListingQuestion([
                        'listing_type'      => $listingType,
                        'listing_id'        => $listingId,
                        'user_id'           => auth()->id(),
                        'ip_address'        => $request->ip(),
                        'question_hash'     => $questionHash,
                        'question_type'     => null,
                        'status'            => 'insufficient_context',
                        'success'           => false,
                        'model'             => null,
                        'response_time_ms'  => (int) round((microtime(true) - $startTime) * 1000),
                        'error_code'        => null,
                        'prompt_tokens'     => 0,
                        'completion_tokens' => 0,
                        'total_tokens'      => 0,
                        'api_request_id'    => null,
                        'outcome_category'  => 'unresolved_question_key',
                    ]);
                } catch (\Throwable $logEx) {
                }

                return response()->json([
                    'success'             => false,
                    'status'              => 'insufficient_context',
                    'answer'              => AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE,
                    'refusal_message'     => null,
                    'disclosures'         => [AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER],
                    'disclaimer'          => AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER,
                    'source_attribution'  => [],
                    'source'              => ['answer_source' => 'deterministic_refusal', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => 'unresolved_question_key', 'snapshot_version' => null],
                    'error'               => null,
                    'follow_up_questions' => [],
                ]);
            }
            $question = $selected['question'];
        }

        try {
            // The scope resolved above: 'owner' for the listing's owner, 'public' for everyone
            // else. The runner's per-fact redaction (Part J / C-B) applies to 'public'.
            //
            // Client-supplied runner options are honoured for the owner only, as before this
            // endpoint admitted anyone else. For a non-owner they are dropped whole: keys such
            // as 'normalized_field_key' steer the runner past the public question card, and
            // 'restricted_owner_answer' is echoed back as answer text. No page sends them.
            $options = $scope === AskAiViewerAuthorizationService::SCOPE_OWNER
                ? ($validated['options'] ?? [])
                : [];
            // An owner's selection names its fact: the runner looks that key up directly.
            // (A non-owner's runner options stay dropped whole; their selection reaches the
            // runner as the registry's own question text and is answered at public scope.)
            if ($selected !== null && $scope === AskAiViewerAuthorizationService::SCOPE_OWNER) {
                $options['normalized_field_key'] = $selected['key'];
            }
            $options['viewer_scope']      = $scope;
            $options['requester_user_id'] = Auth::id();

            $result = $this->runner->run(
                $listingType,
                $listingId,
                $question,
                $options
            );

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $status         = $result['status'] ?? 'failed';
            $success        = $result['success'] ?? false;
            $questionType   = $result['classification']['question_type'] ?? null;
            $model          = $result['adapter_result']['model'] ?? null;

            $errorCode = null;
            if ($status === 'blocked') {
                $errorCode = 'blocked';
            } elseif ($status === 'failed') {
                $errorCode = 'failed';
            }

            $adapterResult    = $result['adapter_result'] ?? [];
            $promptTokens     = (int) ($adapterResult['prompt_tokens']     ?? 0);
            $completionTokens = (int) ($adapterResult['completion_tokens'] ?? 0);
            $totalTokens      = (int) ($adapterResult['total_tokens']      ?? 0);
            $apiRequestId     = $adapterResult['api_request_id']           ?? null;

            // Phase 4: read outcome_category from runner result; derive for legacy paths.
            $outcomeCategory = $result['outcome_category'] ?? null;
            if ($outcomeCategory === null) {
                $outcomeCategory = match ($status) {
                    'blocked'              => 'blocked_restricted',
                    'failed'               => 'error',
                    'insufficient_context' => 'openai_fallback',
                    default                => null,
                };
            }

            try {
                $this->logger->logListingQuestion([
                    'listing_type'     => $listingType,
                    'listing_id'       => $listingId,
                    'user_id'          => auth()->id(),
                    'ip_address'       => $request->ip(),
                    'question_hash'    => $questionHash,
                    'question_type'    => $questionType,
                    'status'           => $status,
                    'success'          => $success,
                    'model'            => $model,
                    'response_time_ms' => $responseTimeMs,
                    'error_code'       => $errorCode,
                    'prompt_tokens'     => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens'      => $totalTokens,
                    'api_request_id'    => $apiRequestId,
                    'outcome_category'  => $outcomeCategory,
                ]);
            } catch (\Throwable $logEx) {
            }

            if ($status === 'failed') {
                $pipelineError = $result['error'] ?? null;
                if ($pipelineError) {
                    Log::warning('AskAi pipeline failure', [
                        'listing_type'  => $listingType,
                        'listing_id'    => $listingId,
                        'question_hash' => $questionHash,
                        'question_type' => $questionType,
                        'error'         => $pipelineError,
                    ]);
                }
                return response()->json([
                    'success'            => false,
                    'status'             => 'failed',
                    'answer'             => null,
                    'refusal_message'    => null,
                    'disclosures'        => null,
                    'source_attribution' => null,
                    'error'              => 'Ask AI could not generate a response right now. Please try again later.',
                    'follow_up_questions'=> [],
                ]);
            }

            $final             = $result['final_response'] ?? [];
            $followUpQuestions = $final['follow_up_questions'] ?? [];

            // C-J — surface the standing educational disclaimer on every answered response.
            $disclosures = is_array($final['disclosures'] ?? null) ? array_values($final['disclosures']) : [];
            if (! in_array(AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER, $disclosures, true)) {
                $disclosures[] = AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER;
            }

            return response()->json([
                'success'             => $result['success'] ?? false,
                'status'              => $status,
                'answer'              => $final['answer']             ?? null,
                'refusal_message'     => $final['refusal_message']    ?? null,
                'disclosures'         => $disclosures,
                'disclaimer'          => AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER,
                'source_attribution'  => $final['source_attribution'] ?? null,
                'source'              => $final['source']             ?? null,
                'error'               => null,
                'follow_up_questions' => $followUpQuestions,
            ]);

        } catch (\Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            // Surface unexpected runner/controller fatals that would otherwise be
            // masked by this catch (they are returned to the client as a generic
            // "could not generate" soft-failure). Log only non-sensitive metadata:
            // the question is recorded as a hash, never in cleartext, and no answer
            // or listing context is included.
            Log::error('AskAi listing-question fatal', [
                'listing_type'  => $listingType,
                'listing_id'    => $listingId,
                'question_hash' => $questionHash,
                'exception'     => get_class($e),
                'message'       => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ]);

            try {
                $this->logger->logListingQuestion([
                    'listing_type'     => $listingType,
                    'listing_id'       => $listingId,
                    'user_id'          => auth()->id(),
                    'ip_address'       => $request->ip(),
                    'question_hash'    => $questionHash,
                    'question_type'    => null,
                    'status'           => 'failed',
                    'success'          => false,
                    'model'            => null,
                    'response_time_ms' => $responseTimeMs,
                    'error_code'       => 'failed',
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'api_request_id'    => null,
                    'outcome_category'  => 'error',
                ]);
            } catch (\Throwable $logEx) {
            }

            return response()->json([
                'success'             => false,
                'status'              => 'failed',
                'answer'              => null,
                'refusal_message'     => null,
                'disclosures'         => null,
                'source_attribution'  => null,
                'source'              => null,
                'error'               => 'Ask AI could not generate a response right now. Please try again later.',
                'follow_up_questions' => [],
            ]);
        }
    }

}
