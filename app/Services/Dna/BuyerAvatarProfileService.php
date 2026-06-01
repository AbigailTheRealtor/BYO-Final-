<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use Illuminate\Support\Facades\Log;

/**
 * BuyerAvatarProfileService — Avatar orchestration layer for buyer DNA profiles.
 *
 * Responsibilities:
 *   - Receive a persisted BuyerTenantDnaProfile (buyer only).
 *   - Call BuyerAvatarService::generate() to produce avatar classification and
 *     all derived fields.
 *   - Persist the ten new avatar fields back to the profile row via update().
 *
 * This service does NOT generate DNA dimensions, lifestyle tags, or deal-breaker
 * flags — those remain the responsibility of BuyerTenantDnaGenerator.
 *
 * Avatar generation failures are caught, logged, and silently skipped. The DNA
 * profile row is never rolled back due to an avatar computation error.
 */
class BuyerAvatarProfileService
{
    public function __construct(private BuyerAvatarService $avatarService)
    {
    }

    /**
     * Compute and persist avatar fields for the given buyer DNA profile.
     *
     * Silently no-ops if the profile is not a buyer listing.
     *
     * @param  BuyerTenantDnaProfile $profile  A persisted buyer profile row.
     * @return void
     */
    public function compute(BuyerTenantDnaProfile $profile): void
    {
        if (($profile->listing_type ?? '') !== 'buyer') {
            return;
        }

        try {
            $result = $this->avatarService->generate($profile);

            $profile->update([
                'avatar_type'              => $result['primary_avatar'],
                'primary_motivation'       => $result['primary_motivation'],
                'secondary_motivation'     => $result['secondary_motivation'],
                'buyer_narrative'          => $result['buyer_narrative'],
                'buyer_preference_summary' => $result['buyer_preference_summary'],
                'buyer_personality_tags'   => $result['buyer_personality_tags'],
                'buyer_match_preferences'  => $result['buyer_match_preferences'],
                'avatar_confidence_score'  => $result['avatar_confidence_score'],
                'buyer_avatar_version'     => $result['buyer_avatar_version'],
                'buyer_readiness_score'    => $result['buyer_readiness_score'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BuyerAvatarProfileService: avatar computation failed — DNA profile row unchanged', [
                'listing_type' => $profile->listing_type ?? 'unknown',
                'listing_id'   => $profile->listing_id  ?? null,
                'profile_id'   => $profile->id           ?? null,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
            ]);
        }
    }
}
