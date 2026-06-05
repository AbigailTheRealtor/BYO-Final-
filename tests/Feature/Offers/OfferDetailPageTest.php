<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferCounterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferDetailPageTest extends TestCase
{
    use DatabaseTransactions;

    // ── Test 1: Root offer page loads HTTP 200 ────────────────────────────────

    public function test_root_offer_page_loads_200(): void
    {
        $user  = User::factory()->create();
        $offer = Offer::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);
    }

    // ── Test 2: Counter-offer page shows the parent offer ID ─────────────────

    public function test_counter_offer_page_shows_parent_offer_id(): void
    {
        $user   = User::factory()->create();
        $parent = Offer::factory()->submitted()->create();
        $child  = Offer::factory()->create([
            'parent_offer_id' => $parent->id,
            'status'          => 'submitted',
        ]);

        $response = $this->actingAs($user)->get(route('offers.show', $child));

        $response->assertStatus(200);
        $response->assertSee((string) $parent->id);
    }

    // ── Test 3: Timeline item count in rendered HTML matches chain length ─────

    public function test_timeline_item_count_matches_chain_length(): void
    {
        $user   = User::factory()->create();
        $parent = Offer::factory()->submitted()->create();

        $counterService = $this->app->make(OfferCounterService::class);
        $result         = $counterService->counter(
            parent: $parent,
            actorId: null,
            actorRole: 'seller',
        );
        $child = $result['counter_offer'];

        $response = $this->actingAs($user)->get(route('offers.show', $child));

        $response->assertStatus(200);

        $response->assertViewHas('timeline', function (array $timeline) {
            return count($timeline) === 2;
        });

        $response->assertSee((string) $parent->id);
        $response->assertSee((string) $child->id);
    }

    // ── Test 4: At least one can_* flag appears in rendered HTML ─────────────

    public function test_at_least_one_can_flag_appears_in_rendered_html(): void
    {
        $user  = User::factory()->create();
        $offer = Offer::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);

        $content = $response->getContent();

        $flagLabels = ['Submit', 'Counter', 'Accept', 'Reject', 'Withdraw', 'Expire', 'View Timeline'];
        $found = false;
        foreach ($flagLabels as $label) {
            if (str_contains($content, $label)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'At least one action flag label should appear in the rendered HTML');
    }

    // ── Test 5: Disabled reason string appears when action is blocked ─────────

    public function test_disabled_reason_appears_when_action_is_blocked(): void
    {
        $user  = User::factory()->create();
        $offer = Offer::factory()->create(['status' => 'draft']);

        $actionsService = $this->app->make(OfferAvailableActionsService::class);
        $actions        = $actionsService->forOffer($offer, null, 'system');

        $blockedReason = null;
        foreach ($actions['reasons'] as $reason) {
            if ($reason !== '') {
                $blockedReason = $reason;
                break;
            }
        }

        $this->assertNotNull($blockedReason, 'At least one blocked reason must exist for a draft offer');

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee($blockedReason);
    }

    // ── Test 6: Unknown offer ID returns 404 ─────────────────────────────────

    public function test_unknown_offer_id_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/offers/99999999');

        $response->assertStatus(404);
    }

    // ── Test 7: Static scan — no status mutation or OfferEventLog writes ──────

    public function test_static_scan_no_status_mutation_or_event_log_writes(): void
    {
        $controllerPath = base_path('app/Http/Controllers/OfferController.php');
        $viewPath       = base_path('resources/views/offers/show.blade.php');

        $this->assertFileExists($controllerPath, 'OfferController.php must exist');
        $this->assertFileExists($viewPath, 'offers/show.blade.php must exist');

        $controllerContent = file_get_contents($controllerPath);
        $viewContent       = file_get_contents($viewPath);

        $mutationPatterns = [
            'OfferEventLog',
            '->save()',
            '->update(',
            '->delete(',
            '->create(',
            '->status =',
        ];

        foreach ($mutationPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $controllerContent,
                "OfferController must not contain '{$pattern}'"
            );
            $this->assertStringNotContainsString(
                $pattern,
                $viewContent,
                "offers/show.blade.php must not contain '{$pattern}'"
            );
        }
    }
}
