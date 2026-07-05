<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Services\Dna\Scores\DnaScoreGenerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 13 — the trigger-agnostic generation service (addition 3). Verifies the
 * default-off gate, the supported-type guard, end-to-end multi-generator
 * production, provenance tagging, idempotency, and version-aware only_stale.
 */
class DnaScoreGenerationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function seededAuction(): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('total_acreage', '0.1');
        $auction->saveMeta('condition_prop', 'Turnkey');
        $auction->saveMeta('waterfront', 'yes');
        $auction->saveMeta('water_access', json_encode(['Gulf/Ocean']));

        PropertyLocationDna::create([
            'listing_type'   => 'landlord_agent',
            'listing_id'     => $auction->id,
            'lifestyle_json' => [
                'version'       => 'LDNA_LIFESTYLE_V1',
                'coastal_score' => 80,
            ],
        ]);

        return $auction;
    }

    public function test_does_nothing_when_generation_disabled(): void
    {
        config(['dna_scores.generation_enabled' => false]);
        $auction = $this->seededAuction();

        $rows = app(DnaScoreGenerationService::class)->generateForListing('landlord_agent', $auction->id);

        $this->assertSame([], $rows);
        $this->assertSame(0, DnaScore::where('listing_id', $auction->id)->count());
    }

    public function test_skips_unsupported_listing_type(): void
    {
        config(['dna_scores.generation_enabled' => true]);

        // 'seller' is the consumer-family string the resolver does not support.
        $rows = app(DnaScoreGenerationService::class)->generateForListing('seller', 12345);

        $this->assertSame([], $rows);
    }

    public function test_generates_scalar_and_bridged_scores_with_system_provenance(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seededAuction();

        $rows = app(DnaScoreGenerationService::class)->generateForListing('landlord_agent', $auction->id);

        // 3 scalar + 1 bridged coastal.
        $this->assertGreaterThanOrEqual(4, count($rows));

        $count = DnaScore::where('listing_type', 'landlord_agent')->where('listing_id', $auction->id)->count();
        $this->assertSame(4, $count);

        foreach (DnaScore::where('listing_id', $auction->id)->get() as $row) {
            $this->assertSame('system', $row->generated_by);
            $this->assertNotNull($row->generator_version);
        }
    }

    public function test_records_explicit_origin_tag(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seededAuction();

        app(DnaScoreGenerationService::class)
            ->generateForListing('landlord_agent', $auction->id, ['generated_by' => 'imported']);

        $this->assertSame(4, DnaScore::where('listing_id', $auction->id)
            ->where('generated_by', 'imported')->count());
    }

    public function test_is_idempotent(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seededAuction();

        $svc = app(DnaScoreGenerationService::class);
        $svc->generateForListing('landlord_agent', $auction->id);
        $svc->generateForListing('landlord_agent', $auction->id);

        $this->assertSame(4, DnaScore::where('listing_id', $auction->id)->count());
    }

    public function test_only_stale_is_a_noop_when_everything_current(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seededAuction();

        $svc = app(DnaScoreGenerationService::class);
        $svc->generateForListing('landlord_agent', $auction->id);

        $rows = $svc->generateForListing('landlord_agent', $auction->id, ['only_stale' => true]);
        $this->assertSame([], $rows);
    }
}
