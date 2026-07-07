<?php

namespace Tests\Feature\MatchCheck;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * git-C14 — route gating for the first consumer Match Check surface.
 *
 * Proves the feature is INVISIBLE and INERT while config('mls_match_check.enabled') is
 * OFF (its default): both routes 404 for everyone, and no Bridge/enrichment I/O occurs.
 * With the flag ON, auth is required and the form renders.
 */
class MatchCheckRouteGateTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** @test */
    public function get_route_404s_when_flag_off_even_for_authenticated_user(): void
    {
        Config::set('mls_match_check.enabled', false);
        Http::fake();
        Queue::fake();

        $this->actingAs($this->user())
            ->get('/match-check')
            ->assertNotFound();

        // Inertness: the 404 fires before any lookup or enrichment dispatch.
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @test */
    public function post_route_404s_when_flag_off(): void
    {
        Config::set('mls_match_check.enabled', false);
        Http::fake();
        Queue::fake();

        $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'mls', 'mls_number' => 'A4567890'])
            ->assertNotFound();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @test */
    public function guest_is_redirected_to_login_when_flag_on(): void
    {
        Config::set('mls_match_check.enabled', true);

        // auth runs before match-check, so a guest never reaches (or reveals) the feature.
        $this->get('/match-check')->assertStatus(302);
    }

    /** @test */
    public function authenticated_user_sees_the_lookup_form_when_flag_on(): void
    {
        Config::set('mls_match_check.enabled', true);

        $this->actingAs($this->user())
            ->get('/match-check')
            ->assertOk()
            ->assertSee('Match Check')
            ->assertSee(route('match-check.lookup'));
    }

    /** @test */
    public function post_route_carries_the_configured_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('match-check.lookup');

        $this->assertNotNull($route, 'match-check.lookup route should be registered');
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
        $this->assertContains('match-check', $route->gatherMiddleware());
    }
}
