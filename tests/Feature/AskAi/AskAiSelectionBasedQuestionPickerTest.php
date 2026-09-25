<?php

namespace Tests\Feature\AskAi;

use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Support\AskAi\PageFactCoverageProbe as P;
use Tests\Support\AskAi\MlsImportFixture;
use Tests\TestCase;

/**
 * Ask AI is SELECTION-BASED (2026-09-25): the viewer picks one of the verified questions the
 * listing can answer and reads its precomputed answer. There is no free-text submission.
 *
 * Three concepts, kept apart and each asserted here against the real rendered page:
 *
 *   ANSWERABLE  every row AskAiPublicPropertyQuestionService returns for this viewer;
 *   FEATURED    at most six of them, on the listing card and at the top of the modal;
 *   ALL         the whole answerable set, searchable, behind "View all questions".
 *
 * The browser half (search filtering, selection, zero requests while typing) is exercised in
 * tests/browser against the same markup; this file proves what the SERVER ships, which is the
 * security boundary — a question absent from the markup can never be found or shown.
 */
class AskAiSelectionBasedQuestionPickerTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTES = [
        'seller'   => 'offer.listing.seller.view',
        'landlord' => 'offer.listing.landlord.view',
        'buyer'    => 'offer.listing.buyer.view',
        'tenant'   => 'offer.listing.tenant.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
        // Zero model calls and zero outbound HTTP, for every test in this file.
        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        Http::fake(static fn () => throw new \RuntimeException('No outbound request is allowed in this test.'));
    }

    /* ------------------------------------------------------------------ */
    /* Parsing                                                             */
    /* ------------------------------------------------------------------ */

    private function page(string $role, int $id, ?User $as = null): string
    {
        $req = $as ? $this->actingAs($as) : $this;
        $html = $req->get(route(self::ROUTES[$role], $id))->assertOk()->getContent();
        auth()->logout();

        return $html;
    }

    private function region(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, "Region '{$open}' not found.");
        $end = strpos($html, $close, $start);
        $this->assertNotFalse($end, "Region '{$open}' is not closed by '{$close}'.");

        return substr($html, $start, $end - $start);
    }

    private function card(string $html, string $role): string
    {
        return $this->region($html, 'data-ask-ai-property-questions="' . $role . '"', 'ask-ai-pq-note');
    }

    private function modal(string $html, string $role): string
    {
        return $this->region($html, 'data-ask-ai-picker="' . $role . '"', 'ask-ai-picker-disclaimer');
    }

    /** @return list<string> */
    private function cardFeatured(string $card): array
    {
        preg_match_all('/data-property-question="([^"]+)"/', $card, $m);

        return $m[1];
    }

    /** @return list<string> */
    private function recommended(string $modal): array
    {
        $block = $this->region($modal, 'ask-ai-picker-recommended', '</div>');
        preg_match_all('/data-ask-ai-pick="([^"]+)"/', $block, $m);

        return $m[1];
    }

    /** @return array<string, array{display: string, terms: list<string>}> id => the View All entry */
    private function all(string $modal): array
    {
        $block = $this->region($modal, 'data-ask-ai-picker-all', 'data-ask-ai-picker-answers');
        preg_match_all('/<button[^>]*data-ask-ai-pick="([^"]+)"[^>]*data-ask-ai-search-terms="([^"]*)"[^>]*>(.*?)<\/button>/s', $block, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $id, $terms, $display]) {
            $out[$id] = [
                'display' => html_entity_decode(trim($display), ENT_QUOTES),
                'terms'   => explode('|', html_entity_decode($terms, ENT_QUOTES)),
            ];
        }

        return $out;
    }

    /** @return array<string, string> id => the answer shown when that question is selected */
    private function answers(string $modal): array
    {
        preg_match_all('/data-ask-ai-answer-for="([^"]+)"[^>]*>(.*?)<\/p>/s', $modal, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $id, $answer]) {
            $out[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        return $out;
    }

    /** @return list<array<string,mixed>> the service's answerable rows for this viewer */
    private function answerable(string $role, int $id, bool $owner = false): array
    {
        return app(AskAiPublicPropertyQuestionService::class)->forStoredListing($role, $id, $owner);
    }

    /** Every word of the query appears in one of the terms — question-picker.js's rule. */
    private function searchFinds(array $all, string $query): array
    {
        $norm = static fn (string $s): string => trim(preg_replace('/\s+/', ' ',
            preg_replace('/[?!.,:;"()]/', ' ', preg_replace('/[-_\/]/', ' ', mb_strtolower(str_replace(['‘', '’'], "'", $s))))));
        $words = array_filter(explode(' ', $norm($query)));
        $hits  = [];
        foreach ($all as $id => $entry) {
            $hay = ' ' . implode(' | ', array_map($norm, $entry['terms'])) . ' ';
            if (array_filter($words, static fn ($w) => !str_contains($hay, $w)) === []) {
                $hits[] = $id;
            }
        }

        return $hits;
    }

    /* ------------------------------------------------------------------ */
    /* Featured vs all                                                     */
    /* ------------------------------------------------------------------ */

    public function test_a_rich_mls_listing_answers_forty_plus_questions_but_features_four_to_six(): void
    {
        $listing = MlsImportFixture::import($this, 'residential', 'seller');
        $html    = $this->page('seller', $listing->id);
        $rows    = $this->answerable('seller', $listing->id);

        $this->assertGreaterThanOrEqual(40, count($rows), 'The overloaded MLS case needs 40+ answerable questions.');

        $featured = $this->cardFeatured($this->card($html, 'seller'));
        $this->assertGreaterThanOrEqual(4, count($featured));
        $this->assertLessThanOrEqual(6, count($featured), 'The listing page must not show dozens of questions.');

        $modal = $this->modal($html, 'seller');
        $this->assertSame($featured, $this->recommended($modal), 'Card and modal recommend the same questions.');
        $this->assertSame(array_column($rows, 'id'), array_keys($this->all($modal)),
            'View all lists exactly the answerable set, in the service order.');
        $this->assertSame([], array_diff($featured, array_column($rows, 'id')), 'Featured is a subset of answerable.');
        $this->assertStringContainsString('View all questions (' . count($rows) . ')', $modal);
    }

    public function test_every_answerable_question_including_non_featured_is_selectable_with_its_verified_answer(): void
    {
        $listing  = MlsImportFixture::import($this, 'residential', 'seller');
        $html     = $this->page('seller', $listing->id);
        $modal    = $this->modal($html, 'seller');
        $answers  = $this->answers($modal);
        $all      = $this->all($modal);
        $featured = $this->cardFeatured($this->card($html, 'seller'));

        $nonFeatured = 0;
        foreach ($this->answerable('seller', $listing->id) as $row) {
            $this->assertArrayHasKey($row['id'], $all, "{$row['id']} is not selectable.");
            $this->assertSame($row['answer'], $answers[$row['id']] ?? null, "{$row['id']} would show a different answer.");
            $nonFeatured += in_array($row['id'], $featured, true) ? 0 : 1;
        }
        $this->assertGreaterThan(30, $nonFeatured, 'Hiding a question from Featured must not remove it.');

        // The obscure MLS facts are reachable under View all, not on the page.
        $obscure = array_filter(array_keys($all), static fn ($id) => str_starts_with($id, 'mls_'));
        $this->assertNotEmpty($obscure);
        $this->assertSame([], array_intersect($obscure, $featured), 'Raw MLS questions must not dominate Featured.');
    }

    /**
     * @dataProvider everyRoleAndType
     */
    public function test_every_role_and_type_features_at_most_six_and_lists_the_whole_set(string $role, string $type, array $meta): void
    {
        $listing = P::makeListing($role, $type, $meta);
        $html    = $this->page($role, $listing->id);
        $rows    = $this->answerable($role, $listing->id);
        $this->assertNotEmpty($rows, "{$role} {$type} answers nothing — the fixture is too thin to prove anything.");

        $featured = $this->cardFeatured($this->card($html, $role));
        $this->assertLessThanOrEqual(6, count($featured));
        $this->assertGreaterThanOrEqual(min(4, count($rows)), count($featured));

        $modal = $this->modal($html, $role);
        $this->assertSame($featured, $this->recommended($modal));
        $this->assertSame(array_column($rows, 'id'), array_keys($this->all($modal)));
        $this->assertSame(array_column($rows, 'answer'), array_values(array_intersect_key($this->answers($modal), array_flip(array_column($rows, 'id')))));
    }

    public static function everyRoleAndType(): array
    {
        $home = ['bedrooms' => '3', 'bathrooms' => '2', 'heated_square' => '1850', 'year_built' => '1998',
                 'annual_property_taxes' => '4200', 'tax_year' => '2025', 'flood_zone_code' => 'AE', 'pool_needed' => 'Yes'];

        return [
            'seller residential' => ['seller', 'Residential', $home],
            'seller income'      => ['seller', 'Income', $home + ['total_units' => '4']],
            'seller commercial'  => ['seller', 'Commercial', ['zoning' => 'C-2', 'annual_property_taxes' => '9100', 'tax_year' => '2025', 'flood_zone_code' => 'X']],
            'seller business'    => ['seller', 'Business', ['zoning' => 'C-2', 'business_type' => json_encode(['Retail']), 'annual_property_taxes' => '3100']],
            'seller vacant land' => ['seller', 'Vacant Land', ['zoning' => 'AG', 'total_acreage' => '5.2', 'flood_zone_code' => 'X', 'annual_property_taxes' => '800']],
            'landlord residential' => ['landlord', 'Residential Property', ['bedrooms' => '2', 'bathrooms' => '1', 'pets' => 'Yes', 'flood_zone_code' => 'AE']],
            'landlord commercial'  => ['landlord', 'Commercial Property', ['zoning' => 'C-1', 'flood_zone_code' => 'X', 'heated_square_feet' => '2750']],
            'buyer residential'    => ['buyer', 'Residential', ['maximum_budget' => '450000', 'bedrooms' => '3', 'bathrooms' => '2', 'cities' => ['Seminole']]],
            'buyer commercial'     => ['buyer', 'Commercial', ['maximum_budget' => '900000', 'minimum_heated_square' => '2000', 'cities' => ['Tampa']]],
            'tenant residential'   => ['tenant', 'Residential', ['budget' => '2500', 'bedrooms' => '2', 'pets' => 'Yes', 'move_in_date_earliest' => '2027-01-15']],
            'tenant commercial'    => ['tenant', 'Commercial', ['budget' => '6000', 'cities' => ['Tampa']]],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Search discovers through the deterministic aliases                  */
    /* ------------------------------------------------------------------ */

    public function test_search_terms_carry_the_aliases_so_search_locates_questions(): void
    {
        $listing = P::makeListing('seller', 'Residential', [
            'bedrooms' => '3', 'bathrooms' => '2', 'heated_square' => '1850',
            'annual_property_taxes' => '4200', 'tax_year' => '2025',
            'roof_type' => ['Shingle'], 'utilities' => ['Electricity Connected', 'Sewer Connected'],
            // The roof-age question is the owner's own KB answer, published only on acknowledgement.
            'listing_ai_faq_public_ack' => '1',
            'listing_ai_faq' => ['roof_age_and_condition' => 'Roof replaced in 2019, architectural shingle.'],
        ]);
        $all = $this->all($this->modal($this->page('seller', $listing->id), 'seller'));

        foreach (['taxes' => 'tax', 'baths' => 'bathroom', 'bathrooms' => 'bathroom', 'sqft' => 'square', 'sq ft' => 'square'] as $query => $expect) {
            $hits = $this->searchFinds($all, $query);
            $this->assertNotEmpty($hits, "Searching '{$query}' finds nothing.");
            $this->assertNotEmpty(array_filter($hits, static fn ($id) => str_contains(mb_strtolower($all[$id]['display']), $expect)),
                "Searching '{$query}' does not surface a '{$expect}' question.");
        }

        foreach (['roof', 'age of roof', 'roof age', 'how old is the roof'] as $query) {
            $hits = $this->searchFinds($all, $query);
            $this->assertNotEmpty(array_filter($hits, static fn ($id) => str_contains(mb_strtolower($all[$id]['display']), 'how old is the roof')),
                "Searching '{$query}' does not surface the roof age question.");
        }

        $hits = $this->searchFinds($all, 'utilities');
        $this->assertNotEmpty(array_filter($hits, static fn ($id) => str_contains(mb_strtolower($all[$id]['display']), 'utilit')),
            "Searching 'utilities' does not surface the utilities question.");

        $this->assertSame([], $this->searchFinds($all, 'zzqx nothing matches this'));
    }

    public function test_every_search_keyword_names_a_real_knowledge_base_question(): void
    {
        // A typo here would silently add nothing, so each id must resolve to a KB key the
        // owner can actually answer for that role.
        foreach (array_keys((array) config('ask_ai_question_presentation.search_keywords')) as $id) {
            $this->assertMatchesRegularExpression('/^kb_(seller|landlord)_[a-z0-9_]+$/', $id);
            [, $role, $key] = explode('_', $id, 3);
            $keys = [];
            foreach ((array) config("ai_faq_{$role}.groups") as $group) {
                foreach ((array) $group as $section) {
                    $keys = array_merge($keys, array_keys((array) $section));
                }
            }
            $this->assertContains($key, $keys, "search_keywords names '{$id}', which is not a {$role} KB question.");
        }
    }

    /* ------------------------------------------------------------------ */
    /* No free-text submission                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @dataProvider everyRole
     */
    public function test_there_is_no_way_to_submit_typed_text(string $role, string $type): void
    {
        $listing = P::makeListing($role, $type, ['bedrooms' => '3', 'pool_needed' => 'Yes', 'pets' => 'Yes', 'budget' => '2500', 'maximum_budget' => '400000']);
        $owner   = User::find($listing->user_id);

        foreach ([null, $owner] as $viewer) {
            $html  = $this->page($role, $listing->id, $viewer);
            $who   = $viewer ? 'owner' : 'guest';
            $modal = $this->modal($html, $role);
            $card  = $this->card($html, $role);

            foreach ([$modal, $card] as $region) {
                foreach (['<form', '<textarea', 'action=', 'method=', 'wire:', '<script'] as $forbidden) {
                    $this->assertStringNotContainsString($forbidden, $region, "{$role}/{$who}: Ask AI contains '{$forbidden}'.");
                }
                $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $region, "{$role}/{$who}: a named input could be submitted.");
                $this->assertDoesNotMatchRegularExpression('/<button\b(?![^>]*type="button")[^>]*>/', $region, "{$role}/{$who}: every button is type=button.");
            }
            // The only text input is the search FILTER.
            preg_match_all('/<input\b[^>]*>/', $modal, $inputs);
            $this->assertCount(1, $inputs[0], "{$role}/{$who}: exactly one input — the search filter.");
            $this->assertStringContainsString('data-ask-ai-picker-search', $inputs[0][0]);
            $this->assertStringContainsString('placeholder="Search questions..."', $inputs[0][0]);

            // The old typed-question surfaces are gone from the page entirely.
            foreach (['data-ask-ai-ask-input', 'deterministic-question-matcher.js', 'Find answer', 'Type a question about this listing'] as $old) {
                $this->assertStringNotContainsString($old, $html, "{$role}/{$who}: '{$old}' is still on the page.");
            }
        }
    }

    public static function everyRole(): array
    {
        return [
            'seller'   => ['seller', 'Residential'],
            'landlord' => ['landlord', 'Residential Property'],
            'buyer'    => ['buyer', 'Residential'],
            'tenant'   => ['tenant', 'Residential'],
        ];
    }

    public function test_the_picker_script_performs_no_request_and_stores_nothing(): void
    {
        $source = (string) file_get_contents(base_path('public/js/ask-ai/question-picker.js'));
        $this->assertNotSame('', $source);
        foreach ([
            'fetch(', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'EventSource', '$.ajax', 'axios', 'import ', 'require(',
            'localStorage', 'sessionStorage', 'document.cookie', 'indexedDB',
            'location.href', 'location.assign', 'window.open', 'history.pushState', '.submit(',
            'console.log', 'dataLayer', 'gtag', 'ask-ai/listing-question', 'api/ask-ai', 'agent-ai/', 'openai',
        ] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source, "question-picker.js contains '{$forbidden}'.");
        }
    }

    public function test_view_all_is_a_capped_scrolling_list_with_show_and_hide(): void
    {
        $partial = (string) file_get_contents(base_path('resources/views/offer-listing/partials/_ask-ai-question-modal.blade.php'));
        $this->assertMatchesRegularExpression('/\.ask-ai-picker-all\s*\{[^}]*max-height:\s*[0-9.]+rem[^}]*overflow-y:\s*auto/', $partial);

        $listing = MlsImportFixture::import($this, 'residential', 'seller');
        $modal   = $this->modal($this->page('seller', $listing->id), 'seller');

        $this->assertMatchesRegularExpression('/class="ask-ai-picker-all"[^>]*data-ask-ai-picker-all[^>]*hidden/', $modal,
            'The complete list starts collapsed behind View all.');
        $this->assertMatchesRegularExpression('/data-ask-ai-picker-toggle[^>]*aria-expanded="false"/', $modal);
        $this->assertStringContainsString('data-label-hide="Hide all questions"', $modal);
    }

    /* ------------------------------------------------------------------ */
    /* Visibility                                                          */
    /* ------------------------------------------------------------------ */

    public function test_guest_and_non_owner_see_the_same_public_set_and_only_the_owner_gets_owner_questions(): void
    {
        $listing = MlsImportFixture::import($this, 'residential', 'seller');
        $owner   = User::find($listing->user_id);
        $other   = User::factory()->create();

        $guest    = $this->modal($this->page('seller', $listing->id), 'seller');
        $nonOwner = $this->modal($this->page('seller', $listing->id, $other), 'seller');
        $mine     = $this->modal($this->page('seller', $listing->id, $owner), 'seller');

        $this->assertSame(array_keys($this->all($guest)), array_keys($this->all($nonOwner)));
        $this->assertSame($this->answers($guest), $this->answers($nonOwner));
        $this->assertSame(array_column($this->answerable('seller', $listing->id, false), 'id'), array_keys($this->all($guest)));

        foreach ([$guest, $nonOwner] as $public) {
            $this->assertStringNotContainsString('data-ask-ai-owner-picker', $public);
            $this->assertStringNotContainsString('data-ask-ai-owner-question', $public);
        }
        $this->assertStringContainsString('data-ask-ai-owner-picker', $mine);
    }

    public function test_a_feed_that_forbids_display_lists_no_mls_question_to_a_non_owner(): void
    {
        $listing = MlsImportFixture::import($this, 'residential', 'seller', ['InternetEntireListingDisplayYN' => false]);
        $html    = $this->page('seller', $listing->id);
        $ids     = array_keys($this->all($this->modal($html, 'seller')));

        $this->assertSame([], array_filter($ids, static fn ($id) => str_starts_with($id, 'mls_')),
            'A non-displayable MLS fact reached View all.');
        $this->assertSame([], array_filter($this->cardFeatured($this->card($html, 'seller')), static fn ($id) => str_starts_with($id, 'mls_')));
    }

    public function test_private_buyer_and_tenant_facts_are_never_listed_featured_or_searchable(): void
    {
        $buyer = P::makeListing('buyer', 'Residential', [
            'maximum_budget' => '450000', 'bedrooms' => '3', 'cities' => ['Seminole'],
            'pre_approval_amount' => '525000', 'cash_budget' => '120000', 'down_payment_amount' => '90000', 'number_occupant' => '4',
        ]);
        $tenant = P::makeListing('tenant', 'Residential', [
            'budget' => '2500', 'move_in_date_earliest' => '2027-01-15', 'pets' => 'Yes',
            'monthly_income' => '7600', 'credit_score_range' => '700-749', 'service_animal' => 'Yes', 'accessibility_requirements' => 'Ground floor',
        ]);

        foreach ([
            ['buyer', $buyer, ['525000', '525,000', '120,000', '90,000'], ['preapproval', 'pre approval', 'down payment', 'occupants', 'lender', 'credit']],
            ['tenant', $tenant, ['7600', '7,600', '700-749', 'Ground floor'], ['income', 'salary', 'credit', 'service animal', 'support animal', 'accessibility', 'disability']],
        ] as [$role, $listing, $values, $queries]) {
            $html  = $this->page($role, $listing->id);
            $modal = $this->modal($html, $role);
            $card  = $this->card($html, $role);
            $all   = $this->all($modal);
            $this->assertNotEmpty($all);

            foreach ($values as $v) {
                $this->assertStringNotContainsString($v, $modal, "{$role}: private value '{$v}' is in the modal.");
                $this->assertStringNotContainsString($v, $card, "{$role}: private value '{$v}' is on the card.");
            }
            foreach ($queries as $q) {
                $this->assertSame([], $this->searchFinds($all, $q), "{$role}: searching '{$q}' finds a question.");
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* No model path                                                       */
    /* ------------------------------------------------------------------ */

    public function test_rendering_featured_all_and_answers_sends_nothing_anywhere(): void
    {
        $listing = MlsImportFixture::import($this, 'residential', 'seller');
        Http::fake();
        foreach ([null, User::find($listing->user_id)] as $viewer) {
            $this->page('seller', $listing->id, $viewer);
        }
        Http::assertNothingSent();
    }
}
