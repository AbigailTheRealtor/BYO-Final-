<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Services\Dna\Scores\DnaScoreGenerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 55+ leak remediation — end-to-end persistence. Running the real generation
 * pipeline for a 55+ demand offer-listing must persist a Lock-and-Leave demand row
 * with NO age data in inputs_json or explanation, at version V2.
 *
 * @see docs/matching-v2-55plus-leak-remediation-scope.md
 */
class LockAndLeavePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generated_lock_and_leave_demand_row_is_age_clean(): void
    {
        $id = 930001;
        $buyer = BuyerAgentAuction::create([
            'id' => $id, 'user_id' => 1, 'title' => 'BYO Test Listing', 'is_approved' => true, 'is_sold' => false,
        ]);
        $buyer->saveMeta('purchase_purpose', 'Second Home / Vacation');
        $buyer->saveMeta('current_status', 'Snowbird');
        $buyer->saveMeta('leasing_55_plus', 'Yes'); // 55+ seeker — must not leak into the score

        // Enable generation only after the criteria metas exist, then generate.
        config(['dna_scores.generation_enabled' => true]);
        app(DnaScoreGenerationService::class)->generateForListing('buyer_agent', $id);

        $row = DnaScore::where('listing_type', 'buyer_agent')
            ->where('listing_id', $id)
            ->where('score_key', 'lock_and_leave')
            ->where('side', 'demand')
            ->first();

        $this->assertNotNull($row, 'Lock-and-Leave demand row should have been generated.');
        $this->assertSame('LOCK_AND_LEAVE_V2', $row->version);
        $this->assertSame(90, (int) $row->value); // 20+40+30, no age bump
        $this->assertStringNotContainsString('55', (string) $row->explanation);

        $inputs = $row->inputs_json; // cast to array
        $this->assertIsArray($inputs);
        $this->assertArrayNotHasKey('age_targeted', $inputs);
        $this->assertEqualsCanonicalizing(['current_status', 'purchase_purpose'], array_keys($inputs));
    }
}
