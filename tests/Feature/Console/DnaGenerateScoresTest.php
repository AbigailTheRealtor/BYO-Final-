<?php

namespace Tests\Feature\Console;

use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 13 — dna:generate-scores bulk command. Honors the master gate, produces
 * scores inline with --sync, and is version-aware under --only-stale.
 */
class DnaGenerateScoresTest extends TestCase
{
    use DatabaseTransactions;

    private function seedLandlord(): LandlordAgentAuction
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
            'lifestyle_json' => ['version' => 'LDNA_LIFESTYLE_V1', 'coastal_score' => 80],
        ]);

        return $auction;
    }

    public function test_noop_when_generation_disabled(): void
    {
        config(['dna_scores.generation_enabled' => false]);
        $auction = $this->seedLandlord();

        $this->artisan('dna:generate-scores --type=landlord_agent --sync')->assertSuccessful();

        $this->assertSame(0, DnaScore::where('listing_id', $auction->id)->count());
    }

    public function test_sync_generates_scores_for_the_type(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seedLandlord();

        $this->artisan('dna:generate-scores --type=landlord_agent --sync')->assertSuccessful();

        // 3 scalar + 1 bridged coastal.
        $this->assertSame(4, DnaScore::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)->count());
    }

    public function test_only_stale_second_run_writes_nothing_new(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        $auction = $this->seedLandlord();

        $this->artisan('dna:generate-scores --type=landlord_agent --sync')->assertSuccessful();
        $before = DnaScore::where('listing_id', $auction->id)->count();

        $this->artisan('dna:generate-scores --type=landlord_agent --sync --only-stale')->assertSuccessful();

        $this->assertSame($before, DnaScore::where('listing_id', $auction->id)->count());
    }

    public function test_rejects_unknown_type(): void
    {
        config(['dna_scores.generation_enabled' => true]);

        $this->artisan('dna:generate-scores --type=bogus --sync')->assertFailed();
    }
}
