<?php

namespace Tests\Unit\ListingPreferences;

use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceReasonDimension;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The reason vocabulary: valid, closed, and tied to the Smart Tag taxonomy only
 * where a real canonical tag exists.
 *
 * Pure — runs without a booted application, the same way the Smart Tag taxonomy
 * and selection policy tests do.
 */
class ListingPreferenceReasonCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ListingPreferenceReasonCatalog::flush();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function the_reason_catalog_is_structurally_valid(): void
    {
        $this->assertSame([], ListingPreferenceReasonCatalog::validationErrors());
        $this->assertNotSame('', ListingPreferenceReasonCatalog::version());
        $this->assertNotEmpty(ListingPreferenceReasonCatalog::all());
    }

    /** @test */
    public function every_smart_tag_reason_names_a_canonical_active_seeker_selectable_tag(): void
    {
        $linked = 0;

        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            if (! $reason->dimension->requiresSmartTag()) {
                continue;
            }

            $linked++;
            $tag = $reason->smartTag();

            $this->assertNotNull($tag, "{$key}: links to a Smart Tag that does not exist");
            $this->assertTrue($tag->isActive(), "{$key}: links to a retired Smart Tag");
            $this->assertTrue(
                $tag->isSeekerSelectable(),
                "{$key}: links to a Smart Tag that is not seeker-selectable"
            );
        }

        $this->assertGreaterThan(0, $linked, 'No reason links to a Smart Tag — the link is the point.');
    }

    /** @test */
    public function only_smart_tag_reasons_carry_a_tag_and_only_criteria_reasons_carry_a_dimension(): void
    {
        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            if ($reason->dimension->requiresSmartTag()) {
                $this->assertNotNull($reason->smartTagKey, "{$key}: a smart_tag reason must name a tag");
            } else {
                $this->assertNull($reason->smartTagKey, "{$key}: only a smart_tag reason may name a tag");
            }

            if ($reason->dimension->requiresCriteriaDimension()) {
                $this->assertContains(
                    $reason->criteriaDimension,
                    ListingPreferenceReasonCatalog::CRITERIA_DIMENSIONS,
                    "{$key}: unknown criteria dimension"
                );
            } else {
                $this->assertNull($reason->criteriaDimension, "{$key}: only a criteria reason may name a dimension");
            }
        }
    }

    /**
     * Price, size and proximity are excluded from the Smart Tag taxonomy by its
     * own header. A reason about them must NOT be forced into a tag.
     *
     * @test
     */
    public function price_size_and_location_reasons_are_not_smart_tags(): void
    {
        foreach (['price', 'too_expensive', 'too_small', 'too_large', 'hoa_fees'] as $key) {
            $reason = ListingPreferenceReasonCatalog::get($key);
            $this->assertNotNull($reason, "{$key} is missing");
            $this->assertSame(ListingPreferenceReasonDimension::Criteria, $reason->dimension, "{$key}");
            $this->assertNull($reason->smartTagKey, "{$key} must not be a Smart Tag");
        }

        foreach (['location_proximity', 'busy_road', 'commute'] as $key) {
            $reason = ListingPreferenceReasonCatalog::get($key);
            $this->assertNotNull($reason, "{$key} is missing");
            $this->assertSame(ListingPreferenceReasonDimension::Location, $reason->dimension, "{$key}");
            $this->assertNull($reason->smartTagKey, "{$key} must not be a Smart Tag");
        }
    }

    /**
     * The customer's reason vocabulary spans three subsystems. If every reason
     * fell into one dimension, either the tag taxonomy has been widened into
     * criteria, or criteria have been smuggled into tags.
     *
     * @test
     */
    public function all_four_dimensions_are_represented(): void
    {
        $seen = [];

        foreach (ListingPreferenceReasonCatalog::all() as $reason) {
            $seen[$reason->dimension->value] = true;
        }

        foreach (ListingPreferenceReasonDimension::values() as $dimension) {
            $this->assertArrayHasKey($dimension, $seen, "no reason uses the {$dimension} dimension");
        }
    }

    /** @test */
    public function unspecified_reasons_are_never_learnable_and_others_are(): void
    {
        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            if ($reason->dimension === ListingPreferenceReasonDimension::Unspecified) {
                $this->assertFalse($reason->isLearnable(), "{$key}: unspecified reasons must never be learnable");
            } elseif ($reason->isActive()) {
                $this->assertTrue($reason->isLearnable(), "{$key}: should be learnable");
            }
        }

        $this->assertArrayNotHasKey('other', ListingPreferenceReasonCatalog::learnable());
        $this->assertArrayNotHasKey('layout', ListingPreferenceReasonCatalog::learnable());
    }

    /** @test */
    public function each_state_offers_chips_and_the_prompts_differ(): void
    {
        $prompts = [];

        foreach (ListingPreferenceState::cases() as $state) {
            $this->assertNotEmpty(
                ListingPreferenceReasonCatalog::forState($state),
                "no reasons offered for {$state->value}"
            );
            $prompts[] = $state->prompt();
        }

        $this->assertCount(3, array_unique($prompts), 'each state must ask its own question');
    }

    /**
     * "Too expensive" is not an answer to "What do you like about this
     * property?" — a reason declares the states it may be offered for.
     *
     * @test
     */
    public function negative_reasons_are_not_offered_as_compliments(): void
    {
        $save = ListingPreferenceReasonCatalog::forState(ListingPreferenceState::Save);

        $this->assertArrayNotHasKey('too_expensive', $save);
        $this->assertArrayNotHasKey('too_small', $save);
        $this->assertArrayNotHasKey('needs_complete_update', $save);
        $this->assertArrayNotHasKey('busy_road', $save);

        $this->assertArrayHasKey('updated_kitchen', $save);
        $this->assertArrayHasKey('natural_light', $save);
    }

    /** @test */
    public function chips_are_returned_in_display_order(): void
    {
        $orders = array_map(
            static fn ($r): int => $r->displayOrder,
            array_values(ListingPreferenceReasonCatalog::forState(ListingPreferenceState::Pass))
        );

        $sorted = $orders;
        sort($sorted);

        $this->assertSame($sorted, $orders);
    }

    /**
     * A tag-backed reason inherits its tag's contexts rather than restating
     * them, so a context added to a tag cannot drift out of sync with its chip.
     *
     * @test
     */
    public function tag_backed_reasons_inherit_their_tag_contexts(): void
    {
        $waterfront = ListingPreferenceReasonCatalog::get('waterfront');
        $this->assertNotNull($waterfront);

        $tag = SmartTagTaxonomy::get('waterfront');
        $this->assertNotNull($tag);

        foreach (SmartTagContext::cases() as $context) {
            $this->assertSame(
                $tag->appliesTo($context),
                $waterfront->appliesToContext($context),
                "waterfront reason and tag disagree about {$context->value}"
            );
        }

        // A non-tag reason applies wherever a customer can express a preference.
        $price = ListingPreferenceReasonCatalog::get('price');
        $this->assertNotNull($price);
        $this->assertCount(count(SmartTagContext::cases()), $price->contexts());
    }

    /** @test */
    public function reason_keys_do_not_silently_duplicate_a_smart_tag_label(): void
    {
        $tagLabels = [];
        foreach (SmartTagTaxonomy::all() as $tagKey => $tag) {
            $tagLabels[mb_strtolower($tag->label)] = $tagKey;
        }

        foreach (ListingPreferenceReasonCatalog::all() as $key => $reason) {
            $label = mb_strtolower($reason->label);

            if (! isset($tagLabels[$label])) {
                continue;
            }

            $this->assertSame(
                $tagLabels[$label],
                $reason->smartTagKey,
                "{$key}: reuses the label of Smart Tag {$tagLabels[$label]} without linking to it — "
                . 'that is the duplicate vocabulary this catalog exists to prevent'
            );
        }
    }
}
