<?php

namespace Tests\Feature\AskAi;

use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService as Scope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Support\AskAi\MlsImportFixture;
use Tests\Support\AskAi\PageFactCoverageProbe as P;
use Tests\TestCase;

/**
 * Lot size and acreage keep their UNIT, on the page and in Ask AI, for every role and type.
 *
 * The defect: a Vacant Land listing with total_acreage = 5.2 showed "Acreage 5.2" on its page
 * while Ask AI answered "The lot is 5 square feet." — the lot-size answer read the ACREAGE
 * field and treated any number as square feet, rounding it on the way. Both surfaces now read
 * acreage through App\Support\Listing\LotAcreage, and MLS "Lot Size Area" is stated in the
 * feed's own "Lot Size Units". Nothing is converted between units anywhere.
 *
 * Zero model calls: the OpenAI client and the intent normalizer fail the test if touched, and
 * every outbound HTTP request throws.
 */
class AskAiLotSizeUnitsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTES = P::ROUTES;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            // Even switched ON, the normalizer must never be reached by these questions.
            'ask_ai.enable_openai_intent_normalization' => true,
        ]);
        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        $this->partialMock(AskAiIntentNormalizerService::class, function ($mock): void {
            $mock->shouldNotReceive('normalize');
        });
        Http::fake(static fn () => throw new \RuntimeException('No outbound request is allowed in this test.'));
    }

    /* ------------------------------------------------------------------ */
    /* The reported defect                                                 */
    /* ------------------------------------------------------------------ */

    public function test_vacant_land_decimal_acreage_is_acres_on_the_page_and_in_ask_ai(): void
    {
        $l = P::makeListing('seller', 'Vacant Land', ['zoning' => 'AG', 'total_acreage' => '5.2', 'flood_zone_code' => 'X']);

        $page = $this->pageText('seller', $l->id);
        $this->assertMatchesRegularExpression('/Acreage\s+5\.2 acres\b/', $page);

        $answers = $this->answerMap('seller', $l->id);
        $this->assertSame('The total acreage is 5.2 acres.', $answers['seller_total_acreage'] ?? null);

        foreach (['What is the lot size / acreage?', 'How big is the lot?', 'how many acres'] as $q) {
            $this->answers($l, 'seller', $q, '5.2 acres');
        }

        $html = $this->html('seller', $l->id);
        foreach (['5 square feet', '5.2 square feet', 'The lot is 5 '] as $wrong) {
            $this->assertStringNotContainsString($wrong, $html, "The page or Ask AI still says '{$wrong}'.");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Seller, every type, manual                                          */
    /* ------------------------------------------------------------------ */

    /** @dataProvider sellerTypes */
    public function test_seller_acreage_keeps_its_unit_for_every_property_type(string $type): void
    {
        foreach ([
            '0.75'                     => ['0.75 acres', 'The total acreage is 0.75 acres.'],
            '1'                        => ['1 acre', 'The total acreage is 1 acre.'],
            '1,250.5'                  => ['1,250.5 acres', 'The total acreage is 1,250.5 acres.'],
            '5 to less than 10 acres'  => ['5 to less than 10 acres', 'The total acreage is 5 to less than 10 acres.'],
            '12,680 sq ft'             => ['12,680 square feet', 'The lot size is 12,680 square feet.'],
        ] as $stored => [$shown, $answer]) {
            $l = P::makeListing('seller', $type, ['total_acreage' => $stored]);

            $this->assertMatchesRegularExpression('/Acreage\s+' . preg_quote($shown, '/') . '(?![\d.,])/', $this->pageText('seller', $l->id), "{$type} '{$stored}'");
            $this->assertSame($answer, $this->answerMap('seller', $l->id)['seller_total_acreage'] ?? null, "{$type} '{$stored}'");
        }
    }

    public static function sellerTypes(): array
    {
        return array_combine(P::MATRIX['seller'], array_map(static fn ($t) => [$t], P::MATRIX['seller']));
    }

    public function test_the_lot_size_fallback_states_the_value_the_page_shows_in_acres(): void
    {
        // Only the legacy min_acreage: the acreage question has no source, the lot-size
        // question answers — in acres, from the same value the page prints.
        $l = P::makeListing('seller', 'Vacant Land', ['min_acreage' => '2.5']);
        $this->assertMatchesRegularExpression('/Acreage\s+2\.5 acres\b/', $this->pageText('seller', $l->id));
        $answers = $this->answerMap('seller', $l->id);
        $this->assertArrayNotHasKey('seller_total_acreage', $answers);
        $this->assertSame('The lot is 2.5 acres.', $answers['seller_lot_size'] ?? null);

        // Both, disagreeing: the page prints min_acreage, so Ask AI must too — never the other.
        $l = P::makeListing('seller', 'Residential', ['min_acreage' => '3', 'total_acreage' => '1/4 to less than 1/2 acre']);
        $this->assertMatchesRegularExpression('/Acreage\s+3 acres\b/', $this->pageText('seller', $l->id));
        $answers = $this->answerMap('seller', $l->id);
        $this->assertArrayNotHasKey('seller_total_acreage', $answers, 'guard: a disagreeing min_acreage hides the total question');
        $this->assertSame('The lot is 3 acres.', $answers['seller_lot_size'] ?? null);

        // Lot dimensions still ride along, and never acquire a unit they did not state.
        $l = P::makeListing('seller', 'Residential', ['min_acreage' => '0.5', 'lot_dimensions' => '100 x 218']);
        $this->assertSame('The lot is 0.5 acres. Lot dimensions: 100 x 218.', $this->answerMap('seller', $l->id)['seller_lot_size'] ?? null);
    }

    public function test_missing_or_unusable_acreage_refuses(): void
    {
        foreach ([[], ['total_acreage' => 'Non-Applicable'], ['total_acreage' => '0'], ['total_acreage' => '']] as $meta) {
            $l = P::makeListing('seller', 'Vacant Land', ['zoning' => 'AG'] + $meta);
            $answers = $this->answerMap('seller', $l->id);
            $this->assertArrayNotHasKey('seller_total_acreage', $answers, json_encode($meta));
            $this->assertArrayNotHasKey('seller_lot_size', $answers, json_encode($meta));
            $this->refuses($l, 'seller', 'how many acres', 'square feet');
            $this->assertStringNotContainsString('square feet', $this->html('seller', $l->id));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Seller and Landlord, MLS                                            */
    /* ------------------------------------------------------------------ */

    public function test_mls_vacant_land_band_and_square_foot_lot_area_keep_their_units(): void
    {
        // Feed: LotSizeAcres 12.5 (banded on import), LotSizeArea 544500 in "Square Feet".
        $l = MlsImportFixture::import($this, 'vacant_land', 'seller');
        $page = $this->pageText('seller', $l->id);
        $this->assertMatchesRegularExpression('/Acreage\s+10 to less than 20 acres\b/', $page);
        $this->assertMatchesRegularExpression('/Lot Size Area\s+544500 square feet\b/', $page);

        $answers = $this->answerMap('seller', $l->id);
        // The exact measurement leads, in the feed's own unit; the range follows. No conversion.
        $this->assertSame('The lot size is 544500 square feet. The MLS acreage range is 10 to less than 20 acres.', $answers['seller_total_acreage'] ?? null);
        $this->assertSame('Lot Size Area: 544500 square feet.', $answers['mls_seller_lotsizearea'] ?? null);
        // Stellar's own band arrives without a unit; it is read as the acreage band it is.
        $this->assertSame('Total Acreage (MLS Range): 10 to less than 20 acres.', $answers['mls_seller_stellar_totalacreage'] ?? null);
        $this->assertMatchesRegularExpression('/Total Acreage \(MLS Range\):?\s+10 to less than 20 acres\b/', $page);
        $this->assertStringNotContainsString('544500 acres', $this->html('seller', $l->id));
    }

    public function test_mls_lot_area_stated_in_acres_stays_acres(): void
    {
        // Feed: LotSizeArea 7.25 in "Acres" (business) and 1.9765 in "Acres" (residential lease).
        $bus = MlsImportFixture::import($this, 'business_opportunity', 'seller');
        $this->assertMatchesRegularExpression('/Lot Size Area\s+7\.25 acres\b/', $this->pageText('seller', $bus->id));
        $this->assertSame('Lot Size Area: 7.25 acres.', $this->answerMap('seller', $bus->id)['mls_seller_lotsizearea'] ?? null);

        $lease = MlsImportFixture::import($this, 'residential_lease', 'landlord');
        $this->assertMatchesRegularExpression('/Lot Size Area\s+1\.9765 acres\b/', $this->pageText('landlord', $lease->id));
        $this->assertSame('Lot Size Area: 1.9765 acres.', $this->answerMap('landlord', $lease->id)['mls_landlord_lotsizearea'] ?? null);
        $this->assertStringNotContainsString('1.9765 square feet', $this->html('landlord', $lease->id));
        // No Stellar range on this record: the listing's own acreage band follows as the range.
        $this->assertSame('The lot size is 1.9765 acres. The acreage range is 1 to less than 2 acres.', $this->answerMap('landlord', $lease->id)['landlord_total_acreage'] ?? null);
    }

    /* ------------------------------------------------------------------ */
    /* "What is the lot size / acreage?" — exact measurement, then range   */
    /* ------------------------------------------------------------------ */

    public function test_generic_lot_size_question_prefers_the_exact_mls_measurement(): void
    {
        $generic = 'What is the lot size / acreage?';

        // A. Exact acres + range (feed: LotSizeArea 7.25 Acres, STELLAR_TotalAcreage "5 to less than 10").
        $a = MlsImportFixture::import($this, 'business_opportunity', 'seller');
        $want = 'The lot size is 7.25 acres. The MLS acreage range is 5 to less than 10 acres.';
        $this->assertSame($want, $this->answerMap('seller', $a->id)['seller_total_acreage'] ?? null);
        $this->assertSame($want, $this->ask($a, 'seller', $generic, Scope::SCOPE_PUBLIC)['answer']);
        $this->assertSame($want, $this->ask($a, 'seller', 'How big is the lot?', Scope::SCOPE_PUBLIC)['answer']);
        // The dedicated questions are untouched.
        $this->assertSame('Lot Size Area: 7.25 acres.', $this->answerMap('seller', $a->id)['mls_seller_lotsizearea'] ?? null);
        $this->assertSame('Total Acreage (MLS Range): 5 to less than 10 acres.', $this->answerMap('seller', $a->id)['mls_seller_stellar_totalacreage'] ?? null);

        // B. Exact square feet + acreage range, seller and landlord: square feet stays square feet.
        foreach ([['commercial_sale', 'seller'], ['commercial_lease', 'landlord']] as [$slug, $role]) {
            $b = MlsImportFixture::import($this, $slug, $role);
            $answer = $this->answerMap($role, $b->id)["{$role}_total_acreage"] ?? '';
            $this->assertSame('The lot size is 12680 square feet. The MLS acreage range is 1/4 to less than 1/2 acre.', $answer, $slug);
            $this->assertStringNotContainsString('0.29', $answer, $slug);
        }

        // C. Exact measurement only (no LotSizeAcres band, no Stellar range).
        $c = MlsImportFixture::import($this, 'business_opportunity', 'seller', [
            'ListingKey' => 'AUDIT-EXACT-ONLY', 'ListingId' => 'AUDEXO1', 'LotSizeAcres' => null, 'STELLAR_TotalAcreage' => null,
        ]);
        $this->assertSame('The lot size is 7.25 acres.', $this->answerMap('seller', $c->id)['seller_total_acreage'] ?? null);

        // D. Range only (no exact measurement): the range, as before.
        $d = MlsImportFixture::import($this, 'business_opportunity', 'seller', [
            'ListingKey' => 'AUDIT-RANGE-ONLY', 'ListingId' => 'AUDRNG1', 'LotSizeArea' => null, 'LotSizeUnits' => null,
        ]);
        $this->assertSame('The total acreage is 5 to less than 10 acres.', $this->answerMap('seller', $d->id)['seller_total_acreage'] ?? null);

        // An area whose unit the feed does not state is not a measurement: never guessed.
        $u = MlsImportFixture::import($this, 'business_opportunity', 'seller', [
            'ListingKey' => 'AUDIT-NO-UNIT', 'ListingId' => 'AUDNOU1', 'LotSizeUnits' => null,
        ]);
        $this->assertSame('The total acreage is 5 to less than 10 acres.', $this->answerMap('seller', $u->id)['seller_total_acreage'] ?? null);

        // E. Manual acreage is unchanged.
        $e = P::makeListing('seller', 'Vacant Land', ['total_acreage' => '5.2']);
        $this->assertSame('The total acreage is 5.2 acres.', $this->answerMap('seller', $e->id)['seller_total_acreage'] ?? null);

        // F. No lot facts at all: still no answer.
        $f = MlsImportFixture::import($this, 'residential', 'seller');
        $this->assertArrayNotHasKey('seller_total_acreage', $this->answerMap('seller', $f->id));
    }

    public function test_the_mls_measurement_is_withheld_when_the_feed_withholds_the_listing(): void
    {
        $l = MlsImportFixture::import($this, 'business_opportunity', 'seller', [
            'ListingKey' => 'AUDIT-NO-IDX', 'ListingId' => 'AUDNOI1', 'IDXParticipationYN' => false,
        ]);
        // The MLS rows are not offered to the public, so the generic answer cannot lead with them.
        $this->assertStringNotContainsString('7.25', (string) ($this->answerMap('seller', $l->id)['seller_total_acreage'] ?? ''));
    }

    public function test_mls_commercial_square_foot_lot_area_stays_square_feet_for_seller_and_landlord(): void
    {
        foreach ([['commercial_sale', 'seller'], ['commercial_lease', 'landlord']] as [$slug, $role]) {
            $l = MlsImportFixture::import($this, $slug, $role);
            $this->assertMatchesRegularExpression('/Lot Size Area\s+12680 square feet\b/', $this->pageText($role, $l->id), $slug);
            $this->assertSame('Lot Size Area: 12680 square feet.', $this->answerMap($role, $l->id)["mls_{$role}_lotsizearea"] ?? null, $slug);
        }
    }

    public function test_an_mls_listing_with_no_lot_measurement_offers_no_lot_size_question(): void
    {
        // Feed: LotSizeAcres 0, LotSizeArea null — "no measurement", not a tiny lot.
        $l = MlsImportFixture::import($this, 'residential', 'seller');
        $answers = $this->answerMap('seller', $l->id);
        foreach (['seller_total_acreage', 'seller_lot_size', 'mls_seller_lotsizearea'] as $id) {
            $this->assertArrayNotHasKey($id, $answers);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Landlord, manual                                                    */
    /* ------------------------------------------------------------------ */

    /** @dataProvider landlordTypes */
    public function test_landlord_acreage_keeps_its_unit(string $type): void
    {
        $l = P::makeListing('landlord', $type, ['total_acreage' => '1.5', 'min_acreage' => '0.25']);
        $page = $this->pageText('landlord', $l->id);
        $this->assertMatchesRegularExpression('/Total Acreage\s+1\.5 acres\b/', $page);
        $this->assertMatchesRegularExpression('/Min Acreage\s+0\.25 acres\b/', $page);
        $this->assertSame('The total acreage is 1.5 acres.', $this->answerMap('landlord', $l->id)['landlord_total_acreage'] ?? null);
    }

    public static function landlordTypes(): array
    {
        return array_combine(P::MATRIX['landlord'], array_map(static fn ($t) => [$t], P::MATRIX['landlord']));
    }

    /* ------------------------------------------------------------------ */
    /* Buyer and Tenant — search criteria                                  */
    /* ------------------------------------------------------------------ */

    /** @dataProvider criteriaTypes */
    public function test_criteria_acreage_band_is_answered_and_a_bare_number_is_never_given_a_meaning(string $role, string $type, bool $applies): void
    {
        $band = P::makeListing($role, $type, ['total_acreage' => '2 to less than 5 acres', 'min_acreage' => '2 to less than 5 acres']);
        $answers = $this->answerMap($role, $band->id);
        if ($applies) {
            $this->assertSame("The {$role} is looking for a lot of 2 to less than 5 acres.", $answers["{$role}_acreage"] ?? null);
        } else {
            $this->assertArrayNotHasKey("{$role}_acreage", $answers, "{$role} {$type}: acreage does not apply to this type");
        }

        // A bare number: the page shows it WITH its unit, Ask AI states nothing rather than
        // pick "at least" or "about" for it — and never square feet.
        $num = P::makeListing($role, $type, ['total_acreage' => '5.2', 'min_acreage' => '5.2']);
        $text = $this->pageText($role, $num->id);
        $this->assertMatchesRegularExpression('/Min\.? Acreage:?\s+5\.2 acres\b/', $text);
        // Every place the page prints this value carries the unit (the tenant page also
        // publishes total_acreage under Additional Information).
        $this->assertDoesNotMatchRegularExpression('/Acreage:?\s+5\.2(?! acres)/', $text);
        $numAnswers = $this->answerMap($role, $num->id);
        $this->assertArrayNotHasKey("{$role}_acreage", $numAnswers);
        $this->assertStringNotContainsString('5.2 square feet', implode(' ', $numAnswers));
    }

    public static function criteriaTypes(): array
    {
        $out = [];
        foreach (P::MATRIX['buyer'] as $t) {
            $out["buyer {$t}"] = ['buyer', $t, $t !== 'Business'];
        }
        foreach (P::MATRIX['tenant'] as $t) {
            $out["tenant {$t}"] = ['tenant', $t, true];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Owner scope reads the same way                                      */
    /* ------------------------------------------------------------------ */

    public function test_the_owner_hears_acres_too(): void
    {
        $l = P::makeListing('seller', 'Vacant Land', ['total_acreage' => '5.2']);
        $this->answers($l, 'seller', 'How big is the lot?', '5.2 acres', Scope::SCOPE_OWNER);
        $owner = User::find($l->user_id);
        $this->assertMatchesRegularExpression('/Acreage\s+5\.2 acres\b/', $this->pageText('seller', $l->id, $owner));
    }

    /* ------------------------------------------------------------------ */
    /* Hire Agent detail pages share the meta, so they share the reading   */
    /* ------------------------------------------------------------------ */

    /**
     * @dataProvider hireRoles
     */
    public function test_hire_agent_detail_pages_print_acreage_with_its_unit(string $role, string $route, string $class, string $type): void
    {
        $owner   = User::factory()->create(['user_type' => $role]);
        $listing = $class::forceCreate([
            'user_id' => $owner->id, 'title' => 'Acreage', 'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('property_type', $type);
        $listing->saveMeta('total_acreage', '5.2');

        $text = preg_replace('/\s+/', ' ', strip_tags(html_entity_decode(
            (string) $this->actingAs($owner)->get(route($route, $listing->id))->assertOk()->getContent(),
            ENT_QUOTES
        ))) ?? '';
        auth()->logout();

        $this->assertMatchesRegularExpression('/Acreage(?: Needed)?:?\s+5\.2 acres\b/', $text, $role);
        $this->assertStringNotContainsString('5 square feet', $text, $role);
    }

    public static function hireRoles(): array
    {
        return [
            'seller'   => ['seller',   'seller.agent.auction.detail', \App\Models\SellerAgentAuction::class,   'Vacant Land'],
            'landlord' => ['landlord', 'landlord.agent.auction.view', \App\Models\LandlordAgentAuction::class, 'Commercial Property'],
            'buyer'    => ['buyer',    'buyer.view-auction',          \App\Models\BuyerAgentAuction::class,    'Vacant Land'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function html(string $role, int $id, ?User $as = null): string
    {
        $req  = $as ? $this->actingAs($as) : $this;
        $html = $req->get(route(self::ROUTES[$role], $id))->assertOk()->getContent();
        auth()->logout();

        return html_entity_decode((string) $html, ENT_QUOTES);
    }

    /** The page with Ask AI's own card and modal removed, so a match is the PAGE stating it. */
    private function pageText(string $role, int $id, ?User $as = null): string
    {
        [, $page] = P::split($this->html($role, $id, $as), $role);

        return preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $page) ?? '') ?? '';
    }

    /** @return array<string, string> question id => answer */
    private function answerMap(string $role, int $id): array
    {
        $rows = app(AskAiPublicPropertyQuestionService::class)->forStoredListing($role, $id, false);

        return array_column($rows, 'answer', 'id');
    }

    private function ask(object $listing, string $role, string $question, string $scope): array
    {
        $r = app(AskAiRunnerV2Service::class)->run($role, $listing->id, $question, ['viewer_scope' => $scope]);

        return ['status' => (string) ($r['status'] ?? ''), 'answer' => (string) ($r['final_response']['answer'] ?? '')];
    }

    private function answers(object $listing, string $role, string $question, string $expect, string $scope = Scope::SCOPE_PUBLIC): void
    {
        $r = $this->ask($listing, $role, $question, $scope);
        $this->assertSame('ready', $r['status'], "{$role} '{$question}' ({$scope}) was not answered: {$r['answer']}");
        $this->assertStringContainsString($expect, $r['answer'], "{$role} '{$question}' ({$scope})");
        $this->assertStringNotContainsString('square feet', $r['answer'], "{$role} '{$question}' ({$scope})");
    }

    private function refuses(object $listing, string $role, string $question, string $mustNotContain): void
    {
        $r = $this->ask($listing, $role, $question, Scope::SCOPE_PUBLIC);
        $this->assertStringNotContainsString($mustNotContain, $r['answer']);
        $this->assertNotSame('ready', $r['status'], "{$role} '{$question}' should refuse, answered: {$r['answer']}");
    }
}
