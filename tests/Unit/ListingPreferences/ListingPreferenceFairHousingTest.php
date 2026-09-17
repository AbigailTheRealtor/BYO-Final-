<?php

namespace Tests\Unit\ListingPreferences;

use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceReasonDimension;
use App\Support\ListingPreferences\ListingPreferenceReasonPolicy;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * Fair Housing, at the reason vocabulary.
 *
 * The guard is the SAME one the Smart Tag taxonomy uses — not a copy — so
 * changing the prohibited patterns is one reviewed code change and cannot be
 * done from config.
 */
class ListingPreferenceFairHousingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ListingPreferenceReasonCatalog::flush();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function every_reason_key_and_label_is_free_of_prohibited_concepts(): void
    {
        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            $this->assertSame(
                [],
                SmartTagComplianceGuard::violations(str_replace('_', ' ', $key)),
                "reason key {$key} contains a prohibited concept"
            );
            $this->assertSame(
                [],
                SmartTagComplianceGuard::violations($reason->label),
                "reason label for {$key} contains a prohibited concept"
            );
        }
    }

    /**
     * The tags excluded as seeker preferences for Fair Housing reasons —
     * accessible_features (disability) and playground (familial status) — cannot
     * become chips, and the exclusion is inherited rather than restated here.
     *
     * @test
     */
    public function tags_excluded_as_seeker_preferences_can_never_become_reason_chips(): void
    {
        foreach (['accessible_features', 'playground'] as $excluded) {
            $tag = SmartTagTaxonomy::get($excluded);
            $this->assertNotNull($tag, "{$excluded} is missing from the taxonomy");
            $this->assertFalse(
                $tag->isSeekerSelectable(),
                "{$excluded} must not be seeker-selectable — the chip list inherits this"
            );

            foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
                $this->assertNotSame(
                    $excluded,
                    $reason->smartTagKey,
                    "reason {$key} links to {$excluded}, which is excluded as a seeker preference"
                );
            }
        }
    }

    /**
     * The write boundary refuses a tag that is not seeker-selectable even when a
     * request names it directly — the catalog gate and the policy gate are two
     * checks of ONE rule, both reading the one taxonomy.
     *
     * @test
     */
    public function the_policy_refuses_a_non_seeker_selectable_tag_key(): void
    {
        foreach (['accessible_features', 'playground'] as $excluded) {
            $result = ListingPreferenceReasonPolicy::project(
                [$excluded],
                ListingPreferenceState::Save,
            );

            $this->assertSame([], $result->accepted, "{$excluded} must not be accepted as a reason");
            $this->assertArrayHasKey($excluded, $result->rejected);
        }
    }

    /**
     * A location reason must never be a Smart Tag, and must never be worded as a
     * claim about who lives somewhere. Location learning is restricted by
     * governance to the customer's OWN stated places; the vocabulary must not
     * make a neighbourhood judgement expressible in the first place.
     *
     * @test
     */
    public function location_reasons_describe_proximity_not_neighbourhoods(): void
    {
        $found = 0;

        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            if ($reason->dimension !== ListingPreferenceReasonDimension::Location) {
                continue;
            }

            $found++;
            $this->assertNull($reason->smartTagKey, "{$key}: location is Location DNA's territory, never a tag");
            $this->assertSame(
                [],
                SmartTagComplianceGuard::violations($reason->label),
                "{$key}: a location reason must not make a neighbourhood or demographic claim"
            );
        }

        $this->assertGreaterThan(0, $found);
    }

    /**
     * Free text is not a reason key. A request cannot add to the vocabulary, so
     * a customer's typed words can never become a learned signal by accident.
     *
     * @test
     */
    public function free_text_is_never_accepted_as_a_reason(): void
    {
        $result = ListingPreferenceReasonPolicy::project(
            [
                'No families with children',
                'prefer_young_professionals',
                'good schools',
                '',
                123,
                ['nested'],
                null,
            ],
            ListingPreferenceState::Pass,
        );

        $this->assertSame([], $result->accepted);
        $this->assertTrue($result->hasRejections());
    }
}
