<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferHistoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferHistoryService $service;
    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferHistoryService();
        $this->offer   = Offer::factory()->create(['status' => 'draft']);
    }

    // ── Case 1: forOffer returns logs in oldest→newest order ──────────────

    public function test_for_offer_returns_logs_in_oldest_to_newest_order(): void
    {
        $first  = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'event_type' => 'submitted',
            'created_at' => now()->subMinutes(10),
        ]);
        $second = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'event_type' => 'reviewed',
            'created_at' => now()->subMinutes(5),
        ]);
        $third  = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'event_type' => 'accepted',
            'created_at' => now(),
        ]);

        $logs = $this->service->forOffer($this->offer);

        $ids = $logs->pluck('id')->values()->all();
        $this->assertSame([$first->id, $second->id, $third->id], $ids);
    }

    // ── Case 2: forOfferId returns same results as forOffer for matching ID ─

    public function test_for_offer_id_returns_same_results_as_for_offer(): void
    {
        OfferEventLog::factory()->count(3)->create(['offer_id' => $this->offer->id]);

        $byModel = $this->service->forOffer($this->offer)->pluck('id')->sort()->values()->all();
        $byId    = $this->service->forOfferId($this->offer->id)->pluck('id')->sort()->values()->all();

        $this->assertSame($byModel, $byId);
    }

    // ── Case 3: forOfferId returns empty collection for unknown ID ─────────

    public function test_for_offer_id_returns_empty_collection_for_unknown_id(): void
    {
        $result = $this->service->forOfferId(PHP_INT_MAX);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ── Case 4: forOffer returns empty collection when no logs exist ───────

    public function test_for_offer_returns_empty_collection_when_no_logs_exist(): void
    {
        $result = $this->service->forOffer($this->offer);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ── Case 5: latestForOffer returns logs in newest→oldest order ─────────

    public function test_latest_for_offer_returns_logs_in_newest_to_oldest_order(): void
    {
        $first  = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'created_at' => now()->subMinutes(10),
        ]);
        $second = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'created_at' => now()->subMinutes(5),
        ]);
        $third  = OfferEventLog::factory()->create([
            'offer_id'   => $this->offer->id,
            'created_at' => now(),
        ]);

        $logs = $this->service->latestForOffer($this->offer, 10);

        $ids = $logs->pluck('id')->values()->all();
        $this->assertSame([$third->id, $second->id, $first->id], $ids);
    }

    // ── Case 6: latestForOffer respects $limit, never returns more ─────────

    public function test_latest_for_offer_respects_limit_parameter(): void
    {
        OfferEventLog::factory()->count(5)->create(['offer_id' => $this->offer->id]);

        $result = $this->service->latestForOffer($this->offer, 3);

        $this->assertCount(3, $result);
    }

    // ── Case 7: forOffer does not return logs belonging to another offer ───

    public function test_for_offer_does_not_return_logs_for_different_offer(): void
    {
        $otherOffer = Offer::factory()->create(['status' => 'draft']);

        OfferEventLog::factory()->create(['offer_id' => $this->offer->id]);
        OfferEventLog::factory()->create(['offer_id' => $otherOffer->id]);

        $logs = $this->service->forOffer($this->offer);

        $this->assertCount(1, $logs);
        $this->assertSame($this->offer->id, $logs->first()->offer_id);
    }

    // ── Case 8: static scan — service file contains no write operations ───

    public function test_service_file_contains_no_write_operations(): void
    {
        $source = file_get_contents(app_path('Services/Offers/OfferHistoryService.php'));

        $this->assertStringNotContainsString('::create',  $source);
        $this->assertStringNotContainsString('->save()',  $source);
        $this->assertStringNotContainsString('->update(', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('->insert(', $source);
    }
}
