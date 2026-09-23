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
 * PHASE 5 adds exactly one consumer, and a third gate for it: the bounded Best
 * Match rerank on the Stellar results page (`rerankingEnabled()`). Matching, the
 * score, Ask AI and recommendations still never read Taste DNA
 * (TasteDnaArchitectureGuardTest), and `listing_preferences.learning_enabled`
 * stays hard-off for everything beyond that rerank.
 */
final class TasteDnaAvailability
{
    public static function enabled(): bool
    {
        return ListingPreferenceAvailability::featureEnabled()
            && config('listing_preferences.taste_dna_enabled') === true;
    }

    /**
     * Whether Taste DNA may reorder near-tied Best Match results.
     *
     * All three gates, each `=== true`: Save / Maybe / Pass, Taste DNA, and
     * reranking itself. Any one off — or a config that did not load — is off.
     */
    public static function rerankingEnabled(): bool
    {
        return self::enabled()
            && config('listing_preferences.taste_reranking_enabled') === true;
    }
}
