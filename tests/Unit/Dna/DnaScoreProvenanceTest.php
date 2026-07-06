<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Services\Dna\Scores\LocationLifestyleBridgeGenerator;
use App\Services\Dna\Scores\LockAndLeaveScoreService;
use App\Services\Dna\Scores\SymmetricScoreDnaGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 13 (production DNA generation) — generation provenance.
 *
 * Every persisted dna_scores row records where it came from (generated_by),
 * which generator/algorithm produced it (generator_version), and — for bridged
 * scores — the upstream data version (source_version). computed_at is the
 * generated_at timestamp. All additive; the legacy `version` column is retained.
 */
class DnaScoreProvenanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_scalar_generator_stamps_system_provenance_by_default(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('total_acreage', '0.1');
        $auction->saveMeta('condition_prop', 'Turnkey');

        $row = app(SymmetricScoreDnaGenerator::class)
            ->generateForListing(app(LockAndLeaveScoreService::class), 'landlord_agent', $auction->id);

        $this->assertNotNull($row);
        $this->assertSame('system', $row->generated_by);
        $this->assertSame('LOCK_AND_LEAVE_V2', $row->generator_version);
        $this->assertSame('LOCK_AND_LEAVE_V2', $row->version); // legacy column kept in sync
        $this->assertNull($row->source_version);               // no upstream data source
        $this->assertNotNull($row->computed_at);               // generated_at
    }

    public function test_scalar_generator_records_explicit_origin(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('total_acreage', '0.1');

        $row = app(SymmetricScoreDnaGenerator::class)->generateForListing(
            app(LockAndLeaveScoreService::class),
            'landlord_agent',
            $auction->id,
            ['generated_by' => 'imported']
        );

        $this->assertSame('imported', $row->generated_by);
    }

    public function test_location_bridge_stamps_source_version_from_upstream(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => 'landlord_agent',
            'listing_id'     => 7373,
            'lifestyle_json' => [
                'version'           => 'LDNA_LIFESTYLE_V1',
                'coastal_score'     => 85,
                'walkability_score' => 60,
            ],
        ]);

        app(LocationLifestyleBridgeGenerator::class)
            ->generateForListing('landlord_agent', 7373, ['generated_by' => 'system']);

        $row = DnaScore::where('listing_id', 7373)->where('score_key', 'location_coastal')->first();

        $this->assertSame('system', $row->generated_by);
        $this->assertSame('LOCATION_BRIDGE_V1', $row->generator_version);
        $this->assertSame('LDNA_LIFESTYLE_V1', $row->source_version); // upstream data version
    }
}
