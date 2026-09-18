<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Batch 3 on the real public listing pages: what the typed-question box ships, and what it
 * structurally cannot do.
 *
 * THE PAGE IS THE SECURITY BOUNDARY, and these tests exist because that claim is only worth
 * something if it is checked against a listing that deliberately CANNOT answer most of the
 * catalog. A listing with every fact populated would prove nothing: every alias would be
 * present and the test would pass whether or not the boundary worked.
 *
 * TYPED TEXT NEVER REACHES LARAVEL, and the proof is structural rather than behavioural.
 * There is no <form> in the card, so there is nothing for Enter to submit; the input carries
 * no `name`, so a form could not carry it even if one appeared; and no fetch, XHR or endpoint
 * string exists in the card or in the matcher. Nothing has to be remembered at runtime.
 */
class TypedQuestionCardBatch3Test extends TestCase
{
    use DatabaseTransactions;

    private const MATCHER = 'public/js/ask-ai/deterministic-question-matcher.js';

    /* ------------------------------------------------------------------ */

    private function seller(array $meta): SellerAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential'];
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '1 Batch3 Way',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Traditional');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function landlord(array $meta): LandlordAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential Property'];
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Batch3 Rental',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function buyer(array $meta): BuyerAgentAuction
    {
        // Ask AI resolves property type fail-closed: a search that names none gets only
        // the questions valid for every type. Every real criteria listing states one.
        $meta += ['property_type' => 'Residential'];
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id' => $user->id, 'title' => 'Batch3 Buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function tenant(array $meta): TenantAgentAuction
    {
        // Ask AI resolves property type fail-closed: a search that names none gets only
        // the questions valid for every type. Every real criteria listing states one.
        $meta += ['property_type' => 'Residential'];
        $listing = TenantAgentAuction::factory()->active()->create(['user_id' => User::factory()->create()->id]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    /** The Ask AI card region, bounded at its closing note. */
    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start, "No Ask AI card for {$role}.");
        $end = strpos($html, 'ask-ai-pq-note', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    private function page(string $route, int $id): string
    {
        return $this->get(route($route, $id))->assertOk()->getContent();
    }

    /** id => aliases, exactly as the card shipped them. */
    private function shippedVocabulary(string $card): array
    {
        preg_match_all(
            '/data-property-question="([a-z_0-9]+)"\s+data-question-aliases="([^"]*)"/',
            $card, $m, PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as [, $id, $aliases]) {
            $out[$id] = array_filter(explode('|', html_entity_decode($aliases, ENT_QUOTES)));
        }

        return $out;
    }

    /* ================================================================== */
    /* The security boundary                                               */
    /* ================================================================== */

    public function test_a_sparse_listing_ships_only_its_own_questions_vocabulary(): void
    {
        // Deliberately sparse: no taxes, no HOA, no bedrooms, and an INVALID flood zone.
        // Everything this listing cannot answer must contribute nothing typeable.
        $listing = $this->seller([
            'pool_needed'     => 'Yes',
            'zoning'          => 'RS-60',
            'flood_zone_code' => 'yes',   // the importer's old boolean; not a designation
        ]);
        $card  = $this->card($this->page('offer.listing.seller.view', $listing->id), 'seller');
        $vocab = $this->shippedVocabulary($card);

        $this->assertSame(['seller_pool', 'seller_zoning'], array_keys($vocab));

        $all = array_merge(...array_values($vocab));
        foreach ([
            'taxes', 'property taxes', 'how much are the taxes', 'tax amount',
            'hoa', 'hoa fee', 'hoa fees', 'association fee', 'how much is the hoa',
            'flood zone', 'flood', 'fema', 'what flood zone',
            'bedrooms', 'beds', 'bathrooms', 'square footage', 'appliances', 'cdd',
        ] as $absent) {
            $this->assertNotContains($absent, $all, "'{$absent}' shipped for a question this listing cannot answer.");
            $this->assertStringNotContainsString('"' . $absent . '"', $card);
        }
    }

    public function test_an_invalid_flood_zone_ships_no_flood_vocabulary(): void
    {
        foreach (['yes', 'Unknown', 'N/A', 'Zone AE', ''] as $stored) {
            $listing = $this->seller(['zoning' => 'RS-60', 'flood_zone_code' => $stored]);
            $card    = $this->card($this->page('offer.listing.seller.view', $listing->id), 'seller');

            $this->assertArrayNotHasKey('seller_flood_zone', $this->shippedVocabulary($card));
            $this->assertStringNotContainsString('flood zone|', $card, "flood aliases shipped for stored '{$stored}'.");
        }
    }

    public function test_the_card_ships_ids_display_questions_and_aliases_and_nothing_else(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes', 'flood_zone_code' => 'AE']);
        $card    = $this->card($this->page('offer.listing.seller.view', $listing->id), 'seller');

        // No catalog metadata leaks into the browser alongside the vocabulary.
        foreach (['source_path', 'source_kind', 'formatter', 'guards', 'narrower_of',
                  'category', 'supporting_paths', 'other_companion'] as $internal) {
            $this->assertStringNotContainsString($internal, $card, "'{$internal}' reached the browser.");
        }
    }

    /* ================================================================== */
    /* Per-role vocabulary                                                 */
    /* ================================================================== */

    public function test_seller_ships_hoa_taxes_and_flood_vocabulary_when_available(): void
    {
        $listing = $this->seller([
            'has_hoa' => 'Yes', 'association_fee_amount' => '250', 'association_fee_frequency' => 'Monthly',
            'annual_property_taxes' => '4200', 'tax_year' => '2025',
            'flood_zone_code' => 'AE', 'pool_needed' => 'Yes',
        ]);
        $vocab = $this->shippedVocabulary($this->card($this->page('offer.listing.seller.view', $listing->id), 'seller'));
        $all   = array_merge(...array_values($vocab));

        foreach (['how much is the hoa', 'hoa fees', 'association fees',
                  'how much are the taxes', 'property taxes', 'tax amount',
                  'flood zone', 'fema zone', 'what flood zone',
                  'does it have a pool', 'swimming pool'] as $expected) {
            $this->assertContains($expected, $all, "seller vocabulary is missing '{$expected}'.");
        }
    }

    public function test_landlord_ships_pets_hoa_and_flood_vocabulary_when_available(): void
    {
        $listing = $this->landlord([
            'pets' => 'Yes', 'has_hoa' => 'Yes', 'association_fee_amount' => '175',
            'association_fee_frequency' => 'Quarterly', 'flood_zone_code' => 'VE',
        ]);
        $all = array_merge(...array_values($this->shippedVocabulary(
            $this->card($this->page('offer.listing.landlord.view', $listing->id), 'landlord')
        )));

        foreach (['pets', 'are pets allowed', 'pet policy', 'how much is the hoa',
                  'flood zone', 'fema zone'] as $expected) {
            $this->assertContains($expected, $all, "landlord vocabulary is missing '{$expected}'.");
        }
    }

    public function test_buyer_ships_budget_and_area_vocabulary_but_nothing_private(): void
    {
        $listing = $this->buyer([
            'maximum_budget' => '450000', 'bedrooms' => '3',
            'cities' => json_encode(['Seminole']), 'counties' => json_encode(['Pinellas']),
            // Private, and stored: none of it may become typeable.
            'pre_approval_amount' => '525000', 'cash_budget' => '120000',
            'down_payment_amount' => '90000', 'number_occupant' => '4',
        ]);
        $card = $this->card($this->page('offer.listing.buyer.view', $listing->id), 'buyer');
        $all  = array_merge(...array_values($this->shippedVocabulary($card)));

        foreach (['budget', 'buyer budget', 'purchase budget', 'how much does the buyer want to spend',
                  'bedrooms', 'areas', 'cities'] as $expected) {
            $this->assertContains($expected, $all, "buyer vocabulary is missing '{$expected}'.");
        }
        foreach (['preapproval', 'pre approval', 'cash', 'down payment', 'credit', 'occupants', 'lender'] as $private) {
            $this->assertNotContains($private, $all, "'{$private}' is typeable on a buyer listing.");
        }
    }

    public function test_tenant_ships_rent_movein_and_furnishings_but_nothing_private(): void
    {
        $listing = $this->tenant([
            'budget' => '2500', 'tenant_require' => json_encode(['Furnished']),
            'move_in_date_earliest' => '2027-01-15', 'move_in_date_latest' => '2027-03-01',
            'pets' => 'Yes',
            // Private, and stored.
            'monthly_income' => '7600', 'credit_score_range' => '700-749',
            'service_animal' => 'Yes', 'accessibility_requirements' => 'Ground floor',
        ]);
        $card = $this->card($this->page('offer.listing.tenant.view', $listing->id), 'tenant');
        $all  = array_merge(...array_values($this->shippedVocabulary($card)));

        foreach (['rent', 'max rent', 'maximum rent', 'rent budget',
                  'move in', 'move in date', 'when do they want to move',
                  'furnished', 'turnkey', 'pet friendly'] as $expected) {
            $this->assertContains($expected, $all, "tenant vocabulary is missing '{$expected}'.");
        }
        foreach (['income', 'salary', 'deposit', 'credit', 'service animal', 'support animal',
                  'accessibility', 'disability', 'eviction'] as $private) {
            $this->assertNotContains($private, $all, "'{$private}' is typeable on a tenant listing.");
        }
    }

    /* ================================================================== */
    /* Nothing can reach the server                                        */
    /* ================================================================== */

    /**
     * @dataProvider everyRolePage
     */
    public function test_the_card_carries_no_way_to_submit_typed_text(string $role, string $routeName, string $factory): void
    {
        $listing = $this->{$factory}(['pool_needed' => 'Yes', 'bedrooms' => '3']);
        $card    = $this->card($this->page($routeName, $listing->id), $role);

        $this->assertStringContainsString('data-ask-ai-ask-input', $card);

        foreach (['<form', 'action=', 'method=', 'fetch(', 'XMLHttpRequest', 'navigator.sendBeacon',
                  'ask-ai/listing-question', 'api/ask-ai/ask', 'agent-ai/', 'wire:', '<script'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $card, "{$role} card contains '{$forbidden}'.");
        }

        // A nameless input outside a form cannot be submitted by any means.
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $card,
            "{$role}: the typed-question input must carry no name attribute.");
        $this->assertMatchesRegularExpression('/<button\b[^>]*type="button"[^>]*data-ask-ai-ask-button/', $card,
            "{$role}: the button must be type=button so it can never submit.");
    }

    public static function everyRolePage(): array
    {
        return [
            'seller'   => ['seller', 'offer.listing.seller.view', 'seller'],
            'landlord' => ['landlord', 'offer.listing.landlord.view', 'landlord'],
            'buyer'    => ['buyer', 'offer.listing.buyer.view', 'buyer'],
            'tenant'   => ['tenant', 'offer.listing.tenant.view', 'tenant'],
        ];
    }

    public function test_the_matcher_asset_reaches_no_network_and_no_storage(): void
    {
        $source = file_get_contents(base_path(self::MATCHER));
        $this->assertNotFalse($source);

        foreach ([
            'fetch(', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'EventSource',
            '$.ajax', 'axios', 'import ', 'require(',
            'localStorage', 'sessionStorage', 'document.cookie', 'indexedDB',
            'location.href', 'location.assign', 'window.open', 'history.pushState',
            'console.log', 'dataLayer', 'gtag', 'analytics',
            // The endpoints themselves, not the card's own `data-ask-ai-*` attribute
            // namespace, which the matcher must of course be able to select on.
            'ask-ai/listing-question', 'api/ask-ai', 'agent-ai/', 'openai',
        ] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source,
                "The matcher contains '{$forbidden}'.");
        }
    }

    public function test_no_server_route_or_handler_was_added_for_typed_questions(): void
    {
        // The feature adds no endpoint at all: nothing in routes/ mentions it, and the
        // matcher is a static asset rather than anything Laravel dispatches.
        $routes = file_get_contents(base_path('routes/web.php'));
        foreach (['ask-ai-ask', 'deterministic-question', 'typed-question', 'question-match'] as $needle) {
            $this->assertStringNotContainsString($needle, $routes, "routes/web.php gained '{$needle}'.");
        }

        $this->assertFileExists(base_path(self::MATCHER));
        $this->assertDirectoryDoesNotExist(base_path('app/Http/Controllers/AskAi/TypedQuestion'));
    }

    /* ================================================================== */
    /* Owner experience                                                    */
    /* ================================================================== */

    public function test_the_owner_keeps_their_existing_ask_ai_modal_alongside_the_typed_box(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes']);
        $owner   = User::find($listing->user_id);

        $html = $this->actingAs($owner)->get(route('offer.listing.seller.view', $listing->id))->assertOk()->getContent();

        // Both exist, and they are separate: the typed box lives in the deterministic card
        // and never routes an unmatched question into the owner's free-text modal.
        $this->assertStringContainsString('data-ask-ai-ask-input', $html);
        $this->assertStringContainsString('data-bs-target="#solAiModal"', $html);

        $card = $this->card($html, 'seller');
        $this->assertStringNotContainsString('solAiModal', $card,
            'The typed box must not be wired to the owner AI modal.');
    }

    /* ================================================================== */
    /* Accessibility                                                       */
    /* ================================================================== */

    public function test_the_typed_box_is_labelled_and_reports_status_politely(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes']);
        $card    = $this->card($this->page('offer.listing.seller.view', $listing->id), 'seller');

        $this->assertMatchesRegularExpression('/<label[^>]*for="sol-ask-ai-ask-input"/', $card);
        $this->assertMatchesRegularExpression('/<input[^>]*id="sol-ask-ai-ask-input"/', $card);
        $this->assertMatchesRegularExpression('/role="status"[^>]*aria-live="polite"|aria-live="polite"[^>]*role="status"/', $card);

        // Native disclosure semantics are untouched — still real <details>/<summary>.
        $this->assertStringContainsString('<details', $card);
        $this->assertStringContainsString('<summary', $card);
    }
}
