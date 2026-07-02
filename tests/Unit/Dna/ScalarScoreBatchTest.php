<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\Dna\Scores\LocationLifestyleBridgeGenerator;
use App\Services\Dna\Scores\LockAndLeaveScoreService;
use App\Services\Dna\Scores\SymmetricScoreDnaGenerator;
use App\Services\Dna\Scores\WaterfrontLifestyleScoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3A — sequenced scalar-score batch. Proves the DNA framework scales to
 * additional score types (Lock-and-Leave, Waterfront-Lifestyle) and bridges the
 * existing 5 Location DNA scores into dna_scores with F4 + F5 — all additive.
 */
class ScalarScoreBatchTest extends TestCase
{
    use DatabaseTransactions;

    private function property(array $fields): CanonicalListing
    {
        return new CanonicalListing('landlord_agent', 1, $fields);
    }

    private function demand(array $fields): CanonicalListing
    {
        return new CanonicalListing('tenant_agent', 1, $fields);
    }

    // ── Lock-and-Leave ──────────────────────────────────────────────────────

    public function test_lock_and_leave_condo_scores_high(): void
    {
        $r = (new LockAndLeaveScoreService())->scoreProperty($this->property([
            'property.structure_type'      => ['Condominium'],
            'property.hoa_fee_includes'    => ['Lawn/Ground Maintenance', 'Exterior Maintenance'],
            'property.community_amenities' => ['Gated', 'Guard'],
            'property.lot_acreage'         => 0.1,
            'property.condition'           => 'Turnkey',
        ]));

        $this->assertSame('lock_and_leave', $r['score_key']);
        $this->assertSame('property', $r['side']);
        $this->assertSame(100, $r['value']); // 25+25+20+15+10+10 capped
        $this->assertSame(100, $r['data_completeness']);
        $this->assertSame(90, $r['confidence']);
        $this->assertStringContainsString('low-maintenance structure', $r['explanation']);
        $this->assertSame('LOCK_AND_LEAVE_V1', $r['version']);
    }

    public function test_lock_and_leave_large_single_family_scores_low(): void
    {
        $r = (new LockAndLeaveScoreService())->scoreProperty($this->property([
            'property.structure_type' => 'Single Family',
            'property.lot_acreage'    => 3.0,
        ]));

        $this->assertLessThan(45, $r['value']);
        $this->assertLessThanOrEqual($r['data_completeness'], $r['confidence']); // F4 invariant
    }

    public function test_lock_and_leave_missing_all_inputs_is_null_zero_confidence(): void
    {
        $r = (new LockAndLeaveScoreService())->scoreProperty($this->property([]));
        $this->assertNull($r['value']);
        $this->assertSame(0, $r['confidence']);
        $this->assertStringContainsString('Insufficient', $r['explanation']);
    }

    public function test_lock_and_leave_demand_from_seasonal_intent(): void
    {
        $r = (new LockAndLeaveScoreService())->scoreDemand($this->demand([
            'demand.purchase_purpose' => 'Second Home / Vacation',
            'demand.current_status'   => 'Snowbird',
            'demand.age_targeted'     => true,
        ]));

        $this->assertSame('demand', $r['side']);
        $this->assertSame(100, $r['value']); // 20+40+30+15 capped
        $this->assertSame(100, $r['data_completeness']);
    }

    // ── Waterfront-Lifestyle ────────────────────────────────────────────────

    public function test_waterfront_property_scores_high(): void
    {
        $r = (new WaterfrontLifestyleScoreService())->scoreProperty($this->property([
            'property.waterfront'          => true,
            'property.water_access'        => ['Canal - Saltwater'],
            'property.water_view'          => ['Full'],
            'property.water_frontage_feet' => 90.0,
        ]));

        $this->assertSame('waterfront_lifestyle', $r['score_key']);
        $this->assertSame(100, $r['value']); // 50+15+10+10+15
        $this->assertStringContainsString('direct waterfront', $r['explanation']);
    }

    public function test_non_waterfront_property_scores_minimal(): void
    {
        $r = (new WaterfrontLifestyleScoreService())->scoreProperty($this->property([
            'property.waterfront' => false,
            'property.water_view' => ['None'],
        ]));

        $this->assertSame(5, $r['value']);
        $this->assertStringContainsString('not a waterfront', $r['explanation']);
    }

    public function test_waterfront_demand_from_view_preference(): void
    {
        $withWater = (new WaterfrontLifestyleScoreService())->scoreDemand($this->demand([
            'demand.view_preference' => ['Water', 'Golf Course'],
        ]));
        $this->assertSame(80, $withWater['value']);

        $withoutWater = (new WaterfrontLifestyleScoreService())->scoreDemand($this->demand([
            'demand.view_preference' => ['Golf Course'],
        ]));
        $this->assertSame(20, $withoutWater['value']);

        $absent = (new WaterfrontLifestyleScoreService())->scoreDemand($this->demand([]));
        $this->assertNull($absent['value']);
        $this->assertSame(0, $absent['confidence']);
    }

    // ── End-to-end via the generic generator + dna_scores ───────────────────

    public function test_generic_generator_persists_multiple_score_types(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('association_fee_includes', json_encode(['Exterior Maintenance']));
        $auction->saveMeta('total_acreage', '0.1');
        $auction->saveMeta('condition_prop', 'Turnkey');
        $auction->saveMeta('waterfront', 'yes');
        $auction->saveMeta('water_access', json_encode(['Gulf/Ocean']));
        $auction->saveMeta('waterfront_feet', '100');

        $gen = app(SymmetricScoreDnaGenerator::class);
        $ll = $gen->generateForListing(app(LockAndLeaveScoreService::class), 'landlord_agent', $auction->id);
        $wf = $gen->generateForListing(app(WaterfrontLifestyleScoreService::class), 'landlord_agent', $auction->id);

        $this->assertSame('lock_and_leave', $ll->score_key);
        $this->assertSame('property', $ll->side);
        $this->assertGreaterThan(60, $ll->value);

        $this->assertSame('waterfront_lifestyle', $wf->score_key);
        $this->assertGreaterThan(60, $wf->value);

        // Two distinct score rows persisted for one listing.
        $this->assertSame(2, DnaScore::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)->count());

        // Idempotent.
        $gen->generateForListing(app(LockAndLeaveScoreService::class), 'landlord_agent', $auction->id);
        $this->assertSame(2, DnaScore::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)->count());
    }

    // ── Location DNA bridge (reuse existing scores) ─────────────────────────

    public function test_location_bridge_imports_five_scores_with_confidence_and_explanation(): void
    {
        PropertyLocationDna::create([
            'listing_type' => 'landlord_agent',
            'listing_id'   => 4242,
            'lifestyle_json' => [
                'version'           => 'LDNA_LIFESTYLE_V1',
                'coastal_score'     => 85,
                'walkability_score' => 60,
                'convenience_score' => 70,
                'commuter_score'    => 40,
                'family_score'      => 0, // absent thematic input
                'lifestyle_categories' => ['Beach Lovers'],
                'location_narrative'   => 'Near the coast.',
            ],
        ]);

        $rows = app(LocationLifestyleBridgeGenerator::class)->generateForListing('landlord_agent', 4242);

        $this->assertCount(5, $rows);

        $coastal = DnaScore::where('listing_id', 4242)->where('score_key', 'location_coastal')->first();
        $this->assertSame(85, $coastal->value);
        $this->assertSame('property', $coastal->side);
        $this->assertSame(100, $coastal->data_completeness);
        $this->assertSame(85, $coastal->confidence);
        $this->assertStringContainsString('Location DNA enrichment', $coastal->explanation);
        $this->assertSame('LOCATION_BRIDGE_V1', $coastal->version);

        // A 0 score (absent thematic input) is bridged at low completeness/confidence.
        $family = DnaScore::where('listing_id', 4242)->where('score_key', 'location_family')->first();
        $this->assertSame(0, $family->value);
        $this->assertSame(30, $family->data_completeness);
        $this->assertLessThanOrEqual($family->data_completeness, $family->confidence);

        // Non-destructive: the source Location DNA row is unchanged.
        $src = PropertyLocationDna::where('listing_id', 4242)->first();
        $this->assertSame(85, $src->lifestyle_json['coastal_score']);
    }

    public function test_location_bridge_noop_when_no_location_dna(): void
    {
        $rows = app(LocationLifestyleBridgeGenerator::class)->generateForListing('landlord_agent', 999999);
        $this->assertSame([], $rows);
    }

    // ── Adapter extension didn't break Phase 2 canonical resolution ─────────

    public function test_resolver_now_exposes_shared_property_fields(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('total_acreage', '0.25');
        $auction->saveMeta('condition_prop', 'Updated');
        $auction->saveMeta('waterfront', 'no');

        $c = app(CanonicalListingResolver::class)->resolve('landlord_agent', $auction->id);
        $this->assertSame(0.25, $c->get('property.lot_acreage'));
        $this->assertSame('Updated', $c->get('property.condition'));
        $this->assertFalse($c->get('property.waterfront'));
        // pet fields (Phase 2) still absent when no pet meta set.
        $this->assertFalse($c->present('pet.policy.pets_allowed'));
    }
}
