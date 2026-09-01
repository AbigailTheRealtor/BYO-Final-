<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M7.2 — the flag-OFF guarantee, and the one place it is written down that it CHANGED.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * THE DEVIATION, RECORDED DELIBERATELY.
 *
 * M7.1 held that flag OFF is byte-identical LITERALLY, and proved it by rendering all four roles
 * before and after and comparing byte counts. M7.2 does NOT hold that, and the difference is not
 * an oversight.
 *
 * M7.1 changed a class string. It moved no markup, so nothing could shift. M7.2 is a component
 * EXTRACTION: eight sections move inside x-hire-agent.detail-section, and markup written at the
 * caller's indentation is now written at column 0 of the component file. The rendered page shifts
 * by that difference. Measured on the landlord page: the legacy section header emits at 8 spaces
 * where it used to emit at 16. No tag, attribute, id or text changes — only whitespace BETWEEN
 * elements, which has no layout effect whatsoever.
 *
 * Two ways to keep literal byte identity were considered and rejected on the record:
 *
 *   1. Inline the conditional card markup at all eight call sites, so nothing moves. This keeps
 *      the bytes and loses the component: unbalanced <div> open/close in separate @if branches,
 *      eight times over, in a 3,000-line view, with card chrome hand-rolled instead of reusing
 *      x-viho.card.
 *   2. An `indent` prop paying back each caller's original column depth. This keeps the bytes and
 *      encodes whitespace into the component's public API — a value that differs per call site
 *      (Property Details sits at 20, the rest at 8) and that a reader must maintain by hand.
 *
 * Both trade component quality for whitespace. The decision was to keep the component and hold the
 * guarantee that actually has meaning: the flag-off DOM is IDENTICAL — same elements, same order,
 * same attributes, same ids, same text, same counts.
 *
 * M7.1's own literal-byte assertions are untouched and still apply to what M7.1 shipped;
 * HireAgentDetailShellLayoutTest is not modified by this milestone.
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 *
 * The full decision, the rejected alternatives and the verification method are written up in
 * docs/investigations/hire-agent-m7-2-flag-off-guarantee.md.
 *
 * WHAT THIS FILE CAN AND CANNOT PROVE. A before/after comparison needs both trees and a test only
 * ever sees one, so the equivalence itself was verified out-of-band against the pre-change tree:
 * the view from 4d9c74961 was prepended to the view finder and rendered in the same process as the
 * post-change view, against the same fixture, and the canonicalised DOM compared. Identical for
 * the owner and for a guest. That harness was confirmed sensitive by injecting a single attribute
 * into the baseline and watching it fail.
 * What lives here is the regression net that keeps it true: the legacy DOM shape is pinned exactly
 * enough that any M7.2 markup leaking into the flag-off branch fails an assertion rather than
 * shipping. The recorded title lists below ARE the pre-change values, read off the pre-change
 * render.
 */
class HireAgentSectionCardDomEquivalenceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Every `viho-section-header-title` the page emits, in document order, as measured on the
     * PRE-CHANGE tree with the flag off.
     *
     * The first entry in each list is the monolithic listing card's own heading, not a section —
     * `x-viho.card` renders its title through the same class. The rest are the sections M7.2
     * converts. Order is asserted, not just membership: a section that moved would still satisfy a
     * set comparison and would still be a regression.
     *
     * Recorded as text, matching what a reader sees on the page.
     *
     * TWO ENTRIES WERE DELETED FROM EVERY ROLE, AND THAT IS NOT A RELAXATION OF THE PIN. 'Services:'
     * and 'Broker Compensation & Agency Agreement Terms' sat between Terms and Additional Details,
     * and after Representation, in all four lists. They are negotiation terms an agent proposes on
     * a bid, not listing detail, so the sections were removed from the page — including from this
     * flag-off branch, because a rule about what a listing IS cannot be conditional on a rollout
     * switch. Every surviving heading keeps its recorded text and its recorded position; the
     * deletions close the gaps without moving anything around them.
     *
     * The absence is asserted positively elsewhere rather than only implied by these lists —
     * see HireAgentListingBidSeparationTest and HireAgentDetailFrameworkTest.
     */
    private const FLAG_OFF_HEADINGS = [
        'seller' => [
            'Listing Details:',
            'Property Details:',
            'Sale Terms',
            'Additional Details:',
            'Referral & Cooperation Terms',
            'Seller Info',
        ],
        'buyer' => [
            'Listing Details:',
            'Property Preferences:',
            'Purchasing Terms:',
            'Additional Details:',
            'Referral & Cooperation Terms',
            "Buyer's Info",
        ],
        'landlord' => [
            'Listing Details:',
            'Property Details:',
            'Leasing Terms:',
            'Additional Details:',
            'Representation Preferences & Compatibility:',
            'Referral & Cooperation Terms',
            "Landlord's Info",
        ],
        'tenant' => [
            'Listing Details:',
            'Property Preferences:',
            'Leasing Terms:',
            'Pre-Screening:',
            'Additional Details:',
            'Referral & Cooperation Terms',
            "Tenant's Info",
        ],
    ];

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0: class-string, 1: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view'],
        };
    }

    /**
     * Every guard that gates a section is satisfied, so all of them render.
     *
     * A fixture that left sections empty would let this suite pass while proving nothing about the
     * sections it exists to protect.
     */
    private function richMeta(): array
    {
        return [
            'listing_title'           => 'Listing title',
            'working_with_agent'      => 'Not currently represented',
            'desired_agent_hire_date' => '2026-09-01',
            'expiration_date'         => now()->addDays(30)->toDateTimeString(),
            'auction_type'            => 'Traditional',
            'budget'                  => '654321',
            'property_type'           => 'Residential Property',

            'services'                => json_encode(['List the property on the local Multiple Listing Service (MLS)']),
            'other_services'          => 'Weekend open houses',
            'additional_details'      => 'Prefers evening showings.',

            'compatibility_preferences' => json_encode([
                'landlord_specific' => [
                    // 'tenant_type_preference' was here. It is retired (Fair Housing), and this
                    // fixture is a Residential Property listing — the exact combination the
                    // retirement exists to make impossible. Replaced with a current public row so
                    // the section still has content to compare DOM for.
                    'primary_leasing_goal'      => 'Maximize rent',
                    'lease_duration_preference' => '1 Year',
                ],
            ]),

            'purchase_fee_type'                  => 'Flat Fee',
            'purchase_fee_flat'                  => '2500',
            'tenant_broker_commission_structure' => 'Percentage',
            'broker_fee_timing'                  => 'At lease signing',

            'referral_percentage'                => '25',
        ];
    }

    private function makeListing(string $role, int $ownerId): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' section card listing',
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

        // Seller's controller redirects anything that looks like an Offer Listing.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ($this->richMeta() as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function renderAsOwner(string $role): string
    {
        [, $route] = $this->wiringFor($role);

        $owner = User::factory()->create(['user_type' => 'seller']);

        return $this->actingAs($owner)
            ->get(route($route, $this->makeListing($role, $owner->id)->id))
            ->assertOk()
            ->getContent();
    }

    private function disableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);
    }

    // ── DOM helpers (mirrors HireAgentShellStructureTest) ────────────────────

    private function xpath(string $html): DOMXPath
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    /** Whole-token class match — `contains(@class, 'viho-card')` also matches viho-card-head. */
    private function classQuery(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }

    /**
     * Section headings in document order, as TEXT.
     *
     * Decoded rather than raw HTML on purpose. The escaped form round-trips differently through
     * DOMDocument than it appears in the response body (`&#039;` comes back as `'`), so asserting
     * on escaped markup would pin an artefact of the parser rather than what the page says. The
     * decorative icon is an empty element and contributes no text, so it drops out for free.
     */
    private function headings(DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query($this->classQuery('viho-section-header-title')) as $node) {
            $out[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
        }

        return $out;
    }

    // ── The guarantee ────────────────────────────────────────────────────────

    /**
     * With the flag off, the page still emits ONE listing card per role.
     *
     * This is the assertion M7.2 is most able to break: the section component rendering its card
     * branch in the legacy path would show up here as nine cards instead of one, on the role that
     * is not even the pilot.
     *
     * @dataProvider roles
     */
    public function test_flag_off_main_column_holds_exactly_one_listing_card(string $role): void
    {
        $this->disableRedesign();

        $x = $this->xpath($this->renderAsOwner($role));

        $this->assertSame(
            1,
            $x->query($this->classQuery('viho-card'))->length,
            "{$role}: flag off must render the single legacy listing card, not section cards."
        );
    }

    /**
     * With the flag off, no section anchor id is emitted at all — by the card or by anything else.
     *
     * Before M7.2 these ids lived on `<span class="viho-section-nav-target">` elements that were
     * themselves inside `@if ($hlaDetailRedesign)`. After M7.2 they live on the cards. Either way
     * the legacy render must carry none, and an id appearing here means the redesign leaked.
     *
     * @dataProvider roles
     */
    public function test_flag_off_emits_no_section_anchor_ids(string $role): void
    {
        $this->disableRedesign();

        $x = $this->xpath($this->renderAsOwner($role));

        $this->assertSame(
            0,
            $x->query('//*[starts-with(@id, "hla-section-")]')->length,
            "{$role}: flag off must emit no hla-section-* anchor."
        );

        $this->assertSame(
            0,
            $x->query($this->classQuery('viho-section-nav-target'))->length,
            "{$role}: flag off must emit no nav target."
        );
    }

    /**
     * The headings, in order, are exactly what the pre-change tree emitted.
     *
     * This is the substance of "the DOM is equivalent". A section that vanished, gained a
     * duplicate, changed wording, or moved relative to its neighbours fails here — and each of
     * those is a way a mechanical extraction across eight sections can go wrong without being
     * visible in a spot check.
     *
     * @dataProvider roles
     */
    public function test_flag_off_headings_match_the_pre_change_render(string $role): void
    {
        $this->disableRedesign();

        $this->assertSame(
            self::FLAG_OFF_HEADINGS[$role],
            $this->headings($this->xpath($this->renderAsOwner($role))),
            "{$role}: flag-off headings drifted from the pre-change render."
        );
    }

    /**
     * The legacy separators survive.
     *
     * M7.2 makes each section-boundary `<hr>` conditional so the card gap replaces it when the
     * redesign is on. With the flag off every one of them must still render.
     *
     * @dataProvider roles
     */
    public function test_flag_off_keeps_its_content_separators(string $role): void
    {
        $this->disableRedesign();

        $x = $this->xpath($this->renderAsOwner($role));

        $this->assertGreaterThan(
            0,
            $x->query($this->classQuery('leftCol') . '//hr')->length,
            "{$role}: flag off must keep the main column's separators."
        );
    }

    /**
     * The section component contributes nothing of its own to the legacy branch.
     *
     * Not a style rule — a wrapper element would change the DOM the flag-off page renders, which
     * is precisely what this suite exists to forbid. Asserted against the component source because
     * a marker class that never renders cannot be caught by rendering.
     */
    public function test_the_section_component_adds_no_wrapper_in_the_legacy_branch(): void
    {
        $source = file_get_contents(
            resource_path('views/components/hire-agent/detail-section.blade.php')
        );

        $this->assertNotFalse($source, 'The section component must exist.');

        // Everything after @else is the legacy branch.
        $legacy = substr($source, strpos($source, '@else'));

        $this->assertStringNotContainsString('<div', $legacy, 'Legacy branch must add no element.');
        $this->assertStringNotContainsString('class=', $legacy, 'Legacy branch must add no class.');
        $this->assertStringContainsString('x-viho.section-header', $legacy, 'Legacy branch renders the original header.');
    }

    /**
     * The component decides nothing about who may see a section.
     *
     * Visibility is the caller's, always — compensation is wrapped in Auth::check() and
     * $hasLandlordBrokerCompData in the view, and this component sits inside both. A component
     * that could read a user would be a second opinion about visibility living in presentation.
     */
    public function test_the_section_component_makes_no_authorization_decision(): void
    {
        $source = file_get_contents(
            resource_path('views/components/hire-agent/detail-section.blade.php')
        );

        // The executable half only. The docblock above it NAMES the caller's guards
        // (Auth::check(), $hasLandlordBrokerCompData) in order to explain that this component sits
        // inside them and never replaces them — prose describing a rule must not trip the rule.
        $source = substr($source, strpos($source, '--}}'));

        foreach (['Auth::', 'auth()', 'user()', 'can(', 'HireAgentProposalAccess', 'config('] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "The section component must not contain [{$forbidden}]."
            );
        }
    }
}
