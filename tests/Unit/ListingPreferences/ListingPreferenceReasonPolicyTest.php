<?php

namespace Tests\Unit\ListingPreferences;

use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceReasonPolicy;
use App\Support\ListingPreferences\ListingPreferenceReasonSelectionResult;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The reason write boundary: an intersection, never a deny-list.
 */
class ListingPreferenceReasonPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ListingPreferenceReasonCatalog::flush();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function a_valid_reason_survives_and_order_and_uniqueness_are_preserved(): void
    {
        $result = ListingPreferenceReasonPolicy::project(
            ['natural_light', 'updated_kitchen', 'natural_light'],
            ListingPreferenceState::Save,
        );

        $this->assertSame(['natural_light', 'updated_kitchen'], $result->accepted);
        $this->assertFalse($result->hasRejections());
    }

    /** @test */
    public function an_unknown_key_is_rejected_rather_than_stored(): void
    {
        $result = ListingPreferenceReasonPolicy::project(
            ['not_a_real_reason'],
            ListingPreferenceState::Save,
        );

        $this->assertSame([], $result->accepted);
        $this->assertSame(
            ListingPreferenceReasonSelectionResult::REASON_UNKNOWN_KEY,
            $result->rejected['not_a_real_reason']
        );
    }

    /** @test */
    public function a_reason_not_offered_for_the_chosen_state_is_rejected(): void
    {
        $result = ListingPreferenceReasonPolicy::project(
            ['too_expensive'],
            ListingPreferenceState::Save,
        );

        $this->assertSame([], $result->accepted);
        $this->assertSame(
            ListingPreferenceReasonSelectionResult::REASON_NOT_FOR_STATE,
            $result->rejected['too_expensive']
        );

        // …and is accepted for the states that do offer it.
        $pass = ListingPreferenceReasonPolicy::project(['too_expensive'], ListingPreferenceState::Pass);
        $this->assertSame(['too_expensive'], $pass->accepted);
    }

    /** @test */
    public function a_tag_backed_reason_is_rejected_where_its_tag_does_not_apply(): void
    {
        // updated_kitchen is residential; it does not apply to land.
        $result = ListingPreferenceReasonPolicy::project(
            ['updated_kitchen'],
            ListingPreferenceState::Save,
            SmartTagContext::LandSale,
        );

        $this->assertSame([], $result->accepted);
        $this->assertSame(
            ListingPreferenceReasonSelectionResult::REASON_NOT_APPLICABLE,
            $result->rejected['updated_kitchen']
        );

        $residential = ListingPreferenceReasonPolicy::project(
            ['updated_kitchen'],
            ListingPreferenceState::Save,
            SmartTagContext::ResidentialSale,
        );
        $this->assertSame(['updated_kitchen'], $residential->accepted);
    }

    /** @test */
    public function malformed_values_are_rejected_as_not_a_key(): void
    {
        $result = ListingPreferenceReasonPolicy::project(
            ['Updated Kitchen', 'UPPER_CASE', '9leading_digit', 'has-hyphen', str_repeat('x', 200)],
            ListingPreferenceState::Save,
        );

        $this->assertSame([], $result->accepted);

        foreach ($result->rejected as $reason) {
            $this->assertSame(ListingPreferenceReasonSelectionResult::REASON_NOT_A_KEY, $reason);
        }
    }

    /** @test */
    public function non_tag_reasons_are_context_independent(): void
    {
        foreach (SmartTagContext::cases() as $context) {
            $result = ListingPreferenceReasonPolicy::project(
                ['price', 'location_proximity', 'other'],
                ListingPreferenceState::Pass,
                $context,
            );

            $this->assertSame(['price', 'location_proximity', 'other'], $result->accepted, $context->value);
        }
    }

    /**
     * The bridge a future learner crosses from "what the customer said" to
     * "which property characteristic that is".
     *
     * @test
     */
    public function smart_tag_keys_are_resolved_only_for_tag_backed_reasons(): void
    {
        $keys = ListingPreferenceReasonPolicy::smartTagKeysFor(
            ['updated_kitchen', 'price', 'other', 'waterfront', 'location_proximity']
        );

        sort($keys);

        $this->assertSame(['updated_kitchen', 'waterfront'], $keys);
    }

}
