<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The legacy "Recommended For You" strip under the BUYER Hire Agent detail page.
 *
 * ── WHAT IT IS ───────────────────────────────────────────────────────────────────────────────
 *
 * Buyer alone ended its detail page with a "Recommended For You" heading and two property cards.
 * They are HARDCODED MOCKUP MARKUP, not a recommendation feature: both cards name the same fixed
 * address, quote the same "#12345" MLS id, the same beds/baths/sqft, the same "$1,000" and the
 * same frozen countdown, and load their photo from an absolute bidyouroffer.com URL. No query, no
 * controller variable and no component feeds the block — the only Blade inside it is three
 * `asset()` calls for icon SVGs.
 *
 * That is why this file asserts on the block's own fingerprints — the container class, the
 * hardcoded address, the external image host — rather than on the heading alone. A test that only
 * counted the words "Recommended For You" would pass against a page still shipping the cards under
 * a different title.
 *
 * ── WHAT IT DELIBERATELY DOES NOT DO ─────────────────────────────────────────────────────────
 *
 * It does not assert that buyer recommendations are gone from the product. The other views that
 * carry a "Recommended For You" heading each hold their own separate copy of this markup, and the
 * CSS classes involved — `buyerOfferContentDetails`, `cardsDetails` — are shared with the live
 * buyer search and author surfaces. The suppression is therefore a Blade guard scoped to this one
 * page, and the flag-off cases below exist to keep it that way: with the redesign off the block
 * must still render exactly as it does on the legacy page today.
 */
class HireAgentBuyerRecommendationsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /**
     * The block's own fingerprints.
     *
     * `Recommended For You` is the heading; the rest are the cards themselves. Asserting on all of
     * them together is what makes "the section is gone" mean "the markup is gone" rather than "the
     * title changed".
     */
    private const RECOMMENDATION_FINGERPRINTS = [
        'Recommended For You',
        'buyerOfferContentDetails',
        'cardsDetails',
        '1199 Randall Way',
        'bidyouroffer.com/wp-content',
    ];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0:class-string<Model>,1:string} model, view route */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view'],
        };
    }

    private function makeListing(string $role): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $owner = User::factory()->create(['user_type' => 'seller']);

        $attributes = [
            'user_id'     => $owner->id,
            'title'       => ucfirst($role) . ' recommendation listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Owner Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Owner Street');
        }

        // Seller's controller redirects anything that looks like an Offer Listing.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ([
            'listing_title'   => ucfirst($role) . ' listing title',
            'property_type'   => 'Residential Property',
            'auction_type'    => 'Traditional',
            'expiration_date' => now()->addDays(30)->toDateTimeString(),
            'first_name'      => 'Owner',
        ] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function renderAsGuest(string $role, Model $listing): string
    {
        [, $route] = $this->wiringFor($role);

        auth()->logout();
        $this->app->get('auth')->forgetGuards();
        $this->assertGuest();

        return $this->get(route($route, $listing->id))->assertOk()->getContent();
    }

    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => self::ROLES,
        ]);
    }

    private function disableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);
    }

    /**
     * The document with its <style> blocks removed.
     *
     * `cardsDetails` and `buyerOfferContentDetails` are named by the shared stylesheet, which ships
     * in BOTH flag states. A whole-document count would report the CSS rather than the page and
     * would pass no matter what markup rendered.
     */
    private function markup(string $html): string
    {
        return preg_replace('/<style\b.*?<\/style>/is', '', $html);
    }

    private function occurrences(string $html, string $needle): int
    {
        return substr_count($this->markup($html), $needle);
    }

    /** Net `<div>` / `</div>` balance of the rendered document, ignoring script and style bodies. */
    private function divBalance(string $html): int
    {
        $body = preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html);

        return preg_match_all('/<div\b/i', $body) - preg_match_all('/<\/div>/i', $body);
    }

    public static function roleProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    public static function otherRoleProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    // ── 1-2: buyer, redesign ON — heading and cards both gone ────────────────

    public function test_buyer_redesign_on_does_not_render_the_recommended_heading(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest('buyer', $this->makeListing('buyer'));

        $this->assertSame(
            0,
            $this->occurrences($html, 'Recommended For You'),
            'Buyer under the redesign must not render the "Recommended For You" heading.'
        );
    }

    public function test_buyer_redesign_on_does_not_render_the_recommendation_cards(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest('buyer', $this->makeListing('buyer'));

        foreach (self::RECOMMENDATION_FINGERPRINTS as $fingerprint) {
            $this->assertSame(
                0,
                $this->occurrences($html, $fingerprint),
                "Buyer under the redesign must not render the recommendation block: found \"$fingerprint\"."
            );
        }
    }

    // ── 3: the page still terminates through the role-info card ──────────────

    /**
     * The suppression must end the page, not truncate it.
     *
     * The role-info section — "Agent's Info" / "Buyer's Info" — is the last content block before
     * the removed strip, so it is the thing a too-wide guard would take with it.
     */
    public function test_buyer_redesign_on_still_renders_through_the_role_info_card(): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest('buyer', $this->makeListing('buyer'));

        $this->assertStringContainsString(
            'hla-section-role-info',
            $this->markup($html),
            'Buyer under the redesign must still render the role-info (Agent\'s Info) section.'
        );
        $this->assertStringContainsString(
            'data-hire-agent-sidebar-card',
            $this->markup($html),
            'Buyer under the redesign must still render the sidebar card.'
        );
        $this->assertSame(
            1,
            $this->occurrences($html, 'Log in to bid'),
            'Buyer under the redesign must still render exactly one guest CTA.'
        );
    }

    /**
     * THE ORPHAN CLOSE TAG, which is the regression this file exists to pin.
     *
     * The removed range opens with a `</div>` that nothing in the slot opens, and closes with a
     * `<div class="container buyerOfferContentDetails">` that nothing closes. The two malformed
     * halves cancel, which is the only reason the slot nets out. Guarding just the visible section
     * — from the `<hr>` down — would have left the orphan close behind to shut the shell's own
     * container early and pull the footer up into the detail card.
     *
     * Asserting the two flag states have the SAME balance, rather than a balance of zero, is
     * deliberate: it says "suppressing the block changed no nesting" without also asserting the
     * legacy page is well-formed, which is a separate claim this change does not make.
     */
    public function test_buyer_suppression_leaves_the_document_nesting_unchanged(): void
    {
        $listing = $this->makeListing('buyer');

        $this->enableRedesign();
        $on = $this->divBalance($this->renderAsGuest('buyer', $listing));

        $this->disableRedesign();
        $off = $this->divBalance($this->renderAsGuest('buyer', $listing));

        $this->assertSame(
            $off,
            $on,
            'Suppressing the recommendation block must not change the document\'s div nesting: '
            . "flag off balanced $off, flag on balanced $on."
        );
    }

    // ── 4: flags off preserves the legacy block exactly ──────────────────────

    public function test_buyer_redesign_off_preserves_the_legacy_recommendation_block(): void
    {
        $this->disableRedesign();

        $html = $this->renderAsGuest('buyer', $this->makeListing('buyer'));

        $this->assertSame(
            1,
            $this->occurrences($html, 'Recommended For You'),
            'Flag off must keep the legacy "Recommended For You" heading.'
        );
        $this->assertSame(
            1,
            $this->occurrences($html, 'buyerOfferContentDetails'),
            'Flag off must keep the legacy recommendation container.'
        );
        $this->assertSame(
            1,
            $this->occurrences($html, 'cardsDetails'),
            'Flag off must keep the legacy recommendation card grid.'
        );
        $this->assertSame(
            2,
            $this->occurrences($html, '1199 Randall Way'),
            'Flag off must keep both hardcoded recommendation cards.'
        );
    }

    // ── 5: the other three roles are untouched in both flag states ───────────

    /**
     * @dataProvider otherRoleProvider
     */
    public function test_other_roles_never_render_a_recommendation_block_with_redesign_on(string $role): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        foreach (self::RECOMMENDATION_FINGERPRINTS as $fingerprint) {
            $this->assertSame(
                0,
                $this->occurrences($html, $fingerprint),
                "[$role] must not render the recommendation block: found \"$fingerprint\"."
            );
        }
    }

    /**
     * The same roles with the flag OFF.
     *
     * This is the half that proves the buyer-only guard did not reach sideways: seller, landlord
     * and tenant never carried this block, so neither flag state may start or stop showing one.
     *
     * @dataProvider otherRoleProvider
     */
    public function test_other_roles_never_render_a_recommendation_block_with_redesign_off(string $role): void
    {
        $this->disableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        foreach (self::RECOMMENDATION_FINGERPRINTS as $fingerprint) {
            $this->assertSame(
                0,
                $this->occurrences($html, $fingerprint),
                "[$role] flag off must not render the recommendation block: found \"$fingerprint\"."
            );
        }
    }
}
