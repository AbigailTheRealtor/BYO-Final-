<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `partials/preset_loaded_banner.blade.php` is SHARED by two different kinds of caller.
 *
 *   1. Preset-driven BID / HIRE surfaces, which declare `public bool $defaultProfileLoaded`
 *      and set it to true once an agent default profile has pre-filled the form. These
 *      must keep showing the banner exactly as before.
 *
 *   2. COUNTER surfaces (Landlord and Seller counter-term components), which have no
 *      preset concept at all and never declare the flag. Under the old bare
 *      `@if($defaultProfileLoaded)` these died in the view with an undefined variable,
 *      500'ing the whole counter page.
 *
 * The `?? false` guard serves both: absent flag means "no preset was loaded", which is
 * true by construction on a counter surface.
 *
 * This file pins the shared partial's contract directly, so the guard cannot be reverted
 * and the banner cannot silently disappear from the callers that legitimately want it.
 *
 * @see \Tests\Feature\LandlordCounterTermRenderTest for the live routed Landlord flow.
 */
class PresetLoadedBannerSharedPartialTest extends TestCase
{
    use DatabaseTransactions;

    private const PARTIAL = 'partials.preset_loaded_banner';
    private const BANNER  = 'Loaded from your preset';

    /** Every view that includes the shared partial, and the component that owns it. */
    private const CALLERS = [
        'livewire/buyer-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php'
            => \App\Http\Livewire\Buyer\BuyerAgentAuctionBid::class,
        'livewire/landlord-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php'
            => \App\Http\Livewire\Landlord\LandlordAgentAuctionBid::class,
        'livewire/tenant-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php'
            => \App\Http\Livewire\Tenant\TenantAgentAuctionBid::class,
        'livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/broker-compensation.blade.php'
            => \App\Http\Livewire\HireSellerAgent\SellerAgentAuction::class,
    ];

    // ── The banner still appears for the callers that set the flag ──

    /** @test */
    public function the_banner_renders_when_a_preset_was_loaded(): void
    {
        $html = view(self::PARTIAL, ['defaultProfileLoaded' => true])->render();

        $this->assertStringContainsString(self::BANNER, $html);
        $this->assertStringContainsString('alert-success', $html);
    }

    /** @test */
    public function the_banner_is_hidden_when_the_flag_is_explicitly_false(): void
    {
        $this->assertStringNotContainsString(
            self::BANNER,
            view(self::PARTIAL, ['defaultProfileLoaded' => false])->render()
        );
    }

    // ── …and the partial no longer explodes for a caller that has no such flag ──

    /** @test */
    public function the_partial_renders_empty_instead_of_throwing_when_the_flag_is_absent(): void
    {
        $html = view(self::PARTIAL)->render();

        $this->assertSame('', trim($html), 'A caller with no preset concept must get nothing, not a banner.');
        $this->assertStringNotContainsString(self::BANNER, $html);
    }

    /**
     * @test
     *
     * The guard must be null-coalescing, not a deletion of the condition. If someone
     * "simplifies" it to an unconditional banner, every counter page grows a false
     * "Loaded from your preset" claim.
     */
    public function the_partial_still_guards_on_the_flag(): void
    {
        $source = file_get_contents(resource_path('views/partials/preset_loaded_banner.blade.php'));

        $this->assertStringContainsString('@if($defaultProfileLoaded ?? false)', $source);
    }

    // ── Buyer / Tenant / Seller / Landlord bid callers are unchanged ──

    /** @test */
    public function every_bid_caller_still_includes_the_shared_partial(): void
    {
        foreach (array_keys(self::CALLERS) as $view) {
            $this->assertStringContainsString(
                "@include('partials.preset_loaded_banner')",
                file_get_contents(resource_path('views/' . $view)),
                "{$view} no longer includes the shared preset banner."
            );
        }
    }

    /**
     * @test
     *
     * `?? false` would also silently swallow a genuine regression — a component that
     * dropped the property would stop showing its banner with no error. Pin the four
     * preset-driven components that own the including views.
     */
    public function every_bid_caller_component_still_declares_the_preset_flag(): void
    {
        foreach (self::CALLERS as $view => $component) {
            $this->assertTrue(
                property_exists($component, 'defaultProfileLoaded'),
                "{$component} (renders {$view}) dropped \$defaultProfileLoaded — its preset banner is now dead."
            );
        }
    }

    /**
     * @test
     *
     * The counter components are the callers the guard exists for. Neither declares the
     * flag, and neither should start to.
     */
    public function the_counter_components_remain_free_of_the_preset_flag(): void
    {
        foreach ([
            \App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm::class,
            \App\Http\Livewire\Seller\SellerAgentAuctionCounterTerm::class,
        ] as $component) {
            $this->assertFalse(
                property_exists($component, 'defaultProfileLoaded'),
                "{$component} must not adopt \$defaultProfileLoaded — it has no preset."
            );
        }
    }
}
