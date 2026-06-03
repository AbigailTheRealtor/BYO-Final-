<?php

namespace App\Services\AskAi;

use App\Models\AskAiUsageLog;
use Illuminate\Support\Facades\Log;

class AskAiUsageLoggerService
{
    public function logListingQuestion(array $payload): ?AskAiUsageLog
    {
        try {
            $promptTokens     = (int) ($payload['prompt_tokens']     ?? 0);
            $completionTokens = (int) ($payload['completion_tokens'] ?? 0);
            $totalTokens      = (int) ($payload['total_tokens']      ?? 0);

            $estimatedCost = null;
            if ($promptTokens > 0 || $completionTokens > 0) {
                $estimatedCost = $this->calculateCost(
                    $payload['model'] ?? null,
                    $promptTokens,
                    $completionTokens
                );
            }

            $log = new AskAiUsageLog();
            $log->listing_type      = $payload['listing_type']      ?? null;
            $log->listing_id        = $payload['listing_id']        ?? null;
            $log->user_id           = $payload['user_id']           ?? null;
            $log->ip_address        = $payload['ip_address']        ?? null;
            $log->question_hash     = $payload['question_hash']     ?? null;
            $log->question_type     = $payload['question_type']     ?? null;
            $log->status            = $payload['status']            ?? null;
            $log->success           = $payload['success']           ?? false;
            $log->model             = $payload['model']             ?? null;
            $log->response_time_ms  = $payload['response_time_ms']  ?? null;
            $log->error_code        = $payload['error_code']        ?? null;
            $log->prompt_tokens     = $promptTokens;
            $log->completion_tokens = $completionTokens;
            $log->total_tokens      = $totalTokens;
            $log->estimated_cost_usd = $estimatedCost;
            $log->api_request_id    = $payload['api_request_id']    ?? null;
            $log->save();

            return $log;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function calculateCost(?string $model, int $promptTokens, int $completionTokens): ?float
    {
        try {
            if ($model === null || $model === '') {
                return null;
            }

            $rates = config('ai.ask_ai_costs.model_rates.' . $model);

            if ($rates === null) {
                Log::warning('Ask AI cost calculation: model rate not found in config.', [
                    'model' => $model,
                ]);
                return null;
            }

            $promptRate     = (float) ($rates['prompt_cost_per_1k_tokens']     ?? 0);
            $completionRate = (float) ($rates['completion_cost_per_1k_tokens'] ?? 0);

            return ($promptTokens / 1000 * $promptRate)
                 + ($completionTokens / 1000 * $completionRate);

        } catch (\Throwable $e) {
            return null;
        }
    }
}
