<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\ListingPreferenceReason;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 1 is INERT.
 *
 * The tables exist, the vocabulary is governed, the boundaries are written — and
 * nothing in the application reads or writes any of it. Merging this phase
 * changes no customer-visible behaviour, which is what makes the customer-facing
 * phase a separate, reviewable decision rather than a side effect of a merge.
 */
class ListingPreferenceInertnessTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function no_listing_preference_rows_exist(): void
    {
        $this->assertSame(0, ListingPreference::count());
        $this->assertSame(0, ListingPreferenceReason::count());
        $this->assertSame(0, ListingPreferenceEvent::count());
    }

    /** @test */
    public function no_route_mentions_listing_preferences(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsStringIgnoringCase(
                'listing-preference',
                (string) $route->uri(),
                'Phase 1 registers no routes'
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'listing_preference',
                (string) $route->getName(),
                'Phase 1 registers no routes'
            );
            $this->assertStringNotContainsString(
                'ListingPreference',
                (string) $route->getActionName(),
                'Phase 1 registers no controllers'
            );
        }
    }

    /**
     * The feature flag exists so the posture is reviewable — it is not what
     * makes Phase 1 inert, and Phase 1 must behave identically either way.
     *
     * @test
     */
    public function the_feature_flags_default_off_and_phase_one_does_not_depend_on_them(): void
    {
        $this->assertFalse(config('listing_preferences.enabled'));
        $this->assertFalse(config('listing_preferences.guest_capture_enabled'));
        $this->assertFalse(config('listing_preferences.learning_enabled'));

        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.learning_enabled', true);

        // Enabling the flags starts nothing: there is no caller to start.
        $this->assertSame(0, ListingPreference::count());
        $this->assertSame(0, ListingPreferenceEvent::count());
    }

    /**
     * The flags govern a customer-facing write path, which makes them safety
     * switches — and the deploy contract may never name one.
     *
     * @test
     */
    public function the_flags_are_not_in_the_required_production_flags_contract(): void
    {
        $contract = json_encode(config('required_production_flags', []));

        $this->assertStringNotContainsString('listing_preferences', (string) $contract);
        $this->assertStringNotContainsString('LISTING_PREFERENCES', (string) $contract);
    }

    /**
     * Phase 1 adds no provider bindings — nothing can be injected by accident,
     * the same posture PropertyCoordinateResolverInterface takes.
     *
     * @test
     */
    public function nothing_binds_a_listing_preference_writer(): void
    {
        $this->assertFalse(app()->bound(\App\Services\ListingPreferences\ListingPreferenceSubjectResolver::class . 'Interface'));

        // The resolver is resolvable but is not a singleton anybody wired in.
        $this->assertFalse(app()->isShared(\App\Services\ListingPreferences\ListingPreferenceSubjectResolver::class));
    }
}
