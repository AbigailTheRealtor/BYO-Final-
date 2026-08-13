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
use App\Support\HireAgent\HireAgentDetailSections;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * THE LINE BETWEEN A LISTING AND A NEGOTIATION, held from both sides.
 *
 * THE RULE. Services and Broker Compensation are negotiation terms. An agent OFFERS them in a
 * proposal and the client accepts, rejects or counters. They are not listing detail — a listing
 * states what is wanted, where, on what terms and how the client wants to be worked with — so they
 * are not sections of the Hire Agent detail page for any role, at any audience tier, under any
 * state of the redesign flag.
 *
 * WHY BOTH HALVES ARE IN ONE FILE, and why that is the point rather than a convenience. A test
 * that only asserted absence would pass just as happily if the whole subject had been deleted from
 * the application — which is not the rule and would be a serious regression: without proposed
 * services and compensation there is nothing to accept, reject or counter. So every absence
 * assertion here has a presence assertion beside it, on the same data, in the same run. The rule
 * is a SEPARATION, and a separation needs two sides to be a separation at all.
 *
 * WHAT MADE THIS NECESSARY. The two bodies of data have nearly the same names — the listing's
 * `$auction->get->services` and `$auction->get->commission_structure` against a bid's own offered
 * services and compensation — and conflating them is exactly how one of them ended up public.
 * Services rendered to anonymous visitors on the landlord view; Broker Compensation sat behind a
 * bare `Auth::check()` that admitted any logged-in stranger. Both are gone from the listing view
 * and untouched on the proposal.
 *
 * THE FIXTURES POPULATE WHAT MUST NOT APPEAR. Every listing below carries services and
 * compensation meta. An absence assertion against an empty listing proves nothing, and that is not
 * a hypothetical: the visual QA that preceded this change checked two listings that happened to
 * hold no services at all and read clean, while a populated one leaked a 2,000-pixel Services
 * section to a guest.
 *
 * @see config/hire_agent_sections.php — the registry, and the reversal that removed both sections.
 * @see HireAgentSectionRegistryTest — the same rule asserted against the registry rather than a page.
 */
class HireAgentListingBidSeparationTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /** The anchors that must never render, and the headings that name them to a reader. */
    private const RETIRED_ANCHORS  = ['hla-section-services', 'hla-section-compensation'];
    /**
     * Written WITHOUT the trailing colon, because sectionHeadings() strips it.
     *
     * The legacy branch renders "Services:" and the redesigned branch renders "Services" —
     * x-hire-agent.detail-section rtrims the colon on the way into the card title, since a heading
     * on a card header band is a title rather than a label. Normalising in one place keeps this
     * list about the sections rather than about which branch produced them.
     */
    private const RETIRED_HEADINGS = ['Services', 'Broker Compensation & Agency Agreement Terms'];

    // ── Wiring ───────────────────────────────────────────────────────────────

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
     * A listing carrying BOTH negotiation subjects, plus enough ordinary content to render.
     *
     * The services and compensation keys are the ones the removed sections read. They are written
     * deliberately: this file's absence assertions are only meaningful while the data that used to
     * produce those sections is present.
     */
    private function makeListing(string $role, int $ownerId): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' separation listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];

        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Separation Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Separation Street');
        }

        foreach ([
            // Ordinary listing content, so the page has real sections to render.
            'listing_title'            => ucfirst($role) . ' separation listing',
            'auction_type'             => 'Traditional',
            'property_type'            => 'Residential Property',
            'cities'                   => json_encode(['Tampa, FL']),
            'first_name'               => 'Abby',
            'additional_details'       => 'Evenings only.',
            'referral_percentage'      => '25',

            // THE SUBJECTS UNDER TEST — the LISTING's own answers, which must not surface.
            'services'                 => json_encode(['List the property on the local Multiple Listing Service (MLS)']),
            'other_services'           => json_encode(['Weekly written updates']),
            'client_custom_services'   => json_encode(['Handle the inspection scheduling']),
            'commission_structure'     => 'Percentage of Sale Price',
            'purchase_fee_type'        => 'Flat Fee',
            'protection_period'        => '90 days',
            'brokerage_relationship'   => 'Single Agent',
            'agency_agreement_timeframe' => '6 months',
            'additional_details_broker'  => 'Negotiable on volume.',
        ] as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /** An agent's proposal, carrying its own offered services and compensation. */
    private function makeBid(string $role, int $listingId, int $agentId): Model
    {
        [, $bidClass, $fk] = $this->wiringFor($role);

        $bid = $bidClass::forceCreate([$fk => $listingId, 'user_id' => $agentId]);

        if (in_array($role, ['seller', 'buyer'], true)) {
            $bid->brokerage = '250.00';
            $bid->save();
        }

        $bid->saveMeta('purchase_fee_type', 'Flat Fee');
        $bid->saveMeta('purchase_fee_flat', '250.00');
        $bid->saveMeta('services', json_encode(['List the property on the local Multiple Listing Service (MLS)']));

        return $bid;
    }

    private function urlFor(string $role, int $id): string
    {
        [, , , $route] = $this->wiringFor($role);

        return route($route, $id);
    }

    private function enableRedesign(bool $on): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => $on,
            'hire_agent_detail.redesign_roles'   => self::ROLES,
        ]);
    }

    /** The listing page, as one of four viewer kinds. */
    private function renderAs(string $role, string $viewer): string
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->makeListing($role, $owner->id);

        $bidder = User::factory()->create(['user_type' => 'agent']);
        $this->makeBid($role, $listing->id, $bidder->id);

        $request = match ($viewer) {
            'owner' => $this->actingAs($owner),

            // An agent with a submitted proposal: the widest tier, and the one a surviving section
            // would still be reachable from.
            'qualifying_agent' => $this->actingAs($bidder),

            // An agent user_type with no relationship to this listing — the discriminating case
            // for "being an agent is not sufficient".
            'stranger' => $this->actingAs(User::factory()->create(['user_type' => 'agent'])),

            // Guards must be CLEARED, not merely omitted: actingAs persists for the whole test
            // method, so an un-cleared guest silently renders as whoever came before it.
            'guest' => tap($this, function () {
                auth()->logout();
                $this->app->get('auth')->forgetGuards();
                $this->assertGuest();
            }),
        };

        $response = $request->get($this->urlFor($role, $listing->id));
        $response->assertOk();

        return $response->getContent();
    }

    /**
     * Every SECTION HEADING the page emits, in document order.
     *
     * READ OFF `viho-section-header-title`, WHICH BOTH BRANCHES USE — x-viho.card renders it with
     * the redesign on, x-viho.section-header with it off — so one extractor serves every role and
     * both flag states. That is also what makes this the right instrument for legacy roles, which
     * emit no `hla-section-*` anchors at all.
     *
     * A WHOLE-PAGE TEXT SEARCH WAS TRIED FIRST AND WAS WRONG. The proposal cards below the listing
     * render "Offered Services:" — an agent's own offer, which is exactly what this file asserts
     * must survive — and that string contains "Services:". Searching the body therefore failed on
     * the presence of the thing the separation is meant to preserve. Headings only.
     *
     * Entities are decoded because Blade escapes `&` and `'`, and these headings contain both.
     */
    private function sectionHeadings(string $html): array
    {
        // PARSED, NOT PATTERN-MATCHED. A regex ending at the first closing tag captures only the
        // decorative <i> icon these headings open with, which silently truncated every heading
        // that had one and left the roles without icons looking correct.
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $headings = [];

        foreach ((new DOMXPath($doc))->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " viho-section-header-title ")]'
        ) as $node) {
            // Trailing colon stripped: see the note on RETIRED_HEADINGS.
            $headings[] = rtrim(trim(preg_replace('/\s+/', ' ', $node->textContent)), ':');
        }

        return $headings;
    }

    // ── 1. The listing view, every role × every audience ─────────────────────

    /**
     * NO ROLE, AT NO TIER, RENDERS EITHER SECTION — with the redesign ON.
     *
     * Sixteen combinations in one test because the rule is uniform: it does not bend by role and it
     * does not bend by audience, and asserting it per-role would let three roles pass while a
     * fourth leaked.
     */
    public function test_no_listing_view_exposes_negotiation_terms_at_any_tier(): void
    {
        $this->enableRedesign(true);

        foreach (self::ROLES as $role) {
            foreach (['guest', 'stranger', 'owner', 'qualifying_agent'] as $viewer) {
                $html = $this->renderAs($role, $viewer);

                foreach (self::RETIRED_ANCHORS as $anchor) {
                    $this->assertStringNotContainsString(
                        'id="' . $anchor . '"',
                        $html,
                        "{$role}/{$viewer}: rendered the retired [{$anchor}] listing card."
                    );
                    $this->assertStringNotContainsString(
                        'href="#' . $anchor . '"',
                        $html,
                        "{$role}/{$viewer}: the nav named the retired [{$anchor}]."
                    );
                }
            }
        }
    }

    /**
     * The same rule with the redesign OFF.
     *
     * SEPARATE FROM THE TEST ABOVE ON PURPOSE. Three of the four roles render their legacy branch
     * in production, so a rule that only held in the redesigned branch would leave most traffic
     * unchanged — and the legacy branch is where both sections rendered most widely, Services to
     * anonymous visitors and compensation to any authenticated user. A statement about what a
     * listing IS cannot be conditional on a rollout switch.
     */
    public function test_the_flag_off_listing_view_exposes_no_negotiation_terms_either(): void
    {
        $this->enableRedesign(false);

        foreach (self::ROLES as $role) {
            foreach (['guest', 'owner'] as $viewer) {
                $headings = $this->sectionHeadings($this->renderAs($role, $viewer));

                foreach ($headings as $heading) {
                    foreach (self::RETIRED_HEADINGS as $retired) {
                        $this->assertStringNotContainsString(
                            $retired,
                            $heading,
                            "{$role}/{$viewer}: the flag-off page still heads a [{$retired}] section."
                        );
                    }
                }

                // Positive control, per role and per viewer: the legacy page rendered sections at
                // all, so the absences above are a removal rather than an empty document.
                $this->assertNotEmpty(
                    $headings,
                    "{$role}/{$viewer}: the flag-off page rendered no sections, so it proves nothing."
                );
            }
        }
    }

    /**
     * The absence is not a blank page.
     *
     * The positive control for both tests above: the same fixtures render their real sections. A
     * page that had stopped rendering entirely would satisfy every assertion above and none here.
     */
    public function test_the_listing_view_still_renders_its_real_sections(): void
    {
        $this->enableRedesign(true);

        foreach (self::ROLES as $role) {
            $headings = $this->sectionHeadings($this->renderAs($role, 'owner'));

            $this->assertContains(
                'Listing Details',
                $headings,
                "{$role}: the listing rendered no Listing Details section, so the absences prove nothing."
            );

            // ASSERTED ON HEADINGS RATHER THAN ANCHORS, because only the roles on the redesign
            // allowlist emit `hla-section-*` ids. Seller and tenant render their legacy branch
            // whatever this flag says — their views were never migrated — so an anchor assertion
            // would fail for them for a reason that has nothing to do with this rule.
            $this->assertGreaterThanOrEqual(
                3,
                count($headings),
                "{$role}: the listing rendered almost nothing."
            );
        }
    }

    // ── 2. The registry, which is where the sections stopped existing ────────

    /** Neither id is scoped to any role, so neither can resolve for any audience. */
    public function test_neither_subject_is_a_section_in_the_registry(): void
    {
        $sections = app(HireAgentDetailSections::class);

        foreach (['services', 'compensation'] as $key) {
            $this->assertSame(
                [],
                $sections->rolesFor($key),
                "[{$key}] is a negotiation term and must not be scoped to any role."
            );

            foreach (self::ROLES as $role) {
                foreach (['public', 'owner', 'agent'] as $audience) {
                    $this->assertArrayNotHasKey(
                        $key,
                        $sections->inScopeFor($role, $audience),
                        "{$role}/{$audience}: [{$key}] is in scope and must not be."
                    );
                }
            }
        }
    }

    // ── 3. The other side of the line: the proposal keeps both ───────────────

    /**
     * THE HALF THAT MAKES THIS A SEPARATION RATHER THAN A DELETION.
     *
     * An agent's proposal still carries its own services and compensation, and the client still
     * reads them — that is the surface where "accept, reject or counter" happens. Asserted for the
     * listing OWNER, who is the person the proposals were written for.
     *
     * ASSERTED ON THE PROPOSAL PARTIALS AT SOURCE, not on the rendered page. The per-bid cards sit
     * behind a proposal console whose rendering depends on bid state, acceptance, counter-offers
     * and HireAgentProposalAccess — machinery this file has no business reproducing, and whose
     * behaviour HireAgentProposalConsoleTest and HireAgentProposalAccessTest already own. What this
     * needs to establish is narrower and sufficient: the proposal surface still carries both
     * subjects, so removing them from the listing did not remove them from the negotiation.
     */
    public function test_the_proposal_surface_still_carries_services_and_compensation(): void
    {
        $partials = [
            'resources/views/hire_landlord_agent/partials/proposal_card.blade.php',
            'resources/views/partials/bid_detail_body/buyer.blade.php',
            'resources/views/partials/bid_detail_body/seller.blade.php',
            'resources/views/partials/bid_detail_body/landlord.blade.php',
        ];

        foreach ($partials as $rel) {
            $path = base_path($rel);

            $this->assertFileExists($path, "The proposal partial [{$rel}] must not be removed.");

            $src = file_get_contents($path);

            $this->assertMatchesRegularExpression(
                '/service/i',
                $src,
                "[{$rel}] no longer mentions services — the negotiation lost half its terms."
            );
            $this->assertMatchesRegularExpression(
                '/commission|compensation|fee/i',
                $src,
                "[{$rel}] no longer mentions compensation — the negotiation lost half its terms."
            );
        }
    }

    /**
     * A submitted proposal's own services and compensation survive the round trip.
     *
     * The data-layer complement to the source assertion above: the listing lost its copies, and a
     * bid's are read back unchanged. Role-neutral, because all four bid models store proposal meta
     * the same way.
     */
    public function test_a_proposal_keeps_its_own_services_and_compensation_meta(): void
    {
        foreach (self::ROLES as $role) {
            $owner   = User::factory()->create(['user_type' => 'seller']);
            $agent   = User::factory()->create(['user_type' => 'agent']);
            $listing = $this->makeListing($role, $owner->id);

            $bid = $this->makeBid($role, $listing->id, $agent->id)->fresh();

            $this->assertSame(
                'Flat Fee',
                $bid->get->purchase_fee_type,
                "{$role}: the proposal lost its compensation terms."
            );
            $this->assertSame(
                '250.00',
                $bid->get->purchase_fee_flat,
                "{$role}: the proposal lost its compensation amount."
            );
            $this->assertNotEmpty(
                $bid->get->services,
                "{$role}: the proposal lost its offered services."
            );
        }
    }
}
