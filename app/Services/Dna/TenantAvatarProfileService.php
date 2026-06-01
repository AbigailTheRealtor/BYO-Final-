<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use Illuminate\Support\Facades\Log;

/**
 * TenantAvatarProfileService — Avatar orchestration layer for tenant DNA profiles.
 *
 * Responsibilities:
 *   - Receive a persisted BuyerTenantDnaProfile (tenant only).
 *   - Call TenantAvatarService::generate() to produce avatar classification and
 *     all derived fields.
 *   - Persist the eight avatar fields back to the profile row via update().
 *
 * This service does NOT generate DNA dimensions, lifestyle tags, or deal-breaker
 * flags — those remain the responsibility of BuyerTenantDnaGenerator.
 *
 * Avatar generation failures are caught, logged, and silently skipped. The DNA
 * profile row is never rolled back due to an avatar computation error.
 */
class TenantAvatarProfileService
{
    public function __construct(private TenantAvatarService $avatarService)
    {
    }

    /**
     * Compute and persist avatar fields for the given tenant DNA profile.
     *
     * Silently no-ops if the profile is not a tenant listing.
     *
     * @param  BuyerTenantDnaProfile $profile  A persisted tenant profile row.
     * @return void
     */
    public function compute(BuyerTenantDnaProfile $profile): void
    {
        if (($profile->listing_type ?? '') !== 'tenant') {
            return;
        }

        try {
            $result = $this->avatarService->generate($profile);

            $profile->update([
                'avatar_type'               => $result['primary_avatar'],
                'primary_motivation'        => $result['primary_motivation'],
                'secondary_motivation'      => $result['secondary_motivation'],
                'tenant_narrative'          => $result['tenant_narrative'],
                'tenant_preference_summary' => $result['tenant_preference_summary'],
                'tenant_personality_tags'   => $result['tenant_personality_tags'],
                'tenant_match_preferences'  => $result['tenant_match_preferences'],
                'avatar_confidence_score'   => $result['avatar_confidence_score'],
                'tenant_avatar_version'     => $result['tenant_avatar_version'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('TenantAvatarProfileService: avatar computation failed — DNA profile row unchanged', [
                'listing_type' => $profile->listing_type ?? 'unknown',
                'listing_id'   => $profile->listing_id  ?? null,
                'profile_id'   => $profile->id           ?? null,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
            ]);
        }
    }
}
