<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Tests\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * The structured water-access and road-surface vocabularies, against the EXACT option
 * strings the real wizards emit.
 *
 * Two gaps are closed here:
 *
 *   • six water_access options (Bay/Harbor, Bayou, Beach, Creek, Pond, River) reached only
 *     the generic `water_access` tag, because no specific canonical key existed;
 *   • Brick and Chip And Seal reached NOTHING at all — road_surface has no `nonempty`
 *     fallback rule, so an unmapped option emits no evidence of any kind.
 *
 * The specific water tags describe THE LISTING'S OWN STATED WATER ACCESS. Proximity —
 * "near the beach", "a short walk to the river" — is Location DNA's territory and must
 * never produce one, which is why they carry no description-derived rule.
 */
class WaterAccessAndRoadSurfaceVocabularyTest extends TestCase
{
    use BuildsSmartTagRecords;

    private NativeListingTagDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        // Both static caches must go: SmartTagSourceRules memoises each normalised
        // vocabulary, so a stale copy would answer for the whole process.
        SmartTagTaxonomy::flush();
        SmartTagConfig::flush();
        SmartTagSourceRules::flush();
        $this->deriver = new NativeListingTagDeriver();
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string> tag => state
     */
    private function sellerStates(array $meta, string $propertyType = 'Residential'): array
    {
        $reader  = $this->nativeMeta(['property_type' => $propertyType] + $meta);
        $context = $this->deriver->contextFor(SmartTagListingType::SellerAgent, $reader);
        $this->assertNotNull($context, 'fixture property_type must resolve to a context');

        return $this->states($this->deriver->derive(SmartTagListingType::SellerAgent, $reader, $context));
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function landlordStates(array $meta, string $propertyType = 'Commercial Property'): array
    {
        $reader  = $this->nativeMeta(['property_type' => $propertyType] + $meta);
        $context = $this->deriver->contextFor(SmartTagListingType::LandlordAgent, $reader);
        $this->assertNotNull($context);

        return $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent, $reader, $context));
    }

    // ── WATER ACCESS ────────────────────────────────────────────────────────

    /**
     * Every real wizard option, and the specific tag it must now reach. The generic
     * `water_access` must keep firing alongside it in every single case.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function waterAccessOptions(): array
    {
        return [
            // pre-existing mappings — these must not regress
            'Gulf/Ocean'            => ['Gulf/Ocean', 'gulf_or_ocean_access'],
            'Gulf/Ocean to Bay'     => ['Gulf/Ocean to Bay', 'gulf_or_ocean_access'],
            'Intracoastal Waterway' => ['Intracoastal Waterway', 'intracoastal_access'],
            'Canal - Freshwater'    => ['Canal - Freshwater', 'canal_frontage'],
            'Canal - Saltwater'     => ['Canal - Saltwater', 'canal_frontage'],
            'Lake'                  => ['Lake', 'lake_access'],
            // newly wired
            'Bay/Harbor'            => ['Bay/Harbor', 'bay_or_harbor_access'],
            'Bayou'                 => ['Bayou', 'bayou_access'],
            'Beach'                 => ['Beach', 'beach_access'],
            'Creek'                 => ['Creek', 'creek_access'],
            'Pond'                  => ['Pond', 'pond_access'],
            'River'                 => ['River', 'river_access'],
        ];
    }

    /**
     * @test
     * @dataProvider waterAccessOptions
     */
    public function each_water_access_option_reaches_its_specific_tag_and_the_generic_one(string $option, string $expected): void
    {
        $states = $this->sellerStates(['water_access' => [$option]]);

        $this->assertSame('present', $states[$expected] ?? 'MISSING', "{$option} should map to {$expected}");
        $this->assertSame('present', $states['water_access'] ?? 'MISSING', "{$option} must still fire the generic water_access");
    }

    /**
     * "Other" carries no information and is dropped by NativeMetaValueReader::list()
     * before any rule sees it — so it produces NO water tag at all, not even the generic
     * one. That is pre-existing behaviour, unchanged here, and it is why 'Other' is
     * deliberately absent from the vocabulary rather than mapped to something.
     *
     * @test
     */
    public function the_other_option_invents_no_body_of_water_and_fires_nothing(): void
    {
        $states = $this->sellerStates(['water_access' => ['Other']]);

        $this->assertArrayNotHasKey('water_access', $states, "'Other' alone is not evidence of water access");

        foreach ($this->specificWaterTags() as $tag) {
            $this->assertArrayNotHasKey($tag, $states, "'Other' must not invent {$tag}");
        }
    }

    /** @test */
    public function other_alongside_a_real_option_does_not_suppress_that_option(): void
    {
        $states = $this->sellerStates(['water_access' => ['Other', 'Bayou']]);

        $this->assertSame('present', $states['bayou_access'] ?? 'MISSING');
        $this->assertSame('present', $states['water_access'] ?? 'MISSING');
    }

    /** @test */
    public function multiple_selected_options_each_reach_their_own_tag(): void
    {
        $states = $this->sellerStates(['water_access' => ['Bayou', 'River', 'Gulf/Ocean']]);

        $this->assertSame('present', $states['bayou_access'] ?? 'MISSING');
        $this->assertSame('present', $states['river_access'] ?? 'MISSING');
        $this->assertSame('present', $states['gulf_or_ocean_access'] ?? 'MISSING');
        $this->assertSame('present', $states['water_access'] ?? 'MISSING');

        $this->assertArrayNotHasKey('beach_access', $states);
        $this->assertArrayNotHasKey('lake_access', $states);
    }

    /** @test */
    public function an_unrelated_string_does_not_fuzzy_match_any_water_tag(): void
    {
        // Substring- or similarity-matching would catch several of these.
        $states = $this->sellerStates(['water_access' => [
            'Riverside Drive', 'Beachwood', 'Pondering', 'Creekside', 'Bay Window', 'Bayonet Point',
        ]]);

        foreach ($this->specificWaterTags() as $tag) {
            $this->assertArrayNotHasKey($tag, $states, "an unrelated string must not produce {$tag}");
        }
    }

    // ── WATER: PROXIMITY IS NOT ACCESS ──────────────────────────────────────

    /** @test */
    public function proximity_prose_never_produces_a_specific_water_access_tag(): void
    {
        $prose = implode(' ', [
            'Just a short walk to the beach and only two blocks from the river.',
            'Close to the bay, near a pond, and minutes from the creek.',
            'Walking distance to the water and steps from the bayou.',
        ]);

        $states = $this->sellerStates(['additional_details' => $prose]);

        foreach ($this->specificWaterTags() as $tag) {
            $this->assertArrayNotHasKey($tag, $states, "proximity prose must never produce {$tag}");
        }
    }

    /** @test */
    public function beach_and_river_proximity_specifically_produce_no_beach_or_river_access(): void
    {
        foreach ([
            'Enjoy living near the beach in this lovely home.',
            'A short walk to the river from your front door.',
            'This neighborhood is close to the beach and the river.',
        ] as $prose) {
            $states = $this->sellerStates(['additional_details' => $prose]);

            $this->assertArrayNotHasKey('beach_access', $states, "prose must not produce beach_access: {$prose}");
            $this->assertArrayNotHasKey('river_access', $states, "prose must not produce river_access: {$prose}");
        }
    }

    /** @test */
    public function location_dna_style_distance_statements_produce_no_water_tags(): void
    {
        $states = $this->sellerStates([
            'additional_details' => '0.4 miles to Sunset Beach. 1.2 miles to the Intracoastal Waterway. 3 minutes to Tampa Bay.',
        ]);

        foreach ($this->specificWaterTags() as $tag) {
            $this->assertArrayNotHasKey($tag, $states, "a distance statement must not produce {$tag}");
        }
    }

    /**
     * The six new keys plus the four that already existed — none of them may ever be
     * reachable from prose.
     *
     * @return string[]
     */
    private function specificWaterTags(): array
    {
        return [
            'bay_or_harbor_access', 'bayou_access', 'beach_access',
            'creek_access', 'pond_access', 'river_access',
            'gulf_or_ocean_access', 'intracoastal_access', 'canal_frontage', 'lake_access',
        ];
    }

    /**
     * REPORTED, NOT CHANGED — pending an explicit decision.
     *
     * "Gulf/Ocean - Full" is a WATER VIEW value, not a water ACCESS value. The seller wizard
     * offers it under `water_view`, and the live residential_lease Bridge fixture carries
     * STELLAR_WaterAccess ['Beach','Gulf/Ocean'] beside STELLAR_WaterView ['Beach','Gulf/Ocean - Full']
     * on one record. Mapping it into the water_access vocabulary would assert gulf/ocean ACCESS
     * from a VIEW — a claim the listing never made — so it is deliberately not mapped.
     *
     * This test pins today's behaviour rather than endorsing it; it is the evidence for the
     * decision, and flipping it is a one-line vocabulary change if the call goes the other way.
     */
    /** @test */
    public function gulf_ocean_full_is_a_water_view_value_and_earns_no_access_tag(): void
    {
        $states = $this->sellerStates(['water_access' => ['Gulf/Ocean - Full']]);

        $this->assertSame('present', $states['water_access'] ?? 'MISSING', 'the generic tag still fires');
        $this->assertArrayNotHasKey('gulf_or_ocean_access', $states);
    }

    // ── ROAD SURFACE ────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function roadSurfaceOptions(): array
    {
        return [
            'Asphalt'       => ['Asphalt', 'paved_road_access'],
            'Concrete'      => ['Concrete', 'paved_road_access'],
            'Paved'         => ['Paved', 'paved_road_access'],
            'Brick'         => ['Brick', 'paved_road_access'],
            'Chip And Seal' => ['Chip And Seal', 'paved_road_access'],
            'Dirt'          => ['Dirt', 'unpaved_road_access'],
            'Gravel'        => ['Gravel', 'unpaved_road_access'],
            'Limerock'      => ['Limerock', 'unpaved_road_access'],
            'Unimproved'    => ['Unimproved', 'unpaved_road_access'],
            'Other'         => ['Other', null],
        ];
    }

    /**
     * land.sale is used because BOTH road tags apply there — see the context test below for
     * the contexts where they deliberately do not.
     *
     * @test
     * @dataProvider roadSurfaceOptions
     */
    public function each_road_surface_option_maps_as_declared(string $option, ?string $expected): void
    {
        $states = $this->sellerStates(['road_surface_type' => [$option]], 'Vacant Land');

        if ($expected === null) {
            $this->assertArrayNotHasKey('paved_road_access', $states, "'{$option}' must not be classified");
            $this->assertArrayNotHasKey('unpaved_road_access', $states, "'{$option}' must not be classified");

            return;
        }

        $this->assertSame('present', $states[$expected] ?? 'MISSING', "{$option} should map to {$expected}");

        $other = $expected === 'paved_road_access' ? 'unpaved_road_access' : 'paved_road_access';
        $this->assertArrayNotHasKey($other, $states, "{$option} must not also produce {$other}");
    }

    /** @test */
    public function brick_and_chip_and_seal_previously_produced_no_tag_at_all(): void
    {
        // The regression this closes: road_surface has no `nonempty` fallback, so an unmapped
        // option emitted nothing — not even a generic road tag.
        foreach (['Brick', 'Chip And Seal'] as $option) {
            $states = $this->sellerStates(['road_surface_type' => [$option]], 'Vacant Land');

            $this->assertSame('present', $states['paved_road_access'] ?? 'MISSING', "{$option} should now be paved");
        }
    }

    // ── ROAD: CONTEXTS WERE NOT WIDENED ─────────────────────────────────────

    /**
     * This patch changed the VOCABULARY only. `paved_road_access` remains scoped to
     * commercial.sale / commercial.lease / business.sale / land.sale, and
     * `unpaved_road_access` to commercial.sale / business.sale / land.sale — note it
     * deliberately still excludes commercial.lease. Both exclude every residential and
     * income context.
     *
     * @test
     */
    public function the_road_tags_reach_no_new_context(): void
    {
        $paved   = SmartTagTaxonomy::get('paved_road_access');
        $unpaved = SmartTagTaxonomy::get('unpaved_road_access');

        $this->assertNotNull($paved);
        $this->assertNotNull($unpaved);

        $this->assertSame(
            ['commercial.sale', 'commercial.lease', 'business.sale', 'land.sale'],
            $paved->contextValues(),
            'paved_road_access contexts must be unchanged by this patch'
        );

        $this->assertSame(
            ['commercial.sale', 'business.sale', 'land.sale'],
            $unpaved->contextValues(),
            'unpaved_road_access contexts must be unchanged — commercial.lease stays excluded'
        );

        $this->assertFalse($paved->appliesTo(SmartTagContext::ResidentialSale));
        $this->assertFalse($paved->appliesTo(SmartTagContext::ResidentialLease));
        $this->assertFalse($paved->appliesTo(SmartTagContext::IncomeSale));
        $this->assertFalse($unpaved->appliesTo(SmartTagContext::CommercialLease));
    }

    /**
     * The applicability rule is enforced on the way out, so a residential listing that
     * answers "Brick" still receives no road tag. The vocabulary fix does not change that,
     * and a reader of this patch should not expect it to.
     *
     * @test
     */
    public function a_residential_listing_answering_brick_still_receives_no_road_tag(): void
    {
        $states = $this->sellerStates(['road_surface_type' => ['Brick']], 'Residential');

        $this->assertArrayNotHasKey('paved_road_access', $states);
        $this->assertArrayNotHasKey('unpaved_road_access', $states);
    }

    /** @test */
    public function a_commercial_lease_gets_paved_but_never_unpaved(): void
    {
        $paved = $this->landlordStates(['road_surface_type' => ['Brick']]);
        $this->assertSame('present', $paved['paved_road_access'] ?? 'MISSING');

        // Dirt maps to unpaved, which does not apply to commercial.lease — so nothing is written.
        $dirt = $this->landlordStates(['road_surface_type' => ['Dirt']]);
        $this->assertArrayNotHasKey('unpaved_road_access', $dirt);
    }
}
