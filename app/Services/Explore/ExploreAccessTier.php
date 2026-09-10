<?php

namespace App\Services\Explore;

/**
 * What this consumer is entitled to see — the seam the whole Explore
 * architecture is shaped around.
 *
 * TWO TIERS, DECIDED SERVER-SIDE, ALWAYS
 * --------------------------------------
 * PUBLIC_IDX is everything Explore serves today: eligible Stellar records this
 * application is already authorised to display to anyone.
 *
 * VOW_REGISTERED is the future authenticated tier. It is declared here and
 * nothing returns it — {@see Vow\VowAvailability} refuses first — because the
 * point of naming it now is that the projection, the repository and the
 * controller all take a tier as an argument rather than assuming one. Adding
 * the tier later is then a matter of resolving it and widening one projection,
 * not of rebuilding the map, the viewport API or the property panel.
 *
 * WHY THIS IS NOT A BOOLEAN
 * -------------------------
 * `is_mls_visible = true/false` is the shape this deliberately avoids. One
 * boolean asked on behalf of every consumer cannot express the situation the
 * two tiers exist for — a listing that is ineligible for one audience and
 * eligible for another. Even with no VOW data available, encoding the question
 * as "which tier" rather than "visible or not" is what keeps that expressible.
 */
enum ExploreAccessTier: string
{
    case PUBLIC_IDX      = 'public_idx';
    case VOW_REGISTERED  = 'vow_registered';

    /** The tier an unauthenticated or unverified consumer always gets. */
    public static function default(): self
    {
        return self::PUBLIC_IDX;
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLIC_IDX;
    }
}
