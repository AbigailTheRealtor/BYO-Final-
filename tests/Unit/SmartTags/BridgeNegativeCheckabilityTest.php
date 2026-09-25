<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Services\SmartTags\Seeker\BridgeSmartTagCheckability as C;
use App\Services\SmartTags\SmartTagResolver;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * Presence and absence are separate claims (`bridge.negative_evidence`).
 *
 * A rule that can EMIT a tag may prove it absent only when it is declared able to, and only
 * sources whose answer IS the question are declared: Y/N fields, counts, single-select enums
 * and status/type fields. A sparse "select all that apply" checklist can say yes and never
 * no. Undeclared means unknown — which scoring leaves out of the denominator and the
 * customer wording never calls "Does not list". Every value below is a string the Stellar
 * feed actually sends.
 */
class BridgeNegativeCheckabilityTest extends TestCase
{
    use BuildsSmartTagRecords;

    private BridgeStructuredTagDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        SmartTagConfig::flush();
        SmartTagSourceRules::flush();
        $this->deriver = new BridgeStructuredTagDeriver();
    }

    /**
     * A record carrying ONLY the given raw fields (plus the property type), so no fixture
     * field can answer for the tag under test.
     *
     * @param array<string, mixed> $raw
     */
    private function record(array $raw, string $type = 'Residential'): BridgeRecordAccessor
    {
        return $this->bridgeRecord(['PropertyType' => $type] + $raw);
    }

    /**
     * What matching would answer, from the in-memory derivation — the same classify() the
     * matcher index and the coverage audit call.
     *
     * @param list<string> $keys
     * @return array<string, array{0: string, 1: ?string}> key => [answer, why]
     */
    private function answers(BridgeRecordAccessor $record, array $keys, bool $withResolution = true): array
    {
        $context = $this->deriver->contextFor($record);
        $this->assertNotNull($context);

        $resolved = [];
        $dropped = [];
        if ($withResolution) {
            $resolution = SmartTagResolver::resolve(array_values($this->deriver->derive($record, $context)), $context);
            foreach ($resolution->assignments as $key => $assignment) {
                $resolved[$key] = $assignment->state;
            }
            $dropped = array_fill_keys($resolution->droppedForConflict, true);
        }

        $out = [];
        foreach (C::classify($record, $context, $keys, $resolved, $dropped, true) as $key => $answer) {
            $out[$key] = [$answer, C::explain($record, $context, $key, $resolved, $dropped, true)];
        }

        return $out;
    }

    private function answer(BridgeRecordAccessor $record, string $key, bool $withResolution = true): string
    {
        return $this->answers($record, [$key], $withResolution)[$key][0];
    }

    private function why(BridgeRecordAccessor $record, string $key): ?string
    {
        return $this->answers($record, [$key])[$key][1];
    }

    /** @return array<string, string> tag => state, as derived */
    private function derived(BridgeRecordAccessor $record): array
    {
        return $this->states($this->deriver->derive($record, $this->deriver->contextFor($record)));
    }

    // ── sparse checklists: yes, never no ─────────────────────────────────

    /** @test */
    public function a_sparse_checklist_value_is_present_and_its_omission_is_unknown(): void
    {
        $ctx = SmartTagContext::ResidentialSale;
        $cases = [
            'vaulted_ceilings' => ['InteriorFeatures', 'Vaulted Ceiling(s)', ['Walk-In Closet(s)', 'Split Bedroom']],
            'quartz_countertops' => ['InteriorFeatures', 'Quartz Counters', ['Walk-In Closet(s)']],
            'outdoor_kitchen'  => ['ExteriorFeatures', 'Outdoor Kitchen', ['Sidewalk', 'Lighting']],
            'clubhouse'        => ['CommunityFeatures', 'Clubhouse', ['Pool', 'Sidewalks']],
            'pickleball_court' => ['AssociationAmenities', 'Pickleball Court(s)', ['Pool', 'Clubhouse']],
            'wine_refrigerator' => ['Appliances', 'Wine Refrigerator', ['Range', 'Dishwasher']],
            'corner_lot'       => ['LotFeatures', 'Corner Lot', ['Sidewalk']],
            'screened_lanai_porch' => ['PatioAndPorchFeatures', 'Screened', ['Rear Porch']],
            'bonus_room'       => ['STELLAR_AdditionalRooms', 'Bonus Room', ['Great Room']],
            'hardwood_flooring' => ['Flooring', 'Hardwood', ['Carpet', 'Ceramic Tile']],
            'in_unit_laundry'  => ['LaundryFeatures', 'Inside', ['Common Area']],
            'heated_pool'      => ['PoolFeatures', 'Heated', ['In Ground', 'Gunite']],
        ];

        foreach ($cases as $tag => [$field, $value, $others]) {
            $this->assertTrue(C::hasStructuredCapability($tag, $ctx), $tag);
            $this->assertFalse(C::hasNegativeCapability($tag, $ctx), "{$field} must never rule out {$tag}");

            $this->assertSame(C::PRESENT, $this->answer($this->record([$field => array_merge($others, [$value])]), $tag), "{$tag} present");

            $omitted = $this->record([$field => $others]);
            $this->assertSame(C::UNKNOWN, $this->answer($omitted, $tag), "{$tag} omitted");
            $this->assertSame(C::WHY_NO_NEGATIVE_EVIDENCE, $this->why($omitted, $tag), $tag);
        }
    }

    /** @test */
    public function a_rule_can_emit_a_tag_it_is_not_allowed_to_rule_out(): void
    {
        $interior = $this->ruleById('bridge.interior_features');
        $this->assertContains('quartz_countertops', SmartTagSourceRules::tagsEmittableBy($interior));
        $this->assertNull(SmartTagSourceRules::negativeEvidence($interior, 'quartz_countertops'));
        $this->assertNull(SmartTagSourceRules::negativeEvidence($interior, 'vaulted_ceilings'));
    }

    /** @test */
    public function stone_counters_neither_prove_nor_rule_out_quartz_or_granite(): void
    {
        $answers = $this->answers($this->record(['InteriorFeatures' => ['Stone Counters', 'Solid Wood Cabinets']]),
            ['quartz_countertops', 'granite_countertops', 'stone_countertops']);

        $this->assertSame(C::UNKNOWN, $answers['quartz_countertops'][0]);
        $this->assertSame(C::UNKNOWN, $answers['granite_countertops'][0]);
        $this->assertSame(C::PRESENT, $answers['stone_countertops'][0]);
    }

    /** @test */
    public function a_generic_range_does_not_rule_out_a_gas_range(): void
    {
        $this->assertSame(C::UNKNOWN, $this->answer($this->record(['Appliances' => ['Range', 'Cooktop']]), 'gas_range'));
        $this->assertSame(C::PRESENT, $this->answer($this->record(['Appliances' => ['Range Gas']]), 'gas_range'));
    }

    /** @test */
    public function neither_community_field_rules_out_an_amenity(): void
    {
        foreach ([['CommunityFeatures' => ['Clubhouse', 'Pool']], ['AssociationAmenities' => ['Pool', 'Clubhouse']]] as $raw) {
            foreach (['pickleball_court', 'basketball_court', 'golf_course_community', 'walking_trails', 'dog_park', 'gated_community'] as $tag) {
                $this->assertSame(C::UNKNOWN, $this->answer($this->record($raw), $tag), $tag . ' via ' . array_key_first($raw));
            }
        }
    }

    /** @test */
    public function window_treatments_do_not_rule_out_impact_windows(): void
    {
        $this->assertSame(C::UNKNOWN, $this->answer($this->record(['WindowFeatures' => ['Blinds', 'Drapes']]), 'impact_windows'));
    }

    // ── strong sources: their answer is the question ─────────────────────

    /** @test */
    public function a_yes_no_field_answers_false_as_a_miss_and_null_as_unknown(): void
    {
        $no = $this->record(['CarportYN' => false]);
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($no, 'carport'), 'stored structured No');
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($no, 'carport', false), 'and the Y/N rule itself may say no');
        $this->assertSame(C::PRESENT, $this->answer($this->record(['CarportYN' => true]), 'carport'));

        foreach ([['CarportYN' => null], []] as $raw) {
            $this->assertSame(C::UNKNOWN, $this->answer($this->record($raw), 'carport'));
        }
    }

    /** @test */
    public function fireplace_is_answered_by_fireplace_yn_never_by_interior_features_silence(): void
    {
        $interior = ['InteriorFeatures' => ['Ceiling Fan(s)', 'Open Floorplan']];

        $this->assertSame(C::PRESENT, $this->answer($this->record($interior + ['FireplaceYN' => true]), 'fireplace'));
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record($interior + ['FireplaceYN' => false]), 'fireplace'));

        $null = $this->record($interior + ['FireplaceYN' => null]);
        $this->assertSame(C::UNKNOWN, $this->answer($null, 'fireplace'));
        $this->assertSame(C::WHY_NO_NEGATIVE_EVIDENCE, $this->why($null, 'fireplace'));
    }

    /** @test */
    public function a_type_field_that_names_the_system_rules_out_the_others(): void
    {
        $this->assertTrue(C::hasNegativeCapability('central_air', SmartTagContext::ResidentialSale));

        $this->assertSame(C::PRESENT, $this->answer($this->record(['Cooling' => ['Central Air']]), 'central_air'));
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['Cooling' => ['Wall/Window Unit(s)']]), 'central_air'));
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['Cooling' => ['None']]), 'central_air'));

        foreach ([['Cooling' => []], []] as $raw) {
            $this->assertSame(C::UNKNOWN, $this->answer($this->record($raw), 'central_air'));
            $this->assertSame(C::WHY_FIELD_UNAVAILABLE, $this->why($this->record($raw), 'central_air'));
        }
    }

    /** @test */
    public function single_select_enums_rule_out_the_other_values(): void
    {
        $owner = $this->answers($this->record(['OccupantType' => 'Owner']), ['tenant_occupied', 'vacant']);
        $this->assertSame(C::KNOWN_ABSENT, $owner['tenant_occupied'][0]);
        $this->assertSame(C::KNOWN_ABSENT, $owner['vacant'][0]);

        $lease = $this->answers($this->record(['Furnished' => 'Unfurnished'], 'Residential Lease'), ['furnished', 'unfurnished']);
        $this->assertSame(C::KNOWN_ABSENT, $lease['furnished'][0]);
        $this->assertSame(C::PRESENT, $lease['unfurnished'][0]);

        $this->assertSame(C::UNKNOWN, $this->answer($this->record(['Furnished' => null], 'Residential Lease'), 'furnished'));
    }

    /**
     * "Negotiable" affirms neither furnished nor unfurnished, so it can never produce
     * "Does not list: Furnished" — and it maps to neither tag.
     *
     * @test
     */
    public function furnished_negotiable_is_unknown_never_a_miss_and_never_a_match(): void
    {
        $lease = fn ($value) => $this->record(['Furnished' => $value], 'Residential Lease');

        $this->assertSame(C::PRESENT, $this->answer($lease('Furnished'), 'furnished'));
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($lease('Unfurnished'), 'furnished'));

        $negotiable = $lease('Negotiable');
        $this->assertSame(C::UNKNOWN, $this->answer($negotiable, 'furnished'));
        $this->assertSame(C::WHY_UNINFORMATIVE, $this->why($negotiable, 'furnished'));
        $this->assertArrayNotHasKey('furnished', $this->derived($negotiable), 'not mapped to furnished');
        $this->assertArrayNotHasKey('unfurnished', $this->derived($negotiable), 'not mapped to unfurnished');

        foreach ([null, ''] as $missing) {
            $this->assertSame(C::UNKNOWN, $this->answer($lease($missing), 'furnished'));
            $this->assertSame(C::WHY_FIELD_UNAVAILABLE, $this->why($lease($missing), 'furnished'));
        }
        $this->assertSame(C::UNKNOWN, $this->answer($this->record([], 'Residential Lease'), 'furnished'));
    }

    /** @test */
    public function fencing_can_only_rule_out_a_fence_with_an_explicit_none(): void
    {
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['Fencing' => ['None']]), 'fenced_yard'));
        $this->assertSame(C::PRESENT, $this->answer($this->record(['Fencing' => ['Wood']]), 'fenced_yard'));
        // Fencing's "Other" is a fence type, never treated as uninformative.
        $this->assertSame(C::PRESENT, $this->answer($this->record(['Fencing' => ['Other']]), 'fenced_yard'));
        $this->assertSame(C::UNKNOWN, $this->answer($this->record(['Fencing' => []]), 'fenced_yard'));
    }

    /** @test */
    public function auction_is_present_ruled_out_by_none_or_another_condition_and_unknown_without_the_field(): void
    {
        $this->assertSame(C::PRESENT, $this->answer($this->record(['SpecialListingConditions' => ['Auction']]), 'auction'));
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['SpecialListingConditions' => ['None']]), 'auction'), 'None is a standard sale');
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['SpecialListingConditions' => ['Short Sale']]), 'auction'));

        foreach ([['SpecialListingConditions' => []], []] as $raw) {
            $this->assertSame(C::UNKNOWN, $this->answer($this->record($raw), 'auction'));
        }
    }

    /** @test */
    public function government_owned_is_never_ruled_out_by_hud_owned_being_absent(): void
    {
        foreach ([['None'], ['Short Sale'], ['Real Estate Owned']] as $values) {
            $record = $this->record(['SpecialListingConditions' => $values]);
            $this->assertSame(C::UNKNOWN, $this->answer($record, 'government_owned'), implode(',', $values));
            $this->assertSame(C::WHY_NO_NEGATIVE_EVIDENCE, $this->why($record, 'government_owned'));
        }

        $this->assertSame(C::PRESENT, $this->answer($this->record(['SpecialListingConditions' => ['HUD Owned']]), 'government_owned'));
    }

    // ── "Other" and masks: they only ever withhold a miss ────────────────

    /** @test */
    public function a_strong_field_holding_only_other_is_not_evidence_but_a_specific_value_beside_it_is(): void
    {
        $other = $this->record(['Cooling' => ['Other']]);
        $this->assertSame(C::UNKNOWN, $this->answer($other, 'central_air'));
        $this->assertSame(C::WHY_UNINFORMATIVE, $this->why($other, 'central_air'));

        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['Cooling' => ['Other', 'Wall/Window Unit(s)']]), 'central_air'));
        $this->assertSame(C::PRESENT, $this->answer($this->record(['Cooling' => ['Other', 'Central Air']]), 'central_air'));

        $remarks = $this->record(['WaterSource' => ['See Remarks']], 'Commercial Sale');
        $this->assertSame(C::WHY_UNINFORMATIVE, $this->why($remarks, 'public_water'));
    }

    /** @test */
    public function a_mask_only_turns_a_miss_into_unknown(): void
    {
        $zoned = $this->record(['Cooling' => ['Zoned']]);
        $this->assertSame(C::UNKNOWN, $this->answer($zoned, 'central_air'));
        $this->assertSame(C::WHY_MASKED, $this->why($zoned, 'central_air'));

        $private = $this->answers($this->record(['WaterSource' => ['Private']], 'Commercial Sale'), ['well_water', 'public_water']);
        $this->assertSame([C::UNKNOWN, C::WHY_MASKED], $private['well_water']);
        $this->assertSame(C::KNOWN_ABSENT, $private['public_water'][0], 'a mask is per tag');

        // Without the generic value, the same field still rules out.
        $this->assertSame(C::KNOWN_ABSENT, $this->answer($this->record(['WaterSource' => ['Public']], 'Commercial Sale'), 'well_water'));
        // And a specific value beside the mask still makes the tag present.
        $this->assertSame(C::PRESENT, $this->answer($this->record(['Cooling' => ['Zoned', 'Central Air']]), 'central_air'));
    }

    /** @test */
    public function no_mask_ever_creates_a_positive(): void
    {
        $zoned = $this->record(['Cooling' => ['Zoned']]);
        $this->assertArrayNotHasKey('central_air', $this->derived($zoned));
        $this->assertNotSame(C::PRESENT, $this->answer($zoned, 'central_air'));

        $private = $this->record(['WaterSource' => ['Private']], 'Commercial Sale');
        $this->assertArrayNotHasKey('well_water', $this->derived($private));
        $this->assertNotSame(C::PRESENT, $this->answer($private, 'well_water'));
    }

    // ── the governed table ───────────────────────────────────────────────

    /**
     * Only strong sources are declared, and a sparse checklist cannot slip back in: every
     * list-reading rule that may prove absence reads one of the fields below.
     *
     * @test
     */
    public function only_strong_sources_may_prove_absence(): void
    {
        $strongListFields = ['SpecialListingConditions', 'PropertyCondition', 'Cooling', 'WaterSource', 'Sewer', 'RoadSurfaceType', 'Fencing'];

        foreach (SmartTagSourceRules::bridgeRules() as $rule) {
            $declared = array_values(array_filter(
                SmartTagSourceRules::tagsEmittableBy($rule),
                static fn (string $tag) => SmartTagSourceRules::negativeEvidence($rule, $tag) !== null,
            ));

            if ($declared === [] || ! in_array($rule['kind'], ['vocab', 'any', 'prefix', 'nonempty'], true)) {
                continue;
            }

            $this->assertContains($rule['field'], $strongListFields, "{$rule['id']} reads a checklist; it must be presence-only");
        }

        // An undeclared rule is fail-closed: a capacity of zero is not "no lift".
        $this->assertNull(SmartTagSourceRules::negativeEvidence($this->ruleById('bridge.dock_lift_cap'), 'boat_lift'));
        $this->assertSame(C::UNKNOWN, $this->answer($this->record(['STELLAR_DockLiftCap' => 0]), 'boat_lift'));
        $this->assertSame(C::PRESENT, $this->answer($this->record(['STELLAR_DockLiftCap' => 10000]), 'boat_lift'));
    }

    /** @test */
    public function the_negative_evidence_config_is_validated(): void
    {
        $this->assertSame([], array_values(array_filter(
            SmartTagSourceRules::validationErrors(),
            static fn (string $e) => str_starts_with($e, 'negative_evidence.'),
        )));
    }

    /** @return array<string, mixed> */
    private function ruleById(string $id): array
    {
        foreach (SmartTagSourceRules::bridgeRules() as $rule) {
            if (($rule['id'] ?? null) === $id) {
                return $rule;
            }
        }
        $this->fail("no Bridge rule {$id}");
    }
}
