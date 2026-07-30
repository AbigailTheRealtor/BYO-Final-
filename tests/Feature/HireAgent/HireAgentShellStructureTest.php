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
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Structural facts about the four Hire Agent detail pages, measured from rendered DOM.
 *
 * WHY DOM AND NOT SOURCE COUNTING. Milestone 4 deferred the shared shell on the strength of a
 * visual read of the Blade source, and that read was wrong in both directions: Seller's markup was
 * called malformed when it is not, and the stray trailing <hr> was attributed to Landlord alone
 * when Buyer has one too. Counting <div> against </div> in Blade source cannot settle any of this,
 * because every @if/@else branch contributes tags that are mutually exclusive at runtime — a
 * depth tracker walking Seller's source reports the sidebar closing ~50 lines early, in the middle
 * of the share card. Every assertion here therefore runs against a DOMDocument built from a real
 * HTTP response.
 *
 * Class matching is by exact token. `contains(@class, 'hla-hero')` also matches hla-hero-title,
 * hla-hero-chip and every other descendant — it reported 16 heroes on a page that has one.
 *
 * The nested-form check plants a proposal first. Without one the owner sees no action forms at
 * all, `//form//form` is trivially zero, and the assertion passes while proving nothing.
 *
 * WHAT THIS FILE IS FOR. It is the baseline a shared shell has to be built against, and the
 * regression net for when one is. It records the CURRENT structure precisely — including a
 * divergence between the four roles that a shell cannot paper over silently.
 */
class HireAgentShellStructureTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Rendered nesting levels between the `rightCol` element and the `listingDescription`
     * container, measured per role.
     *
     * These are NOT all the same, which is the single most important structural fact about these
     * four pages and the reason a shell is not a drop-in:
     *
     *   seller   1  — container > row > rightCol       (the intended Bootstrap grid)
     *   buyer    1  — container > row > rightCol       (repaired in Milestone 5A.2-B)
     *   landlord 1  — container > row > rightCol       (the intended Bootstrap grid)
     *   tenant   2  — container > row > leftCol > rightCol  (STILL BROKEN — 5A.2-T)
     *
     * Bootstrap's col-* classes rely on the negative margins and gutters a .row supplies, so a
     * sidebar outside the row is laid out differently from one inside it. Buyer's was outside the
     * row entirely until 5A.2-B; Tenant's is still nested inside the MAIN column, which is why it
     * measures 2 rather than 1 and why its repair is a separate checkpoint.
     *
     * Encoding these means a change here has to be made deliberately, with someone looking at the
     * result, instead of being discovered in production.
     */
    private const RIGHTCOL_DEPTH_BELOW_CONTAINER = [
        'seller'   => 1,
        'buyer'    => 1,
        'landlord' => 1,
        'tenant'   => 2,
    ];

    /**
     * <hr> elements rendered as a following sibling of the listing container.
     *
     * The count alone does not say whether one is a defect — context does, and checking context
     * overturned an earlier assumption. Landlord's was the last node on the page with nothing
     * after it to separate: accidental, and removed in Milestone 5A. Buyer's looks identical to
     * this query but divides the listing from the "Recommended For You" section that follows it,
     * so it is a real separator and stays. Seller and Tenant have none.
     *
     * Keeping Buyer at 1 is therefore an assertion that a legitimate divider was NOT swept up by
     * a cleanup that only counted.
     */
    private const TRAILING_HR = [
        'seller'   => 0,
        'buyer'    => 1,
        'landlord' => 0,
        'tenant'   => 0,
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
            $attributes['address'] = '100 Shell Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Shell Street');
        }

        foreach (array_merge([
            'listing_title'        => ucfirst($role) . ' listing title',
            'budget'               => '654321',
            'property_type'        => 'Residential Property',
            'commission_structure' => 'Percentage of Sale Price',
            'cities'               => json_encode(['Austin, TX']),
            'auction_type'         => 'Traditional',
            'expiration_date'      => now()->addDays(30)->toDateTimeString(),
        ], $overrides) as $k => $v) {
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

    /**
     * Render a role's detail page as its owner, with one proposal already submitted, and return
     * an XPath handle over the result.
     *
     * The proposal is not decoration: several assertions below are only meaningful on a page that
     * actually renders action forms.
     */
    private function renderOwnerView(string $role): DOMXPath
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id);

        $this->makeBid($role, $listing->id, $agent->id);

        $response = $this->actingAs($owner)->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        return $this->xpath($response->getContent());
    }

    /**
     * libxml is silenced deliberately. These pages are ~4,000 lines of legacy markup and libxml
     * objects to things that say nothing about page structure (unknown attributes, HTML5
     * elements). The assertions target specific regions rather than global validity, so an
     * unrelated legacy warning must not be able to fail this file.
     */
    private function xpath(string $html): DOMXPath
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    private function nodes(DOMXPath $x, string $query): int
    {
        return $x->query($query)->length;
    }

    /** XPath fragment matching a whole class token rather than a substring. */
    private function classQuery(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }

    private function countClass(DOMXPath $x, string $class): int
    {
        return $this->nodes($x, $this->classQuery($class));
    }

    private function firstWithClass(DOMXPath $x, string $class): ?DOMNode
    {
        return $x->query($this->classQuery($class))->item(0);
    }

    // ── Region uniqueness ────────────────────────────────────────────────────

    /**
     * Exactly one of each structural region, on every role.
     *
     * A duplicated hero or a second sidebar is the classic failure mode of a shell extraction —
     * the old markup is left in place while the new component also renders — and it is invisible
     * to any test that only asks "is the hero present".
     *
     * @dataProvider roles
     */
    public function test_each_page_renders_exactly_one_of_each_structural_region(string $role): void
    {
        $x = $this->renderOwnerView($role);

        foreach ([
            'hla-hero'           => 'hero',
            'listingDescription' => 'root listing region',
            'leftCol'            => 'main column',
            'rightCol'           => 'sidebar column',
        ] as $class => $label) {
            $this->assertSame(
                1,
                $this->countClass($x, $class),
                "{$role}: expected exactly one {$label} (.{$class})."
            );
        }
    }

    /**
     * The sidebar follows the main column in document order, so a narrow viewport stacks
     * summary-then-details-then-actions without any CSS reordering.
     *
     * @dataProvider roles
     */
    public function test_sidebar_follows_main_column_in_dom_order(string $role): void
    {
        $x = $this->renderOwnerView($role);

        // DOMNode::compareDocumentPosition() is not available in this PHP's DOM extension, and
        // XPath returns nodes in document order anyway — so selecting both columns in one query
        // and reading the order off the result is both simpler and portable.
        $columns = $x->query($this->classQuery('leftCol') . ' | ' . $this->classQuery('rightCol'));

        $this->assertSame(2, $columns->length, "{$role}: expected exactly one main and one sidebar column.");

        $order = [];
        foreach ($columns as $node) {
            $order[] = str_contains(' ' . $node->getAttribute('class') . ' ', ' leftCol ') ? 'main' : 'sidebar';
        }

        $this->assertSame(
            ['main', 'sidebar'],
            $order,
            "{$role}: the sidebar must come after the main column in DOM order, so a narrow "
            . 'viewport stacks details before actions without CSS reordering.'
        );
    }

    // ── Form integrity ───────────────────────────────────────────────────────

    /**
     * No form inside another form, in a state that actually has forms.
     *
     * Nested forms are the specific way a wrapper refactor breaks a page: the browser silently
     * drops the inner form and its submit button stops working, with no error anywhere.
     *
     * @dataProvider roles
     */
    public function test_no_nested_forms_with_a_proposal_present(string $role): void
    {
        $x = $this->renderOwnerView($role);

        $this->assertGreaterThan(
            0,
            $this->nodes($x, '//form'),
            "{$role}: control — a page with a proposal must render action forms, or the nested-form check proves nothing."
        );

        $this->assertSame(
            0,
            $this->nodes($x, '//form//form'),
            "{$role}: a form must never be nested inside another form."
        );
    }

    // ── Measured column nesting ──────────────────────────────────────────────

    /**
     * The four roles do NOT nest their sidebar identically, and this pins the current values.
     *
     * See RIGHTCOL_DEPTH_BELOW_CONTAINER for why this matters: Buyer's sidebar has no .row
     * ancestor at all and Tenant's sits one level deeper than Seller's and Landlord's, so a
     * shared shell that imposes one grid necessarily changes Buyer's and Tenant's layout. This
     * test exists so that change has to be made on purpose.
     *
     * @dataProvider roles
     */
    public function test_rendered_sidebar_nesting_depth_matches_the_recorded_baseline(string $role): void
    {
        $x = $this->renderOwnerView($role);

        $right     = $this->firstWithClass($x, 'rightCol');
        $container = $this->firstWithClass($x, 'listingDescription');

        $this->assertNotNull($right);
        $this->assertNotNull($container);

        $depth = 0;
        for ($n = $right->parentNode; $n && $n !== $container; $n = $n->parentNode) {
            $depth++;
        }

        // Also measure the MAIN column, because the two only tell the story together: if both
        // columns sit at the same depth the grid is merely shaped differently per role, whereas
        // a mismatch between them means that role's two columns are not siblings in one grid.
        $left = $this->firstWithClass($x, 'leftCol');
        $leftDepth = 0;
        for ($n = $left->parentNode; $n && $n !== $container; $n = $n->parentNode) {
            $leftDepth++;
        }
        $this->assertSame(
            1,
            $leftDepth,
            "{$role}: the main column sits one level below the container on every role."
        );

        $this->assertSame(
            self::RIGHTCOL_DEPTH_BELOW_CONTAINER[$role],
            $depth,
            "{$role}: rendered sidebar nesting changed. If a shared shell did this deliberately, "
            . 'update RIGHTCOL_DEPTH_BELOW_CONTAINER and confirm the layout visually first.'
        );
    }

    // ── Separators ───────────────────────────────────────────────────────────

    /**
     * @dataProvider roles
     */
    public function test_trailing_separator_count_matches_the_recorded_baseline(string $role): void
    {
        $x = $this->renderOwnerView($role);

        $this->assertSame(
            self::TRAILING_HR[$role],
            $this->nodes($x, $this->classQuery('listingDescription') . '/following-sibling::hr'),
            "{$role}: trailing <hr> count changed."
        );
    }

    /**
     * Legitimate separators inside the main column divide real content and must survive any
     * wrapper work.
     *
     * @dataProvider roles
     */
    public function test_main_column_keeps_its_content_separators(string $role): void
    {
        $x = $this->renderOwnerView($role);

        $this->assertGreaterThan(
            0,
            $this->nodes($x, $this->classQuery('leftCol') . '//hr'),
            "{$role}: the main column's content separators must not be removed."
        );
    }

    // ── Retired behaviour must not reappear through structural work ──────────

    /**
     * @dataProvider roles
     */
    public function test_no_countdown_or_bidding_period_markup_is_rendered(string $role): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        $agent = User::factory()->create(['user_type' => 'agent']);
        // Plant the legacy timer configuration so a regression would have something to render.
        $listing = $this->makeListing($role, $owner->id, ['auction_type' => 'Bidding Period', 'auction_time' => '1 Days']);
        $this->makeBid($role, $listing->id, $agent->id);

        $body = $this->actingAs($owner)->get($this->urlFor($role, $listing->id))->getContent();

        foreach (['timer-d', 'timer-h', 'timer.jquery', 'data-expiration', 'countdown: true',
                  'Bidding Ended', 'Bidding Period Length'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "{$role}: retired timer markup must not return.");
        }
    }

    /**
     * A competing agent still sees only their own proposal.
     *
     * @dataProvider roles
     */
    public function test_competing_proposal_privacy_holds(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $mine    = User::factory()->create(['user_type' => 'agent']);
        $rival   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->makeListing($role, $owner->id);

        $myBid    = $this->makeBid($role, $listing->id, $mine->id);
        $rivalBid = $this->makeBid($role, $listing->id, $rival->id);

        $served = $this->actingAs($mine)->get($this->urlFor($role, $listing->id))
            ->viewData('auction')->bids->pluck('id')->all();

        $this->assertContains($myBid->id, $served, "{$role}: control — the agent sees their own proposal.");
        $this->assertNotContains($rivalBid->id, $served, "{$role}: a competing proposal must stay invisible.");
    }

    /**
     * One primary proposal CTA per rendered state — never a desktop/mobile duplicate pair.
     *
     * @dataProvider roles
     */
    public function test_exactly_one_primary_proposal_cta_for_an_eligible_agent(string $role): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id);

        $body = $this->actingAs($agent)->get($this->urlFor($role, $listing->id))->getContent();

        $this->assertSame(
            1,
            substr_count($body, '>Bid Now<'),
            "{$role}: exactly one primary proposal CTA, not one per breakpoint."
        );
    }
}
