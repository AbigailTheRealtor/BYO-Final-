<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WizardEventService
{
    private const VALID_MODES = ['create', 'edit'];
    private const VALID_ROLES = ['seller', 'buyer', 'landlord', 'tenant'];
    private const VALID_EVENT_TYPES = ['tab_visited', 'save_draft', 'submit', 'publish'];
    private const DEDUP_MINUTES = 5;

    public function record(
        string $role,
        ?int $listingId,
        ?int $userId,
        string $eventType,
        string $tabName,
        ?string $mode,
        ?string $sessionId
    ): void {
        if (!in_array($role, self::VALID_ROLES, true)) {
            Log::warning('WizardEventService: invalid role value, skipping.', [
                'role'       => $role,
                'event_type' => $eventType,
            ]);
            return;
        }

        if (!in_array($eventType, self::VALID_EVENT_TYPES, true)) {
            Log::warning('WizardEventService: invalid event_type value, skipping.', [
                'role'       => $role,
                'event_type' => $eventType,
            ]);
            return;
        }

        if ($mode !== null && !in_array($mode, self::VALID_MODES, true)) {
            Log::warning('WizardEventService: invalid mode value, skipping.', [
                'mode'       => $mode,
                'role'       => $role,
                'event_type' => $eventType,
            ]);
            return;
        }

        if ($eventType === 'tab_visited' && $this->isDuplicate($role, $listingId, $sessionId, $tabName)) {
            return;
        }

        try {
            DB::table('wizard_events')->insert([
                'listing_role' => $role,
                'listing_id'   => $listingId,
                'user_id'      => $userId,
                'event_type'   => $eventType,
                'tab_name'     => $tabName,
                'session_id'   => $sessionId,
                'mode'         => $mode,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WizardEventService: DB write failed, event not recorded.', [
                'error'      => $e->getMessage(),
                'role'       => $role,
                'event_type' => $eventType,
                'tab_name'   => $tabName,
            ]);
        }
    }

    private function isDuplicate(string $role, ?int $listingId, ?string $sessionId, string $tabName): bool
    {
        try {
            return DB::table('wizard_events')
                ->where('listing_role', $role)
                ->where('listing_id', $listingId)
                ->where('session_id', $sessionId)
                ->where('tab_name', $tabName)
                ->where('event_type', 'tab_visited')
                ->where('created_at', '>=', now()->subMinutes(self::DEDUP_MINUTES))
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('WizardEventService: dedup check failed, allowing insert.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
