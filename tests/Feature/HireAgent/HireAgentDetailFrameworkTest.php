<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use App\Support\HireAgent\HireAgentHeroData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Milestone 4 — the shared Hire Agent Listing Detail Framework.
 *
 * The four Hire Agent detail views total ~17,000 lines and had drifted: each carried its own copy
 * of the same ~290-line stylesheet, its own copy of the same flash-message block, and no hero at
 * all — the page opened straight into a "Listing Details:" card with the title tucked at the top
 * of the right column. Landlord even wrapped its first two cards in different markup from the
 * other three.
 *
 * This file asserts the framework is actually adopted rather than merely present, that the hero
 * shows the right role-specific values, and — the larger half — that adopting it moved none of
 * the behaviour the previous three milestones established.
 *
 * Structural and behavioural assertions only, no pixel snapshots: a snapshot of a 4,000-line page
 * fails on every unrelated copy edit and teaches nothing when it does.
 *
 * @see \App\Support\HireAgent\HireAgentHeroData for the role-specific data contract
 */
class HireAgentDetailFrameworkTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEWS = [
        'seller'   => 'resources/views/hire_seller_agent/view.blade.php',
        'buyer'    => 'resources/views/hire_buyer_agent/view.blade.php',
        'landlord' => 'resources/views/hire_landlord_agent/view.blade.php',
        'tenant'   => 'resources/views/hire_tenant_agent/view.blade.php',
    ];

    public function roles(): array
    {
        return ['seller' => ['seller'], 'buyer' => ['buyer'], 'landlord' => ['landlord'], 'tenant' => ['tenant']];
    }

    /** @return array{0: class-string, 1: class-string, 2: string, 3: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   SellerAgentAuctionBid::class,   'seller_agent_auction_id',   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    BuyerAgentAuctionBid::class,    'buyer_agent_auction_id',    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, LandlordAgentAuctionBid::class, 'landlord_agent_auction_id', 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   TenantAgentAuctionBid::class,   'tenant_agent_auction_id',   'tenant.agent.auction.view'],
        };
    }

    /**
     * A live listing with the role's hero inputs planted.
     *
     * `budget` is the single stored key all four roles use for their headline figure — its
     * MEANING differs (sale price / monthly rent / purchase budget / rental budget) but the key
     * does not, which is exactly why the presenter decides the label. Cities are planted for all
     * roles so the Buyer/Tenant "Preferred Area" assertions have a real value to find and the
     * Seller/Landlord assertions prove the presenter does NOT show an area for them.
     */
    private function makeListing(string $role, int $ownerId, array $overrides = []): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' hire-agent listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Framework Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Framework Street');
        }

        $meta = array_merge([
            'listing_title'        => ucfirst($role) . ' listing title',
            'budget'               => '654321',
            'property_type'        => 'Residential Property',
            'commission_structure' => 'Percentage of Sale Price',
            'purchase_fee_type'    => 'Flat Fee',
            'cities'               => json_encode(['Austin, TX', 'Round Rock, TX']),
            'expiration_date'      => now()->addDays(30)->toDateTimeString(),
        ], $overrides);

        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function makeBid(string $role, int $listingId, int $userId): Model
    {
        [, $bidClass, $fk] = $this->wiringFor($role);
        $bid = $bidClass::forceCreate([$fk => $listingId, 'user_id' => $userId]);

        if (in_array($role, ['seller', 'buyer'], true)) {
            $bid->brokerage = '250.00';
            $bid->save();
        } else {
            $bid->saveMeta('purchase_fee_type', 'Flat Fee');
            $bid->saveMeta('purchase_fee_flat', '250.00');
        }

        return $bid;
    }

    private function urlFor(string $role, int $id): string
    {
        [, , , $route] = $this->wiringFor($role);

        return route($route, $id);
    }

    /** Positive control — the page under test really is this listing's detail page. */
    private function assertIsDetailPage($response, int $listingId): void
    {
        $response->assertOk();
        $this->assertSame($listingId, (int) $response->viewData('auction')->id);
    }

    // ── 1. all four adopt the framework ──────────────────────────────────────

    /**
     * Source-level: each view must pull the shared stylesheet and the shared flash component
     * rather than carrying its own copy. Asserted on source because "the CSS is shared" is a
     * structural fact that no rendered-HTML probe can distinguish from a lucky duplicate.
     */
    public function test_all_four_views_include_the_shared_framework(): void
    {
        foreach (self::VIEWS as $role => $rel) {
            $src = file_get_contents(base_path($rel));

            $this->assertStringContainsString(
                "@include('hire_agent.framework.styles')",
                $src,
                "The {$role} detail view must use the shared framework stylesheet."
            );
            $this->assertStringContainsString(
                '<x-hire-agent.flash />',
                $src,
                "The {$role} detail view must use the shared flash component."
            );
            $this->assertStringContainsString(
                '<x-hire-agent.hero',
                $src,
                "The {$role} detail view must render the shared hero."
            );
        }
    }

    /**
     * The duplicated stylesheet is really gone, not merely supplemented.
     *
     * Thirty rules were byte-identical across all four views. If a view still declared them
     * inline it would both duplicate the shared partial and be free to drift from it again, so
     * the check is that no view's residual block re-declares a rule the shared partial owns.
     */
    public function test_no_view_re_declares_a_shared_style_rule(): void
    {
        $shared = file_get_contents(base_path('resources/views/hire_agent/framework/styles.blade.php'));

        // Selectors the shared partial owns, sampled across the block.
        $ownedSelectors = ['.field-row', '.field-label', '.services-section-header'];

        foreach (self::VIEWS as $role => $rel) {
            $src = file_get_contents(base_path($rel));

            // Only look inside the view's own <style> block, not the whole file.
            preg_match('/<style>(.*?)<\/style>/s', $src, $m);
            $inline = $m[1] ?? '';

            foreach ($ownedSelectors as $sel) {
                $this->assertStringContainsString($sel, $shared, "Precondition: the shared partial owns {$sel}.");
                $this->assertStringNotContainsString(
                    $sel . ' {',
                    $inline,
                    "The {$role} view still declares {$sel} inline; it belongs to the shared partial now."
                );
            }
        }
    }

    // ── 2. all four render the shared hero ───────────────────────────────────

    /**
     * @dataProvider roles
     */
    public function test_every_role_renders_the_shared_hero(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id);

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        $response->assertSee('data-hla-hero', false);
        $response->assertSee('data-hla-role="' . $role . '"', false);
        $response->assertSee('hla-hero-title', false);
    }

    /** Exactly one hero per page — the component must not be rendered twice. */
    public function test_hero_is_rendered_exactly_once_per_page(): void
    {
        foreach (array_keys(self::VIEWS) as $role) {
            $owner   = User::factory()->create(['user_type' => 'seller']);
            $listing = $this->makeListing($role, $owner->id);

            $body = $this->actingAs($owner)->get($this->urlFor($role, $listing->id))->getContent();

            $this->assertSame(
                1,
                substr_count($body, 'data-hla-role="'),
                "The {$role} page must render exactly one hero."
            );
        }
    }

    // ── 3-6. role-specific hero content ──────────────────────────────────────

    /** 3. Seller: listing price + broker compensation. */
    public function test_seller_hero_shows_listing_price_and_broker_compensation(): void
    {
        $hero = HireAgentHeroData::for('seller', $this->makeListing('seller', User::factory()->create()->id));

        $this->assertSame('Listing Price', $hero['figure']['label']);
        $this->assertStringContainsString('654,321', $hero['figure']['value']);
        $this->assertContains(
            ['label' => 'Broker Compensation', 'value' => 'Percentage of Sale Price'],
            $hero['facts']
        );
    }

    /** 4. Landlord: monthly rent + leasing compensation. */
    public function test_landlord_hero_shows_monthly_rent_and_leasing_compensation(): void
    {
        $hero = HireAgentHeroData::for('landlord', $this->makeListing('landlord', User::factory()->create()->id));

        $this->assertSame('Monthly Rent', $hero['figure']['label']);
        $this->assertStringContainsString('654,321', $hero['figure']['value']);

        $labels = array_column($hero['facts'], 'label');
        $this->assertContains('Leasing Compensation', $labels);
    }

    /** 5. Buyer: purchase budget + preferred area. */
    public function test_buyer_hero_shows_purchase_budget_and_preferred_area(): void
    {
        $hero = HireAgentHeroData::for('buyer', $this->makeListing('buyer', User::factory()->create()->id));

        $this->assertSame('Purchase Budget', $hero['figure']['label']);
        $this->assertStringContainsString('654,321', $hero['figure']['value']);

        $area = collect($hero['facts'])->firstWhere('label', 'Preferred Area');
        $this->assertNotNull($area, 'Buyer hero must show a preferred area.');
        $this->assertStringContainsString('Austin', $area['value']);
    }

    /** 6. Tenant: rental budget + preferred area. */
    public function test_tenant_hero_shows_rental_budget_and_preferred_area(): void
    {
        $hero = HireAgentHeroData::for('tenant', $this->makeListing('tenant', User::factory()->create()->id));

        $this->assertSame('Rental Budget', $hero['figure']['label']);
        $this->assertStringContainsString('654,321', $hero['figure']['value']);

        $area = collect($hero['facts'])->firstWhere('label', 'Preferred Area');
        $this->assertNotNull($area, 'Tenant hero must show a preferred area.');
        $this->assertStringContainsString('Austin', $area['value']);
    }

    /**
     * The role contract is a real contract: a property role must NOT be given a client-brief
     * field and vice versa. Without this, all four could quietly collapse to the same fields and
     * the per-role tests above would still pass.
     */
    public function test_property_roles_and_client_roles_get_different_secondary_facts(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $labels = array_column(HireAgentHeroData::for($role, $this->makeListing($role, User::factory()->create()->id))['facts'], 'label');
            $this->assertNotContains('Preferred Area', $labels, "{$role} describes a property, not a search area.");
        }

        foreach (['buyer', 'tenant'] as $role) {
            $labels = array_column(HireAgentHeroData::for($role, $this->makeListing($role, User::factory()->create()->id))['facts'], 'label');
            $this->assertNotContains('Broker Compensation', $labels, "{$role} describes a need, not a listing's compensation.");
            $this->assertNotContains('Leasing Compensation', $labels);
        }
    }

    /** A missing value omits its slot rather than rendering a placeholder or a zero. */
    public function test_absent_values_are_omitted_not_placeheld(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeListing('seller', $owner->id, ['budget' => '', 'commission_structure' => '', 'purchase_fee_type' => '', 'lease_fee_type' => '', 'brokerage_relationship' => '']);

        $hero = HireAgentHeroData::for('seller', $listing);

        $this->assertNull($hero['figure'], 'No budget must mean no figure — not $0.');
        $this->assertNotContains('Broker Compensation', array_column($hero['facts'], 'label'));
    }

    // ── 7. role cards retained ───────────────────────────────────────────────

    /**
     * The framework must not have flattened the roles into one card set. Each role keeps the
     * cards that belong to it, and the shared cards stay shared.
     */
    public function test_each_role_retains_its_own_detail_cards(): void
    {
        $expected = [
            'seller'   => ['Property Details', 'Sale Terms', 'Financing Details'],
            'buyer'    => ['Property Preferences', 'Purchasing Terms', 'Financing Details'],
            'landlord' => ['Property Details', 'Leasing Terms'],
            'tenant'   => ['Property Preferences', 'Leasing Terms', 'Pre-Screening'],
        ];

        foreach ($expected as $role => $cards) {
            $src = file_get_contents(base_path(self::VIEWS[$role]));
            foreach ($cards as $card) {
                $this->assertStringContainsString($card, $src, "The {$role} view must keep its '{$card}' card.");
            }
        }

        // Cards that genuinely are common to all four stay in all four.
        foreach (self::VIEWS as $role => $rel) {
            $src = file_get_contents(base_path($rel));
            foreach (['Services', 'Additional Details', 'Broker Compensation & Agency Agreement Terms', 'Referral & Cooperation Terms'] as $shared) {
                $this->assertStringContainsString($shared, $src, "The {$role} view must keep the shared '{$shared}' card.");
            }
        }
    }

    // ── 8-9. retired behaviour must not return through the framework ─────────

    /**
     * 8. The hero is new markup and therefore a fresh opportunity to reintroduce a countdown.
     *
     * @dataProvider roles
     */
    public function test_framework_reintroduces_no_countdown_or_bidding_period_markup(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id, [
            // The legacy timer configuration, planted so a regression would have fuel.
            'auction_type' => 'Bidding Period',
            'auction_time' => '1 Days',
        ]);

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $this->assertIsDetailPage($response, $listing->id);

        foreach (['timer-d', 'timer-h', 'data-expiration', 'timer.jquery', 'countdown: true',
                  'Bidding Ended', 'Bidding Period Length', '>Days<', '>Hrs<', '>Secs<'] as $needle) {
            $response->assertDontSee($needle, false);
        }
    }

    /**
     * The hero must not echo a retired listing-type label even when the stored row still says so.
     */
    public function test_hero_suppresses_the_retired_bidding_period_listing_type_label(): void
    {
        foreach (['Bidding Period', 'Auction (Timer)'] as $legacy) {
            $listing = $this->makeListing('seller', User::factory()->create()->id, ['auction_type' => $legacy]);

            $this->assertNull(
                HireAgentHeroData::for('seller', $listing)['listingType'],
                "The hero must not surface the retired '{$legacy}' label."
            );
        }

        // Control: a legitimate type still shows, so the suppression is targeted not blanket.
        $ok = $this->makeListing('seller', User::factory()->create()->id, ['auction_type' => 'Traditional']);
        $this->assertSame('Traditional', HireAgentHeroData::for('seller', $ok)['listingType']);
    }

    /**
     * 9. No competing-proposal information may appear in the hero, for any viewer.
     *
     * @dataProvider roles
     */
    public function test_framework_discloses_no_competing_proposal_information(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id);

        $this->makeBid($role, $listing->id, $mine->id);
        $this->makeBid($role, $listing->id, $rival->id);

        $body = $this->actingAs($mine)->get($this->urlFor($role, $listing->id))->getContent();

        // Isolate the hero and assert nothing competitive reached it.
        if (preg_match('/<div class="hla-hero"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/', $body, $m)) {
            foreach (['proposal', 'bidder', 'Rank', 'Highest', 'competing'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $m[0],
                    "The {$role} hero must disclose nothing about competing proposals."
                );
            }
        }

        foreach (['was the last bidder', 'Competing Bids (', 'Highest Proposal'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    // ── 10-12. privacy behaviour preserved ───────────────────────────────────

    /**
     * @dataProvider roles
     */
    public function test_owner_still_reviews_every_proposal(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id);

        $a = $this->makeBid($role, $listing->id, $mine->id);
        $b = $this->makeBid($role, $listing->id, $rival->id);

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        $this->assertSame(
            collect([$a->id, $b->id])->sort()->values()->all(),
            $response->viewData('auction')->bids->pluck('id')->sort()->values()->all()
        );
    }

    /**
     * @dataProvider roles
     */
    public function test_submitting_agent_still_sees_only_their_own_proposal(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id);

        $myBid    = $this->makeBid($role, $listing->id, $mine->id);
        $rivalBid = $this->makeBid($role, $listing->id, $rival->id);

        $served = $this->actingAs($mine)->get($this->urlFor($role, $listing->id))
            ->viewData('auction')->bids->pluck('id')->all();

        $this->assertContains($myBid->id, $served, 'Control: the agent sees their own proposal.');
        $this->assertNotContains($rivalBid->id, $served, 'A competing proposal must stay invisible.');
    }

    // ── 13. status and expiration unchanged ──────────────────────────────────

    /**
     * @dataProvider roles
     */
    public function test_status_and_expiration_behaviour_is_unchanged(string $role): void
    {
        $agent = User::factory()->create(['user_type' => 'agent']);
        $owner = User::factory()->create(['user_type' => 'seller']);

        $live = $this->makeListing($role, $owner->id);
        $this->actingAs($agent)->get($this->urlFor($role, $live->id))->assertSee('Bid Now', false);

        $expired = $this->makeListing($role, $owner->id, ['expiration_date' => now()->subDays(3)->toDateTimeString()]);
        $response = $this->actingAs($agent)->get($this->urlFor($role, $expired->id));
        $response->assertDontSee('Bid Now', false);
        $response->assertSee('This listing has expired', false);

        // Rendering the framework must not mutate status.
        $before = $live->fresh()->status;
        $this->actingAs($owner)->get($this->urlFor($role, $live->id))->assertOk();
        $this->assertSame($before, $live->fresh()->status);
    }

    // ── 14. one primary CTA, not duplicated by responsive rendering ──────────

    /**
     * The hero introduced a second place a call-to-action could live. A common way to build a
     * responsive layout is to render the CTA twice and hide one with a d-none/d-md-block pair —
     * which produces two in the DOM, two for a screen reader, and two in the accessibility tree.
     * This asserts the primary proposal CTA exists exactly once.
     *
     * @dataProvider roles
     */
    public function test_page_has_exactly_one_primary_proposal_cta(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id);

        $body = $this->actingAs($agent)->get($this->urlFor($role, $listing->id))->getContent();

        $this->assertSame(
            1,
            substr_count($body, '>Bid Now<'),
            "The {$role} page must expose exactly one primary proposal CTA, not a duplicate hidden by CSS."
        );
    }

    /** The hero must not introduce a fixed-width element that can overflow a narrow viewport. */
    public function test_hero_declares_no_fixed_pixel_width(): void
    {
        $hero = file_get_contents(base_path('resources/views/components/hire-agent/hero.blade.php'));

        $this->assertSame(
            0,
            preg_match('/width:\s*\d{3,}px/', $hero),
            'The hero must not hard-code a pixel width — it has to survive a narrow viewport.'
        );

        $styles = file_get_contents(base_path('resources/views/hire_agent/framework/styles.blade.php'));
        $this->assertStringContainsString('@media (max-width: 767.98px)', $styles, 'The framework must define mobile behaviour.');
        $this->assertStringContainsString('max-width: 100%', $styles, 'The hero must be constrained against horizontal overflow.');
    }

    // ── 15. Create Offer untouched and uncoupled ─────────────────────────────

    /**
     * The framework is Hire Agent's alone. Create Offer keeps its separately-audited hero, and
     * nothing in either direction may reference the other — no shared file, no shared class name,
     * no include across the boundary. A shared CSS prefix would be enough to couple them, so the
     * namespaces are asserted disjoint too.
     */
    public function test_create_offer_is_untouched_and_uncoupled(): void
    {
        $partial = base_path('resources/views/offer-listing/partials/_competing-bids.blade.php');
        $this->assertFileExists($partial);
        $this->assertStringContainsString('PublicOfferFeedService', file_get_contents($partial));

        foreach (['seller', 'landlord'] as $role) {
            $view = file_get_contents(base_path("resources/views/offer-listing/{$role}/view.blade.php"));

            $this->assertStringContainsString("@include('offer-listing.partials._competing-bids'", $view);
            $this->assertStringNotContainsString('hire_agent.framework', $view, 'Create Offer must not depend on the Hire Agent framework.');
            $this->assertStringNotContainsString('x-hire-agent.', $view, 'Create Offer must not use Hire Agent components.');
        }

        // Only Create Offer's Seller view has the sol- hero; the others never had one. Asserting
        // it for all of them would fail for a reason that has nothing to do with this framework.
        $this->assertStringContainsString(
            'sol-hero',
            file_get_contents(base_path('resources/views/offer-listing/seller/view.blade.php')),
            "Create Offer's Seller view keeps its own separately-audited hero."
        );

        // …and the Hire Agent framework must not reach into Create Offer.
        foreach ([
            'resources/views/hire_agent/framework/styles.blade.php',
            'resources/views/components/hire-agent/hero.blade.php',
            'resources/views/components/hire-agent/info-card.blade.php',
            'resources/views/components/hire-agent/field.blade.php',
            'resources/views/components/hire-agent/flash.blade.php',
            'app/Support/HireAgent/HireAgentHeroData.php',
        ] as $rel) {
            $src = file_get_contents(base_path($rel));

            $this->assertStringNotContainsString('offer-listing', $src, "{$rel} must not reference Create Offer.");
            $this->assertStringNotContainsString('sol-hero', $src, "{$rel} must not reuse Create Offer's hero classes.");
            $this->assertStringNotContainsString('PublicOfferFeedService', $src);
        }
    }

    /** The framework's CSS must not select globally in a way that could reach another page. */
    public function test_framework_styles_are_hire_agent_scoped(): void
    {
        $styles = file_get_contents(base_path('resources/views/hire_agent/framework/styles.blade.php'));

        // The file's header comment mentions "<style>" in prose, so strip Blade comments before
        // locating the real block — otherwise the match starts inside the documentation.
        $styles = preg_replace('/\{\{--.*?--\}\}/s', '', $styles);

        preg_match('/<style>(.*?)<\/style>/s', $styles, $m);
        $css = $m[1] ?? '';

        $this->assertNotSame('', $css, 'Precondition: the shared stylesheet has a style block.');

        // Every NEW framework rule is .hla- prefixed.
        preg_match_all('/^\s*(\.hla-[a-z-]+)/m', $css, $found);
        $this->assertNotEmpty($found[1], 'The framework must define its own .hla- namespace.');

        // It must not claim Create Offer's namespace.
        $this->assertStringNotContainsString('.sol-', $css, 'The Hire Agent framework must not style Create Offer classes.');
    }
}
