<?php

namespace App\Services\AskAi;

use App\Models\AskAiUsageLog;

class AskAiUsageLoggerService
{
    public function logListingQuestion(array $payload): ?AskAiUsageLog
    {
        try {
            $log = new AskAiUsageLog();
            $log->listing_type     = $payload['listing_type']     ?? null;
            $log->listing_id       = $payload['listing_id']       ?? null;
            $log->user_id          = $payload['user_id']          ?? null;
            $log->ip_address       = $payload['ip_address']       ?? null;
            $log->question_hash    = $payload['question_hash']    ?? null;
            $log->question_type    = $payload['question_type']    ?? null;
            $log->status           = $payload['status']           ?? null;
            $log->success          = $payload['success']          ?? false;
            $log->model            = $payload['model']            ?? null;
            $log->response_time_ms = $payload['response_time_ms'] ?? null;
            $log->error_code       = $payload['error_code']       ?? null;
            $log->save();

            return $log;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
