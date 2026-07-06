<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\SellerAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Matching V2 C6 — the matching:preview inspection command: force-enable default,
 * --respect-flag, --json, unsupported type, and read-only.
 */
class MatchingV2PreviewCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function seedSubjectAndCandidate(): void
    {
        BuyerAgentAuction::create(['id' => 971001, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false])
            ->saveMeta('workflow_type', 'offer_listing');
        $this->score('buyer_agent', 971001, 'demand', 80);

        SellerAgentAuction::create(['id' => 971002, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false])
            ->saveMeta('workflow_type', 'offer_listing');
        $this->score('seller_agent', 971002, 'property', 100);
    }

    private function score(string $type, int $id, string $side, int $value): void
    {
        DnaScore::create([
            'listing_type' => $type, 'listing_id' => $id, 'score_key' => 'pet_friendliness',
            'side' => $side, 'value' => $value, 'data_completeness' => 100, 'confidence' => 90,
            'explanation' => 'seed', 'version' => 'TEST_V1', 'generator_version' => 'TEST_V1', 'generated_by' => 'system',
        ]);
    }

    public function test_force_enables_by_default_and_prints_matches(): void
    {
        config(['matching.v2_enabled' => false]); // env flag OFF
        $this->seedSubjectAndCandidate();

        $exit = Artisan::call('matching:preview', ['listingType' => 'buyer_agent', 'listingId' => 971001]);
        $out  = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('force-enabled in-process', $out);
        $this->assertStringContainsString('971002', $out);       // the matched seller listing id
        $this->assertStringContainsString('seller_agent', $out);
        // The command must not have left the flag enabled.
        $this->assertFalse((bool) config('matching.v2_enabled'));
    }

    public function test_json_output_is_machine_readable(): void
    {
        config(['matching.v2_enabled' => false]);
        $this->seedSubjectAndCandidate();

        Artisan::call('matching:preview', ['listingType' => 'buyer_agent', 'listingId' => 971001, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('buyer_agent', $decoded['subject_type']);
        $this->assertSame('DemandToListings', $decoded['direction']);
        $this->assertSame(971002, $decoded['matches'][0]['listing_id']);
        $this->assertSame('seller_agent', $decoded['matches'][0]['listing_type']);
    }

    public function test_respect_flag_returns_inert_when_disabled(): void
    {
        config(['matching.v2_enabled' => false]);
        $this->seedSubjectAndCandidate();

        Artisan::call('matching:preview', ['listingType' => 'buyer_agent', 'listingId' => 971001, '--respect-flag' => true]);
        $out = Artisan::output();

        $this->assertStringContainsString('honouring flag', $out);
        $this->assertStringContainsString('No determined matches.', $out);
    }

    public function test_unsupported_type_exits_nonzero(): void
    {
        $exit = Artisan::call('matching:preview', ['listingType' => 'agent_service', 'listingId' => 1]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unsupported subject listing_type', Artisan::output());
    }
}
