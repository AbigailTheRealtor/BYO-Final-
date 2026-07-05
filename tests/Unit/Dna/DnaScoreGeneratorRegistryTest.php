<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Services\Dna\Scores\Contracts\DnaScoreGenerator;
use App\Services\Dna\Scores\DnaScoreGeneratorRegistry;
use App\Services\Dna\Scores\Generators\ScalarScoresGenerator;
use App\Services\Dna\Scores\LocationLifestyleBridgeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 13 — the config-driven generator registry + uniform DnaScoreGenerator
 * contract (addition 2: future-proofing for additional score types).
 */
class DnaScoreGeneratorRegistryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registry_resolves_configured_generators_in_order(): void
    {
        $generators = app(DnaScoreGeneratorRegistry::class)->enabled();

        foreach ($generators as $g) {
            $this->assertInstanceOf(DnaScoreGenerator::class, $g);
        }

        $keys = array_map(fn (DnaScoreGenerator $g) => $g->key(), $generators);
        $this->assertSame(['scalar_scores', 'location_lifestyle_bridge'], $keys);
    }

    public function test_registry_ignores_non_generator_classes(): void
    {
        config(['dna_scores.generators' => [\stdClass::class, ScalarScoresGenerator::class]]);

        $generators = app(DnaScoreGeneratorRegistry::class)->enabled();

        $this->assertCount(1, $generators);
        $this->assertSame('scalar_scores', $generators[0]->key());
    }

    public function test_scalar_generator_persists_configured_scores_via_contract(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('total_acreage', '0.1');
        $auction->saveMeta('condition_prop', 'Turnkey');
        $auction->saveMeta('waterfront', 'yes');
        $auction->saveMeta('water_access', json_encode(['Gulf/Ocean']));

        $rows = app(ScalarScoresGenerator::class)->generate('landlord_agent', $auction->id);

        // All three configured scalar scores persisted.
        $this->assertCount(3, $rows);
        $this->assertSame(3, DnaScore::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)->count());
    }

    public function test_scalar_generator_only_stale_skips_current_versions(): void
    {
        $auction = LandlordAgentAuction::create(['auction_type' => 'landlord']);
        $auction->saveMeta('property_items', json_encode(['Villa']));
        $auction->saveMeta('total_acreage', '0.1');

        // First pass writes rows at the current generator versions.
        app(ScalarScoresGenerator::class)->generate('landlord_agent', $auction->id);

        // only_stale second pass: every score is already current → nothing rewritten.
        $rows = app(ScalarScoresGenerator::class)->generate('landlord_agent', $auction->id, ['only_stale' => true]);
        $this->assertSame([], $rows);
    }

    public function test_location_bridge_only_stale_skips_when_source_version_unchanged(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => 'landlord_agent',
            'listing_id'     => 5150,
            'lifestyle_json' => [
                'version'       => 'LDNA_LIFESTYLE_V1',
                'coastal_score' => 70,
            ],
        ]);

        $bridge = app(LocationLifestyleBridgeGenerator::class);
        $this->assertCount(1, $bridge->generate('landlord_agent', 5150));

        // Unchanged source version → stale pass is a no-op.
        $this->assertSame([], $bridge->generate('landlord_agent', 5150, ['only_stale' => true]));
    }
}
