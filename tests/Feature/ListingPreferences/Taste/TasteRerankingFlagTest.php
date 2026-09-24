<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Support\ListingPreferences\Taste\TasteDnaAvailability;
use Tests\TestCase;

/**
 * The Phase 5 reranking flag: shipped off, parsed fail-closed, and effective
 * only with BOTH earlier gates on as well.
 */
class TasteRerankingFlagTest extends TestCase
{
    /** @test */
    public function the_reranking_flag_ships_off_and_is_parsed_fail_closed(): void
    {
        $this->assertFalse((require config_path('listing_preferences.php'))['taste_reranking_enabled']);

        foreach (['off', 'no', '0', 'false', '', 'enabled', 'TRUE!'] as $value) {
            putenv("LISTING_PREFERENCE_TASTE_RERANKING_ENABLED={$value}");
            $this->assertFalse((require config_path('listing_preferences.php'))['taste_reranking_enabled'], "'{$value}' must read as off");
        }

        foreach (['true', '1', 'on', 'yes'] as $value) {
            putenv("LISTING_PREFERENCE_TASTE_RERANKING_ENABLED={$value}");
            $this->assertTrue((require config_path('listing_preferences.php'))['taste_reranking_enabled'], "'{$value}' must read as on");
        }

        putenv('LISTING_PREFERENCE_TASTE_RERANKING_ENABLED');
    }

    /** @test */
    public function reranking_needs_all_three_gates_and_each_must_be_exactly_true(): void
    {
        $cases = [
            [[true, true, true], true],
            [[false, true, true], false],
            [[true, false, true], false],
            [[true, true, false], false],
            [[true, true, 'true'], false],
            [[true, true, 1], false],
            [[true, true, null], false],
        ];

        foreach ($cases as [[$prefs, $taste, $rerank], $expected]) {
            config()->set('listing_preferences.enabled', $prefs);
            config()->set('listing_preferences.taste_dna_enabled', $taste);
            config()->set('listing_preferences.taste_reranking_enabled', $rerank);

            $this->assertSame($expected, TasteDnaAvailability::rerankingEnabled(), json_encode([$prefs, $taste, $rerank]));
        }
    }

    /** @test */
    public function switching_taste_dna_on_does_not_switch_reranking_on(): void
    {
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.taste_dna_enabled', true);
        config()->set('listing_preferences.taste_reranking_enabled', false);

        $this->assertTrue(TasteDnaAvailability::enabled());
        $this->assertFalse(TasteDnaAvailability::rerankingEnabled());
    }
}
