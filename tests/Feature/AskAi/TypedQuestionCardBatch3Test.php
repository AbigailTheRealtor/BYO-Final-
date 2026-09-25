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
 * The Ask AI question VOCABULARY on the real public listing pages, and what the surface
 * structurally cannot do.
 *
 * HISTORY. Batch 3 shipped a typed-question box on the card; it is retired. Ask AI is now
 * SELECTION-BASED (2026-09-25): the modal lists every answerable question with its search
 * terms (the question's wording plus the same deterministic aliases Batch 3 built), and
 * "Search questions..." only FILTERS that list. The class name is kept for continuity; the
 * vocabulary is now read from the modal's View-all list.
 *
 * THE PAGE IS THE SECURITY BOUNDARY, and these tests exist because that claim is only worth
 * something if it is checked against a listing that deliberately CANNOT answer most of the
 * catalog. A listing with every fact populated would prove nothing: every alias would be
 * present and the test would pass whether or not the boundary worked.
 *
 * TYPED TEXT NEVER REACHES LARAVEL, and the proof is structural rather than behavioural.
 * There is no <form> in the card or the modal, so there is nothing for Enter to submit; the
 * one input (the search filter) carries no `name`; every button is type=button; and no
 * fetch, XHR or endpoint string exists in either region or in question-picker.js.
 */
class TypedQuestionCardBatch3Test extends TestCase
{
    use DatabaseTransactions;

    private const PICKER = 'public/js/ask-ai/question-picker.js';

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

    /** The Ask AI modal body, bounded at its disclaimer. */
    private function modal(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-picker="' . $role . '"');
        $this->assertNotFalse($start, "No Ask AI modal for {$role}.");
        $end = strpos($html, 'ask-ai-picker-disclaimer', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private function page(string $route, int $id): string
    {
        return $this->get(route($route, $id))->assertOk()->getContent();
    }

    /** id => search terms, exactly as the modal's View-all list ships them. */
    private function shippedVocabulary(string $modal): array
    {
        preg_match_all(
            '/data-ask-ai-pick="([a-z_0-9]+)"[^>]*\sdata-ask-ai-search-terms="([^"]*)"/',
            $modal, $m, PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as [, $id, $terms]) {
            $out[$id] = array_values(array_filter(explode('|', html_entity_decode($terms, ENT_QUOTES))));
        }

        return $out;
    }

    /** The vocabulary of one role's page, read from its modal. */
    private function vocabularyOn(string $route, int $id, string $role): array
    {
        return $this->shippedVocabulary($this->modal($this->page($route, $id), $role));
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
        $card  = $this->modal($this->page('offer.listing.seller.view', $listing->id), 'seller');
        $vocab = $this->shippedVocabulary($card);

        // Zoning is stored but not asked: the Residential seller form does not collect it
        // (SP property-preferences :2156 / :2537 / :2796 — Commercial, Business, Vacant Land).
        $this->assertSame(['seller_pool'], array_keys($vocab));

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
            $card    = $this->modal($this->page('offer.listing.seller.view', $listing->id), 'seller');

            $this->assertArrayNotHasKey('seller_flood_zone', $this->shippedVocabulary($card));
            $this->assertStringNotContainsString('flood zone|', $card, "flood aliases shipped for stored '{$stored}'.");
        }
    }

    public function test_the_card_ships_ids_display_questions_and_aliases_and_nothing_else(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes', 'flood_zone_code' => 'AE']);
        $html    = $this->page('offer.listing.seller.view', $listing->id);

        // No catalog metadata leaks into the browser alongside the vocabulary — neither on
        // the card's featured list nor in the modal's complete list.
        foreach ([$this->card($html, 'seller'), $this->modal($html, 'seller')] as $region) {
            foreach (['source_path', 'source_kind', 'formatter', 'guards', 'narrower_of',
                      'category', 'supporting_paths', 'other_companion'] as $internal) {
                $this->assertStringNotContainsString($internal, $region, "'{$internal}' reached the browser.");
            }
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
        $vocab = $this->vocabularyOn('offer.listing.seller.view', $listing->id, 'seller');
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
        $all = array_merge(...array_values($this->vocabularyOn('offer.listing.landlord.view', $listing->id, 'landlord')));

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
        $all  = array_merge(...array_values($this->vocabularyOn('offer.listing.buyer.view', $listing->id, 'buyer')));

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
        $all  = array_merge(...array_values($this->vocabularyOn('offer.listing.tenant.view', $listing->id, 'tenant')));

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
    public function test_nothing_typed_can_be_submitted(string $role, string $routeName, string $factory): void
    {
        $listing = $this->{$factory}(['pool_needed' => 'Yes', 'bedrooms' => '3']);
        $html    = $this->page($routeName, $listing->id);

        foreach (['card' => $this->card($html, $role), 'modal' => $this->modal($html, $role)] as $where => $region) {
            foreach (['<form', 'action=', 'method=', 'fetch(', 'XMLHttpRequest', 'navigator.sendBeacon', '<textarea',
                      'ask-ai/listing-question', 'api/ask-ai/ask', 'agent-ai/', 'wire:', '<script'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $region, "{$role} {$where} contains '{$forbidden}'.");
            }
            // A nameless input outside a form cannot be submitted by any means.
            $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $region,
                "{$role} {$where}: no Ask AI input may carry a name attribute.");
            // Every button is type=button, so none can ever submit.
            preg_match_all('/<button\b[^>]*>/', $region, $buttons);
            foreach ($buttons[0] as $button) {
                $this->assertStringContainsString('type="button"', $button, "{$role} {$where}: {$button}");
            }
        }

        // The card has no input at all; the modal's ONLY input is the search filter.
        $this->assertDoesNotMatchRegularExpression('/<input\b/', $this->card($html, $role));
        preg_match_all('/<input\b[^>]*>/', $this->modal($html, $role), $inputs);
        $this->assertCount(1, $inputs[0], "{$role}: the modal must carry exactly one input.");
        $this->assertStringContainsString('data-ask-ai-picker-search', $inputs[0][0]);
        $this->assertStringContainsString('type="search"', $inputs[0][0]);

        // The retired typed box is gone.
        $this->assertStringNotContainsString('data-ask-ai-ask-input', $html);
        $this->assertStringNotContainsString('deterministic-question-matcher.js', $html);
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

    public function test_the_picker_asset_reaches_no_network_and_no_storage(): void
    {
        $source = file_get_contents(base_path(self::PICKER));
        $this->assertNotFalse($source);

        foreach ([
            'fetch(', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'EventSource',
            '$.ajax', 'axios', 'import ', 'require(',
            'localStorage', 'sessionStorage', 'document.cookie', 'indexedDB',
            'location.href', 'location.assign', 'window.open', 'history.pushState', '.submit(',
            'console.log', 'dataLayer', 'gtag', 'analytics',
            // The endpoints themselves, not the modal's own `data-ask-ai-*` attribute
            // namespace, which the picker must of course be able to select on.
            'ask-ai/listing-question', 'api/ask-ai', 'agent-ai/', 'openai',
        ] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source,
                "The picker contains '{$forbidden}'.");
        }
    }

    public function test_no_server_route_or_handler_was_added_for_question_search(): void
    {
        // Search filters markup the page already holds; it has no endpoint at all.
        $routes = file_get_contents(base_path('routes/web.php'));
        foreach (['ask-ai-ask', 'deterministic-question', 'typed-question', 'question-match',
                  'question-picker', 'question-search'] as $needle) {
            $this->assertStringNotContainsString($needle, $routes, "routes/web.php gained '{$needle}'.");
        }

        $this->assertFileExists(base_path(self::PICKER));
        $this->assertDirectoryDoesNotExist(base_path('app/Http/Controllers/AskAi/TypedQuestion'));
    }

    /* ================================================================== */
    /* Owner experience                                                    */
    /* ================================================================== */

    public function test_the_owner_gets_owner_questions_separately_and_shoppers_do_not(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes']);
        $owner   = User::find($listing->user_id);

        $mine = $this->modal(
            $this->actingAs($owner)->get(route('offer.listing.seller.view', $listing->id))->assertOk()->getContent(),
            'seller'
        );
        auth()->logout();
        $public = $this->modal($this->page('offer.listing.seller.view', $listing->id), 'seller');

        // The owner's questions are their own group, apart from the public View-all list,
        // and each is a listed question (no text box).
        $this->assertStringContainsString('data-ask-ai-owner-picker', $mine);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-ask-ai-owner-question="[^"]+"/', $mine);
        $allStart = strpos($mine, 'data-ask-ai-picker-all');
        $allEnd   = strpos($mine, 'data-ask-ai-picker-answers', $allStart);
        $this->assertStringNotContainsString('data-ask-ai-owner-question', substr($mine, $allStart, $allEnd - $allStart),
            'Owner questions must not be mixed into the public View-all list.');

        $this->assertStringNotContainsString('data-ask-ai-owner-picker', $public);
        $this->assertStringNotContainsString('owner-question-picker.js', $this->page('offer.listing.seller.view', $listing->id));
        $this->assertSame(array_keys($this->shippedVocabulary($public)), array_keys($this->shippedVocabulary($mine)));
    }

    /* ================================================================== */
    /* Accessibility                                                       */
    /* ================================================================== */

    public function test_the_search_filter_is_labelled_and_reports_no_match_politely(): void
    {
        $listing = $this->seller(['pool_needed' => 'Yes']);
        $modal   = $this->modal($this->page('offer.listing.seller.view', $listing->id), 'seller');

        $this->assertMatchesRegularExpression('/<label[^>]*for="solAiQuestionSearch"/', $modal);
        $this->assertMatchesRegularExpression('/<input[^>]*id="solAiQuestionSearch"/', $modal);
        $this->assertMatchesRegularExpression('/<input[^>]*aria-controls="solAiAllQuestions"/', $modal);
        $this->assertMatchesRegularExpression('/data-ask-ai-picker-empty[^>]*role="status"/', $modal);
        $this->assertMatchesRegularExpression('/data-ask-ai-picker-answer[^>]*aria-live="polite"/', $modal);
        $this->assertMatchesRegularExpression('/data-ask-ai-picker-toggle[^>]*aria-expanded="false"[^>]*aria-controls="solAiAllQuestions"/', $modal);
    }
}
