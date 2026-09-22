<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\ListingPreferences\ListingPreferenceAvailability;

/**
 * ONE answer to "does Your Home Taste exist on this deployment?"
 *
 * Two gates, both required, both fail-closed (`=== true`):
 *
 *   listing_preferences.enabled            Save | Maybe | Pass itself. With it off
 *                                          there is no history to learn from and
 *                                          no management area to put the page in.
 *   listing_preferences.taste_dna_enabled  this page. Separate on purpose: base
 *                                          Save / Maybe / Pass must be able to run
 *                                          with Taste DNA off, and turning capture
 *                                          on must never also turn learning on.
 *
 * Read by the route middleware and the management page's tab alike, so the link
 * and the endpoint cannot disagree about whether the page exists.
 *
 * WHAT THIS DOES NOT GATE: ranking, matching, Stellar, Ask AI or recommendations.
 * Nothing there reads Taste DNA at all (TasteDnaArchitectureGuardTest), so there
 * is nothing for a flag to switch. `listing_preferences.learning_enabled` stays
 * hard-off for that later work.
 */
final class TasteDnaAvailability
{
    public static function enabled(): bool
    {
        return ListingPreferenceAvailability::featureEnabled()
            && config('listing_preferences.taste_dna_enabled') === true;
    }
}
