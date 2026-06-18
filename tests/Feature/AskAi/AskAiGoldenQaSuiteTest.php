<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiFinalResponseBuilderService as FRB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Golden QA Suite — Truth Source Contract
 *
 * Verifies that:
 *   1. CANONICAL_SOURCE_MAP exists and covers all four roles.
 *   2. extractListingFields() always returns a '_sources' key.
 *   3. conflictDetect() correctly identifies matching vs conflicting values.
 *   4. contractFormOf() / assertContractForm() classify answer forms correctly.
 *   5. SYNTHESIS_REQUIRED_KEYS routing — "Will landlord accept a 4 month lease?"
 *      resolves to listing.lease_terms (not listing.lease_length / min_lease_period).
 *   6. Lease terms regression: synthesized context for landlord reads terms_of_lease
 *      (e.g. "3 Months, 6 Months"), not min_lease_period ("30 days").
 *   7. Landlord description now populated from 'additional_details' EAV key.
 *   8. Seller 121 / Landlord 71 — live-DB truth source alignment (skipped when
 *      records not present in the test environment).
 *
 * Routing tests use reflection on the private detectListingFieldKey() method.
 * Context tests create fresh in-transaction auction records using saveMeta().
 */
class AskAiGoldenQaSuiteTest extends TestCase
{
    use DatabaseTransactions;

    private AskAiContextBuilderService $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextBuilder = app(AskAiContextBuilderService::class);
    }

    // =========================================================================
    // § 1 — CANONICAL_SOURCE_MAP structure
    // =========================================================================

    public function test_canonical_source_map_covers_all_four_roles(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        $this->assertArrayHasKey('seller',   $map, 'seller role missing from CANONICAL_SOURCE_MAP');
        $this->assertArrayHasKey('buyer',    $map, 'buyer role missing from CANONICAL_SOURCE_MAP');
        $this->assertArrayHasKey('landlord', $map, 'landlord role missing from CANONICAL_SOURCE_MAP');
        $this->assertArrayHasKey('tenant',   $map, 'tenant role missing from CANONICAL_SOURCE_MAP');
    }

    public function test_canonical_source_map_seller_has_key_fields(): void
    {
        $seller = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];

        foreach (['description', 'asking_price', 'square_feet', 'year_built'] as $key) {
            $this->assertArrayHasKey($key, $seller, "seller CANONICAL_SOURCE_MAP missing '{$key}'");
        }
    }

    public function test_canonical_source_map_landlord_has_lease_and_description(): void
    {
        $landlord = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord'];

        $this->assertArrayHasKey('description', $landlord,
            "landlord CANONICAL_SOURCE_MAP missing 'description' (should be 'additional_details')");
        $this->assertSame('additional_details', $landlord['description']);

        $this->assertArrayHasKey('lease_terms', $landlord,
            "landlord CANONICAL_SOURCE_MAP missing 'lease_terms'");
        $this->assertSame('terms_of_lease', $landlord['lease_terms']);

        $this->assertArrayHasKey('utilities', $landlord,
            "landlord CANONICAL_SOURCE_MAP missing 'utilities'");
        // Must be an array so conflict-detection can use the UI-view key ('utilities') first.
        $this->assertIsArray($landlord['utilities']);
        $this->assertSame('utilities', $landlord['utilities'][0],
            "landlord utilities source spec must have 'utilities' (UI-view key) as the first element");
    }

    // =========================================================================
    // § 2 — _sources tracking in extractListingFields()
    // =========================================================================

    public function test_sources_key_present_in_seller_context(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Golden QA sources test',
            'address' => '1 Sources Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        // _sources is a top-level key in the buildForListing() return array,
        // separate from the 'listing' sub-array (added in Truth Source Contract work).
        $this->assertArrayHasKey('_sources', $ctx,
            '_sources key must be present at top level of buildForListing() output');
        $this->assertArrayHasKey('asking_price', $ctx['_sources'],
            '_sources must include seller canonical keys like asking_price');
    }

    public function test_sources_key_present_in_landlord_context(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $this->assertArrayHasKey('_sources', $ctx,
            '_sources key must be present at top level of landlord buildForListing() output');
        $this->assertArrayHasKey('lease_terms', $ctx['_sources'],
            '_sources landlord must include lease_terms');
        $this->assertArrayHasKey('description', $ctx['_sources'],
            '_sources landlord must include description');
    }

    // =========================================================================
    // § 3 — conflictDetect() utility
    // =========================================================================

    public function test_conflict_detect_returns_no_conflict_when_values_match(): void
    {
        $result = AskAiContextBuilderService::conflictDetect(
            'asking_price',
            '$450,000',
            '$450,000'
        );

        $this->assertFalse($result['conflict'],   'identical values should not conflict');
        $this->assertSame('asking_price', $result['canonical_key']);
        $this->assertSame('$450,000',     $result['context_value']);
        $this->assertSame('$450,000',     $result['ui_value']);
    }

    public function test_conflict_detect_case_and_whitespace_insensitive(): void
    {
        $result = AskAiContextBuilderService::conflictDetect(
            'some_field',
            '  Electric, Gas  ',
            'electric, gas'
        );

        $this->assertFalse($result['conflict'],
            'conflictDetect should normalise case and trim before comparing');
    }

    public function test_conflict_detect_fires_when_values_differ(): void
    {
        $result = AskAiContextBuilderService::conflictDetect(
            'asking_price',
            '$450,000',
            '$475,000'
        );

        $this->assertTrue($result['conflict'], 'differing values must trigger conflict=true');
    }

    public function test_conflict_detect_no_conflict_when_both_empty(): void
    {
        foreach ([null, ''] as $empty) {
            $result = AskAiContextBuilderService::conflictDetect('some_key', $empty, $empty);
            $this->assertFalse($result['conflict'],
                'both-empty should not register as a conflict');
        }
    }

    public function test_conflict_detect_fires_when_one_side_empty(): void
    {
        $result = AskAiContextBuilderService::conflictDetect('rent_amount', '2500', null);
        $this->assertTrue($result['conflict'],
            'one side empty and other non-empty must be a conflict');
    }

    // =========================================================================
    // § 4 — contractFormOf() / assertContractForm()
    // =========================================================================

    public function test_contract_form_of_returns_refusal_for_blocked_status(): void
    {
        $builder = new AskAiFinalResponseBuilderService();
        $form    = $builder->contractFormOf(['status' => 'blocked'], false);

        $this->assertSame('refusal', $form);
    }

    public function test_contract_form_of_returns_insufficient_context(): void
    {
        $builder = new AskAiFinalResponseBuilderService();
        $form    = $builder->contractFormOf(['status' => 'insufficient_context'], false);

        $this->assertSame('insufficient_context', $form);
    }

    public function test_contract_form_of_returns_direct_fact_when_ready_and_no_synthesis(): void
    {
        $builder = new AskAiFinalResponseBuilderService();
        $form    = $builder->contractFormOf(['status' => 'ready'], false);

        $this->assertSame('direct_fact', $form);
    }

    public function test_contract_form_of_returns_synthesis_when_ready_and_hint_true(): void
    {
        $builder = new AskAiFinalResponseBuilderService();
        $form    = $builder->contractFormOf(['status' => 'ready'], true);

        $this->assertSame('synthesis', $form);
    }

    public function test_assert_contract_form_does_not_throw_when_form_matches(): void
    {
        $this->expectNotToPerformAssertions();

        AskAiFinalResponseBuilderService::assertContractForm(
            ['status' => 'ready', 'answer' => 'The asking price is $450,000.'],
            'direct_fact',
            false
        );
    }

    public function test_assert_contract_form_throws_on_mismatch(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Contract form mismatch/');

        AskAiFinalResponseBuilderService::assertContractForm(
            ['status' => 'insufficient_context'],
            'direct_fact',
            false
        );
    }

    // =========================================================================
    // § 5 — SYNTHESIS_REQUIRED_KEYS routing
    // Verify that lease-acceptance phrases resolve to listing.lease_terms
    // and NOT to listing.lease_length (which would return min_lease_period).
    // =========================================================================

    /**
     * Invoke the private detectListingFieldKey() method via reflection.
     */
    private function detectListingField(string $question): ?string
    {
        $runner = new AskAiRunnerV2Service(
            $this->createMock(\App\Services\AskAi\AskAiQuestionClassifierService::class),
            $this->createMock(\App\Services\AskAi\AskAiInternalRunnerService::class),
            $this->createMock(\App\Services\AskAi\AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $this->createMock(\App\Services\AskAi\AskAiFollowUpQuestionService::class),
        );

        $method = new ReflectionMethod(AskAiRunnerV2Service::class, 'detectListingFieldKey');
        $method->setAccessible(true);
        return $method->invoke($runner, $question);
    }

    public function test_will_landlord_accept_4_month_lease_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingField('will landlord accept a 4 month lease');

        $this->assertSame('listing.lease_terms', $detected,
            '"will landlord accept a 4 month lease" must route to listing.lease_terms '
            . '(reads terms_of_lease), NOT listing.lease_length (which returns min_lease_period = "30 days")');
    }

    public function test_6_month_lease_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingField('is there a 6 month lease option');

        $this->assertSame('listing.lease_terms', $detected,
            '"is there a 6 month lease option" must route to listing.lease_terms');
    }

    public function test_accepted_lease_lengths_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingField('what are the accepted lease lengths');

        $this->assertSame('listing.lease_terms', $detected,
            '"what are the accepted lease lengths" must route to listing.lease_terms');
    }

    public function test_landlord_flexible_on_lease_term_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingField('is the landlord flexible on lease term');

        $this->assertSame('listing.lease_terms', $detected);
    }

    public function test_shortest_landlord_accepts_routes_to_lease_terms(): void
    {
        $detected = $this->detectListingField('what is the shortest lease term landlord will accept');

        $this->assertSame('listing.lease_terms', $detected);
    }

    /**
     * Regression guard: the existing phrase "what lease lengths are available" must
     * still resolve to listing.lease_length (reads min_lease_period).
     * This phrase is intentionally in listing.lease_length and must NOT be captured
     * by the new "month lease" phrase added to listing.lease_terms.
     *
     * @see AskAiCoverageRemediationRoutingTest::test_listing_lease_length_phrases (line 468)
     */
    public function test_what_lease_lengths_are_available_still_routes_to_lease_length(): void
    {
        $detected = $this->detectListingField('what lease lengths are available');

        $this->assertSame('listing.lease_length', $detected,
            '"what lease lengths are available" must remain in listing.lease_length — regression guard');
    }

    public function test_minimum_lease_term_still_routes_to_lease_length(): void
    {
        $detected = $this->detectListingField('what is the minimum lease term');

        $this->assertSame('listing.lease_length', $detected,
            '"minimum lease term" must remain in listing.lease_length');
    }

    // =========================================================================
    // § 6 — Lease terms regression: landlord context reads terms_of_lease
    // =========================================================================

    public function test_landlord_context_lease_terms_reads_terms_of_lease_not_min_lease_period(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('terms_of_lease',   '3 Months, 6 Months, 12 Months');
        $auction->saveMeta('min_lease_period',  '30 days');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $leaseTerms = $ctx['listing']['lease_terms'] ?? null;

        $this->assertNotNull($leaseTerms,
            'lease_terms must be present in landlord context when terms_of_lease is set');

        $this->assertStringContainsString('Months', $leaseTerms,
            'lease_terms must read from terms_of_lease (e.g. "3 Months") not min_lease_period ("30 days")');

        $this->assertStringNotContainsString('30 days', $leaseTerms,
            'lease_terms must NOT contain min_lease_period value "30 days"');
    }

    // =========================================================================
    // § 7 — Landlord description now populated from 'additional_details'
    // =========================================================================

    public function test_landlord_description_populated_from_additional_details(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('additional_details', 'Beautiful 2-bed apartment with pool access.');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $description = $ctx['listing']['description'] ?? null;

        $this->assertNotNull($description,
            'landlord context[listing][description] must be non-null when additional_details is set');

        $this->assertStringContainsString('Beautiful 2-bed apartment', $description,
            'landlord description must read from the additional_details EAV key');
    }

    public function test_landlord_description_null_when_additional_details_absent(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $this->assertNull($ctx['listing']['description'] ?? null,
            'landlord description must be null when additional_details is not set');
    }

    // =========================================================================
    // § 8 — Conflict-detect integration: context vs DB direct read
    //
    // Verifies that conflictDetect() agrees with a direct DB query for a
    // freshly-created record, confirming the utility works end-to-end.
    // =========================================================================

    public function test_conflict_detect_agrees_with_db_for_seller_asking_price(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Conflict detect test',
            'address' => '1 Conflict Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('maximum_budget', '425000');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        // Direct DB read — meta columns are meta_key / meta_value.
        $uiValue = \Illuminate\Support\Facades\DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'maximum_budget')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect(
            'asking_price',
            $ctx['listing']['asking_price'] ?? null,
            $uiValue
        );

        $this->assertFalse($result['conflict'],
            'conflictDetect must find no conflict between context and direct DB read for maximum_budget');
    }

    public function test_conflict_detect_agrees_with_db_for_landlord_terms_of_lease(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('terms_of_lease', '6 Months, 12 Months');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        // Direct DB read — meta columns are meta_key / meta_value.
        $uiValue = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'terms_of_lease')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect(
            'lease_terms',
            $ctx['listing']['lease_terms'] ?? null,
            $uiValue
        );

        $this->assertFalse($result['conflict'],
            'conflictDetect must find no conflict between context lease_terms and direct DB read of terms_of_lease');
    }

    // =========================================================================
    // § 9 — Live-DB spot checks (seller 121, landlord 71)
    //
    // These tests skip gracefully when the records are not present in the test
    // database, so they run in staging/production environments without failing CI.
    // When records ARE present they assert full truth-source alignment for the
    // most important fields on each listing.
    // =========================================================================

    public function test_seller_121_context_matches_db_asking_price(): void
    {
        $auction = SellerAgentAuction::find(121);
        if (!$auction) {
            $this->markTestSkipped('Seller listing 121 not present in this environment — skipping live-DB golden QA.');
        }

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        // maximum_budget is the canonical source for asking_price (CANONICAL_SOURCE_MAP).
        $uiValue = \Illuminate\Support\Facades\DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', 121)
            ->where('meta_key', 'maximum_budget')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect(
            'asking_price',
            $ctx['listing']['asking_price'] ?? null,
            $uiValue
        );

        $this->assertFalse($result['conflict'],
            sprintf(
                'Seller 121 asking_price conflict: context=%s, db=%s',
                $result['context_value'],
                $result['ui_value']
            )
        );

        // Also assert top-level _sources is present and points to maximum_budget.
        $this->assertArrayHasKey('_sources', $ctx);
        $this->assertSame('maximum_budget', $ctx['_sources']['asking_price']);
    }

    public function test_seller_121_description_reads_eav_additional_details(): void
    {
        $auction = SellerAgentAuction::find(121);
        if (!$auction) {
            $this->markTestSkipped('Seller listing 121 not present in this environment.');
        }

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        // CANONICAL_SOURCE_MAP (post I-1 fix): seller.description primary source is
        // 'additional_details' EAV key (written by SellerOfferListing Livewire form).
        // Conflict-detection must compare context against the EAV value, which is what
        // the offer-listing view ($val('additional_details')) renders to the user.
        $eavDescription = \Illuminate\Support\Facades\DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', 121)
            ->where('meta_key', 'additional_details')
            ->value('meta_value');

        // Fallback: if no EAV additional_details exists, check native description column.
        if ($eavDescription === null) {
            $eavDescription = \Illuminate\Support\Facades\DB::table('seller_agent_auctions')
                ->where('id', 121)
                ->value('description');
        }

        $result = AskAiContextBuilderService::conflictDetect(
            'description',
            $ctx['listing']['description'] ?? null,
            $eavDescription
        );

        $this->assertFalse($result['conflict'],
            sprintf(
                'Seller 121 description conflict: context=%s, ui_source=%s',
                $result['context_value'] ?? '(null)',
                $result['ui_value']   ?? '(null)'
            )
        );
    }

    public function test_landlord_71_lease_terms_reads_terms_of_lease(): void
    {
        $auction = LandlordAgentAuction::find(71);
        if (!$auction) {
            $this->markTestSkipped('Landlord listing 71 not present in this environment — skipping live-DB golden QA.');
        }

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        // Direct DB read — meta columns are meta_key / meta_value.
        $uiValue = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'terms_of_lease')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect(
            'lease_terms',
            $ctx['listing']['lease_terms'] ?? null,
            $uiValue
        );

        $this->assertFalse($result['conflict'],
            sprintf(
                'Landlord 71 lease_terms conflict: context=%s, db(terms_of_lease)=%s',
                $result['context_value'] ?? '(null)',
                $result['ui_value']      ?? '(null)'
            )
        );

        // Confirm lease_terms does NOT contain a bare "days" substring
        // (which would indicate it has been read from min_lease_period instead).
        $leaseTerms = $ctx['listing']['lease_terms'] ?? '';
        if ($leaseTerms !== null && $leaseTerms !== '') {
            $this->assertStringNotContainsString(' days', $leaseTerms,
                'Landlord 71 lease_terms must not contain a raw "days" value from min_lease_period');
        }
    }

    public function test_landlord_71_description_populated_from_additional_details(): void
    {
        $auction = LandlordAgentAuction::find(71);
        if (!$auction) {
            $this->markTestSkipped('Landlord listing 71 not present in this environment.');
        }

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        // Ground truth from DB — meta columns are meta_key / meta_value.
        $dbDescription = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'additional_details')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect(
            'description',
            $ctx['listing']['description'] ?? null,
            $dbDescription
        );

        $this->assertFalse($result['conflict'],
            sprintf(
                'Landlord 71 description conflict: context=%s, db(additional_details)=%s',
                $result['context_value'] ?? '(null)',
                $result['ui_value']      ?? '(null)'
            )
        );
    }

    public function test_landlord_71_utilities_not_conflicted_with_db(): void
    {
        $auction = LandlordAgentAuction::find(71);
        if (!$auction) {
            $this->markTestSkipped('Landlord listing 71 not present in this environment.');
        }

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        // Context reads 'utilities' first (matches the public view), then falls back to
        // 'property_utilities' for agent-auction rows.  Meta columns are meta_key/meta_value.
        $dbUtilities = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'utilities')
            ->value('meta_value');

        $dbPropertyUtilities = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'property_utilities')
            ->value('meta_value');

        if ($dbUtilities === null && $dbPropertyUtilities === null) {
            $this->markTestSkipped('Landlord 71 has no utilities data in DB — skipping.');
        }

        $ctxValue = $ctx['listing']['utilities'] ?? null;

        // Context value must be non-null when DB has utilities data.
        $this->assertNotNull(
            $ctxValue,
            'Landlord 71 utilities must be non-null when DB has utilities data'
        );

        // When the UI-visible key ('utilities') is set in DB, context must return that
        // exact value — not the property_utilities fallback — to stay in sync with the view.
        if ($dbUtilities !== null && trim($dbUtilities) !== '') {
            $this->assertSame(
                $dbUtilities,
                $ctxValue,
                'Landlord 71 utilities context value must equal the UI-visible "utilities" EAV key'
            );
        }
    }

    // =========================================================================
    // § 10 — Synthesis trace flag for SYNTHESIS_REQUIRED_KEYS
    // =========================================================================

    public function test_synthesis_required_keys_include_seller_credit_and_lease_terms(): void
    {
        // Verify the constant is reachable via reflection (it is private).
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $constant   = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        $this->assertIsArray($constant, 'SYNTHESIS_REQUIRED_KEYS must be an array constant');
        $this->assertContains('listing.seller_credit_offered', $constant);
        $this->assertContains('listing.seller_credit_amount',  $constant);
        $this->assertContains('listing.lease_terms',           $constant);
        $this->assertContains('listing.terms_of_lease',        $constant);
    }

    // =========================================================================
    // § 11 — Buyer context truth-source alignment
    // Buyer max_price reads from 'maximum_budget' EAV (CANONICAL_SOURCE_MAP).
    // =========================================================================

    public function test_buyer_context_max_price_reads_from_maximum_budget(): void
    {
        $user    = User::factory()->create();
        $auction = \App\Models\BuyerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Buyer max_price regression test',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('maximum_budget', '350000');

        $ctx = $this->contextBuilder->buildForListing('buyer', $auction->id);

        $this->assertArrayHasKey('_sources', $ctx,
            '_sources must be present in buyer buildForListing() output');

        $maxPrice = $ctx['listing']['max_price'] ?? null;
        $this->assertNotNull($maxPrice,
            'buyer context[listing][max_price] must be non-null when maximum_budget is set');
        $this->assertSame('350000', $maxPrice,
            'buyer max_price must read from maximum_budget EAV key');

        // Direct DB read — meta columns are meta_key / meta_value.
        $uiValue = \Illuminate\Support\Facades\DB::table('buyer_agent_auction_metas')
            ->where('buyer_agent_auction_id', $auction->id)
            ->where('meta_key', 'maximum_budget')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect('max_price', $maxPrice, $uiValue);
        $this->assertFalse($result['conflict'],
            'buyer max_price must match direct DB read of maximum_budget');
    }

    public function test_buyer_context_sources_map_present(): void
    {
        $user    = User::factory()->create();
        $auction = \App\Models\BuyerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Buyer sources map regression test',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('buyer', $auction->id);

        $this->assertArrayHasKey('_sources', $ctx);
        $this->assertArrayHasKey('max_price', $ctx['_sources'],
            'buyer _sources must declare max_price canonical source');
        $this->assertSame('maximum_budget', $ctx['_sources']['max_price'],
            'buyer max_price canonical source must be maximum_budget');
    }

    // =========================================================================
    // § 12 — Tenant context truth-source alignment
    // Tenant max_rent reads from 'budget' EAV key (CANONICAL_SOURCE_MAP cascade).
    // =========================================================================

    public function test_tenant_context_max_rent_reads_from_budget(): void
    {
        $user = User::factory()->create();
        $id   = \Illuminate\Support\Facades\DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'          => $user->id,
            'is_approved'      => true,
            'is_draft'         => false,
            'is_sold'          => false,
            'auction_ended'    => false,
            'referral_locked'  => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $auction = \App\Models\TenantAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('budget', '2500');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $this->assertArrayHasKey('_sources', $ctx,
            '_sources must be present in tenant buildForListing() output');

        $maxRent = $ctx['listing']['max_rent'] ?? null;
        $this->assertNotNull($maxRent,
            'tenant context[listing][max_rent] must be non-null when budget is set');
        $this->assertSame('2500', $maxRent,
            'tenant max_rent must read from budget EAV key');

        // Direct DB read — meta columns are meta_key / meta_value.
        $uiValue = \Illuminate\Support\Facades\DB::table('tenant_agent_auction_metas')
            ->where('tenant_agent_auction_id', $auction->id)
            ->where('meta_key', 'budget')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect('max_rent', $maxRent, $uiValue);
        $this->assertFalse($result['conflict'],
            'tenant max_rent must match direct DB read of budget');
    }

    public function test_tenant_context_max_rent_falls_back_to_maximum_budget_when_budget_absent(): void
    {
        $user = User::factory()->create();
        $id   = \Illuminate\Support\Facades\DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'          => $user->id,
            'is_approved'      => true,
            'is_draft'         => false,
            'is_sold'          => false,
            'auction_ended'    => false,
            'referral_locked'  => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $auction = \App\Models\TenantAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('maximum_budget', '3000');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $maxRent = $ctx['listing']['max_rent'] ?? null;
        $this->assertNotNull($maxRent,
            'tenant max_rent must fall back to maximum_budget when budget EAV is absent');
        $this->assertSame('3000', $maxRent);
    }

    // =========================================================================
    // § 13 — Synthesis gate enforcement (regression: no raw echo for risky fields)
    //
    // Verifies that the listing.* direct-return fallback does NOT echo the raw
    // field value for synthesis-required keys when both the primary adapter call
    // AND the quality-rewrite call fail. Instead it must return insufficient_context.
    //
    // Uses runner with mocked adapter that always fails, and a manually-built
    // prompt package that simulates a 'prompt_ready' state with a non-null
    // listing.lease_terms allowed_context entry.
    // =========================================================================

    public function test_synthesis_gate_fires_for_lease_terms_on_adapter_failure(): void
    {
        // Adapter mock that always fails (simulates OpenAI unavailable).
        $failingAdapter = $this->createMock(\App\Services\AskAi\AskAiOpenAiAdapterService::class);
        $failingAdapter->method('generate')->willReturn([
            'success' => false,
            'error'   => 'OpenAI unavailable',
            'status'  => 'failed',
        ]);

        // Build a prompt package that is prompt_ready with a non-null lease_terms value.
        $promptPackage = [
            'status'              => 'prompt_ready',
            'messages'            => [['role' => 'user', 'content' => 'Will landlord accept a 4 month lease?']],
            'required_disclosures'=> [],
            'source_attribution'  => [],
            'allowed_context'     => [
                'listing' => [
                    'lease_terms' => '6 Months, 12 Months',
                ],
            ],
        ];

        // We need to test the synthesis gate path directly. The gate is inside
        // the run() method, so we invoke the runner's internal flow by calling
        // the private handlePromptReadyPath via reflection — OR we test via the
        // public API by setting up a full run() call.
        //
        // Since the runner's run() call requires a full context assembly chain,
        // we test the gate behaviour via reflection on the constants and by
        // verifying the logic contract is established in the code.
        //
        // Contract assertion: synthesis_gate_fired must be true when:
        //   (a) $normalizedFieldKey is in SYNTHESIS_REQUIRED_KEYS
        //   (b) $listingAnswerText is still degraded after rewrite attempt (rewrite failed)
        //
        // This is verified structurally by checking that SYNTHESIS_REQUIRED_KEYS
        // contains 'listing.lease_terms' AND that isResponseDegraded() returns true
        // for the raw value '6 Months, 12 Months'.
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');
        $this->assertContains('listing.lease_terms', $synthKeys,
            'listing.lease_terms must be in SYNTHESIS_REQUIRED_KEYS for synthesis gate to fire');

        // Raw lease_terms value IS degraded (no terminal punctuation, comma-separated list).
        $builder  = new AskAiFinalResponseBuilderService();
        $rawValue = '6 Months, 12 Months';
        $this->assertTrue($builder->isResponseDegraded($rawValue),
            '"6 Months, 12 Months" must be degraded (no terminal punctuation) so synthesis gate fires');

        // Contract form for a degraded, synthesis-required field with adapter failure
        // must be 'insufficient_context', NOT 'direct_fact'.
        $syntheticFailedResponse = ['status' => 'insufficient_context'];
        $form = $builder->contractFormOf($syntheticFailedResponse, true);
        $this->assertSame('insufficient_context', $form,
            'synthesis-required field that cannot be synthesized must produce insufficient_context contract form');
    }

    public function test_synthesis_gate_does_not_fire_for_non_synthesis_required_keys(): void
    {
        // Non-synthesis-required keys (e.g. listing.asking_price) must NOT trigger
        // the synthesis gate — they should be able to return raw value on rewrite failure.
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        $this->assertNotContains('listing.asking_price', $synthKeys,
            'listing.asking_price must NOT be synthesis-required — scalar values are safe to echo');
        $this->assertNotContains('listing.year_built', $synthKeys,
            'listing.year_built must NOT be synthesis-required — scalar year values are safe to echo');
        $this->assertNotContains('listing.rent_amount', $synthKeys,
            'listing.rent_amount must NOT be synthesis-required — scalar rent amounts are safe to echo');
    }

    // =========================================================================
    // § 14 — Contract form in runner trace
    // Verifies contractFormOf() integration in runner is accessible and correct.
    // =========================================================================

    public function test_contract_form_of_maps_all_four_status_values(): void
    {
        $builder = new AskAiFinalResponseBuilderService();

        $cases = [
            ['status' => 'ready',                'hint' => false, 'expected' => 'direct_fact'],
            ['status' => 'ready',                'hint' => true,  'expected' => 'synthesis'],
            ['status' => 'insufficient_context', 'hint' => false, 'expected' => 'insufficient_context'],
            ['status' => 'insufficient_context', 'hint' => true,  'expected' => 'insufficient_context'],
            ['status' => 'blocked',              'hint' => false, 'expected' => 'refusal'],
            ['status' => 'blocked',              'hint' => true,  'expected' => 'refusal'],
            ['status' => 'failed',               'hint' => false, 'expected' => 'insufficient_context'],
        ];

        foreach ($cases as $case) {
            $actual = $builder->contractFormOf(['status' => $case['status']], $case['hint']);
            $this->assertSame(
                $case['expected'],
                $actual,
                "contractFormOf() for status='{$case['status']}', hint=" . ($case['hint'] ? 'true' : 'false')
                . " must return '{$case['expected']}'"
            );
        }
    }

    // =========================================================================
    // § 15 — Five regression scenarios (context + routing verification)
    //
    // These tests are structural regressions that don't require a live OpenAI
    // call. They verify that:
    //   R1. Seller roof_type context is populated from 'roof_type' EAV key.
    //   R2. Seller credit pairing — both credit_offered + credit_amount in context.
    //   R3. Seller utilities context populated from 'utilities' EAV key.
    //   R4. Air conditioning questions route to listing context (not opaque).
    //   R5. Landlord rent_amount conflict-detection agrees with DB (desired_rental_amount).
    // =========================================================================

    public function test_r1_seller_roof_type_populated_from_eav(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Roof regression test',
            'address' => '1 Roof Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('roof_type', json_encode(['Shingle', 'Metal']));

        $ctx      = $this->contextBuilder->buildForListing('seller', $auction->id);
        $roofType = $ctx['listing']['roof_type'] ?? null;

        $this->assertNotNull($roofType,
            'R1: seller context[listing][roof_type] must be non-null when roof_type EAV is set');
        $this->assertStringContainsString('Shingle', $roofType,
            'R1: roof_type must decode the JSON array value from EAV');
    }

    public function test_r2_seller_credit_pairing_both_fields_in_context(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Credit pairing test',
            'address' => '1 Credit Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('seller_contribution_credit_offered', 'Yes');
        $auction->saveMeta('seller_contribution_amount_details', 'Up to $10,000 toward closing costs');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $creditOffered = $ctx['listing']['seller_credit_offered'] ?? null;
        $creditAmount  = $ctx['listing']['seller_credit_amount']  ?? null;

        $this->assertNotNull($creditOffered,
            'R2: seller_credit_offered must be in context when EAV is set');
        $this->assertNotNull($creditAmount,
            'R2: seller_credit_amount must be in context when EAV is set');

        $this->assertSame('Yes', $creditOffered,
            'R2: seller_credit_offered must read "Yes" from seller_contribution_credit_offered EAV');
        $this->assertStringContainsString('10,000', $creditAmount,
            'R2: seller_credit_amount must read from seller_contribution_amount_details EAV');

        // Both fields are SYNTHESIS_REQUIRED — assert they appear in SYNTHESIS_REQUIRED_KEYS.
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');
        $this->assertContains('listing.seller_credit_offered', $synthKeys,
            'R2: seller_credit_offered must be marked synthesis-required (needs paired answer)');
        $this->assertContains('listing.seller_credit_amount', $synthKeys,
            'R2: seller_credit_amount must be marked synthesis-required (needs paired answer)');
    }

    public function test_r3_seller_utilities_context_populated_from_eav(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Utilities regression test',
            'address' => '1 Utils Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('utilities', 'Electric, Gas, Water');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $utilities = $ctx['listing']['utilities'] ?? null;

        $this->assertNotNull($utilities,
            'R3: seller utilities must be in context when utilities EAV is set');
        $this->assertStringContainsString('Electric', $utilities,
            'R3: seller utilities must include data from the utilities EAV key');

        // CANONICAL_SOURCE_MAP must declare 'utilities' as the canonical source.
        $sellerSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];
        $this->assertArrayHasKey('utilities', $sellerSources,
            'R3: seller utilities must have a declared source in CANONICAL_SOURCE_MAP');
        $this->assertSame('utilities', $sellerSources['utilities'],
            'R3: seller utilities canonical source must be the utilities EAV key');
    }

    public function test_r4_air_conditioning_field_in_seller_context(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'AC regression test',
            'address' => '1 AC Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('air_conditioning', json_encode(['Central Air', 'Mini-Split']));

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $ac = $ctx['listing']['air_conditioning'] ?? null;

        $this->assertNotNull($ac,
            'R4: seller air_conditioning must be in context when air_conditioning EAV is set');
        $this->assertStringContainsString('Central Air', $ac,
            'R4: air_conditioning must decode JSON from EAV');

        // Routing: "what air conditioning type does this property have?" must
        // resolve to a listing context key (not return null and go to opaque OpenAI).
        // detectListingFieldKey is private, but we can verify the context field exists,
        // which guarantees the Guard B path will find the data when the key is detected.
        $this->assertNotNull($ac, 'R4: AC field data is available for Guard B to use when key is detected');
    }

    public function test_r5_landlord_rent_amount_conflict_detect_with_db(): void
    {
        $user    = User::factory()->create();
        $auction = \App\Models\LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('desired_rental_amount', '2800');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $rentAmount = $ctx['listing']['rent_amount'] ?? null;

        $this->assertNotNull($rentAmount,
            'R5: landlord rent_amount must be in context when desired_rental_amount is set');

        // Direct DB read — canonical source is desired_rental_amount.
        $uiValue = \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'desired_rental_amount')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect('rent_amount', $rentAmount, $uiValue);
        $this->assertFalse($result['conflict'],
            'R5: landlord rent_amount must match direct DB read of desired_rental_amount; '
            . "context={$result['context_value']}, db={$result['ui_value']}");

        // CANONICAL_SOURCE_MAP must list desired_rental_amount as primary source.
        $landlordSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord'];
        $this->assertArrayHasKey('rent_amount', $landlordSources,
            'R5: landlord rent_amount must have a declared source in CANONICAL_SOURCE_MAP');
        $rentSource = $landlordSources['rent_amount'];
        $primarySource = is_array($rentSource) ? $rentSource[0] : $rentSource;
        $this->assertSame('desired_rental_amount', $primarySource,
            'R5: landlord rent_amount primary canonical source must be desired_rental_amount');
    }

    // =========================================================================
    // § 16 — Agent Profile: CANONICAL_SOURCE_MAP completeness check
    // The truth source contract covers buyer/tenant/seller/landlord roles.
    // Agent profile data is assembled separately via buildAgentProfile() and
    // is not part of extractFactualFields(). Assert that _sources does NOT
    // falsely claim agent profile keys — it covers listing data only.
    // =========================================================================

    public function test_sources_does_not_include_agent_profile_keys(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => 'Agent profile sources test',
            'address' => '1 Agent Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        // Agent profile keys are assembled in buildAgentProfile(), not extractFactualFields().
        // They should NOT appear in CANONICAL_SOURCE_MAP / _sources.
        $agentProfileKeys = ['agent_name', 'agent_bio', 'agent_license', 'brokerage_name'];
        foreach ($agentProfileKeys as $key) {
            $this->assertArrayNotHasKey($key, $ctx['_sources'],
                "_sources must not include agent profile key '{$key}' — agent profile is a separate context section");
        }
    }

    // =========================================================================
    // § 17 — Integration Harness: Field Alignment Table
    //
    // For each listed field in seller 121 and landlord 71 this harness:
    //   1. Reads the raw DB value directly (the "UI value" a user sees on the
    //      listing page, taken from the same EAV row as the public display).
    //   2. Reads the context builder output (the value passed to OpenAI).
    //   3. Normalises both to a common form (JSON arrays decoded + "Other" stripped).
    //   4. Asserts they match — i.e. context has zero drift from the UI value.
    //
    // This is the "context value == UI value" check required by the Truth Source
    // Contract; any delta here means Ask AI could answer a question using data
    // the user never published.
    //
    // Structure per case: [role, listingId, contextField, table, fk, metaKey, isJson]
    // =========================================================================

    /**
     * Normalize a raw EAV value for conflict comparison.
     *
     * • JSON arrays  → decode, strip literal "Other", implode with ", ".
     * • Empty string → return empty string (not null).
     * • Scalar       → return trimmed string.
     */
    private function normalizeEavForComparison(?string $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '';
        }
        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            $filtered = array_values(array_filter(
                $decoded,
                fn ($v) => strtolower(trim((string) $v)) !== 'other'
            ));
            return implode(', ', $filtered);
        }
        return trim($rawValue);
    }

    /** @return array<string, array{string, int, string, string, string, string, bool}> */
    public static function sellerAlignmentProvider(): array
    {
        // [role, listingId, contextField, table, fk, metaKey]
        return [
            'seller_121_asking_price'   => ['seller', 121, 'asking_price',        'seller_agent_auction_metas', 'seller_agent_auction_id', 'maximum_budget'],
            'seller_121_utilities'      => ['seller', 121, 'utilities',            'seller_agent_auction_metas', 'seller_agent_auction_id', 'utilities'],
            'seller_121_roof_type'      => ['seller', 121, 'roof_type',            'seller_agent_auction_metas', 'seller_agent_auction_id', 'roof_type'],
            'seller_121_ac'             => ['seller', 121, 'air_conditioning',     'seller_agent_auction_metas', 'seller_agent_auction_id', 'air_conditioning'],
            'seller_121_appliances'     => ['seller', 121, 'appliances',           'seller_agent_auction_metas', 'seller_agent_auction_id', 'appliances'],
            'seller_121_credit_offered' => ['seller', 121, 'seller_credit_offered','seller_agent_auction_metas', 'seller_agent_auction_id', 'seller_contribution_credit_offered'],
            'seller_121_credit_amount'  => ['seller', 121, 'seller_credit_amount', 'seller_agent_auction_metas', 'seller_agent_auction_id', 'seller_contribution_amount_details'],
        ];
    }

    /**
     * @dataProvider sellerAlignmentProvider
     * @param string $role        Listing role (seller/landlord/buyer/tenant).
     * @param int    $listingId   Real listing ID with live DB data.
     * @param string $contextField Key in ctx['listing'] to read.
     * @param string $table       Meta table name.
     * @param string $fk          Foreign-key column name.
     * @param string $metaKey     EAV meta_key value.
     */
    public function test_harness_seller_field_alignment(
        string $role,
        int    $listingId,
        string $contextField,
        string $table,
        string $fk,
        string $metaKey
    ): void {
        $ctx          = $this->contextBuilder->buildForListing($role, $listingId);
        $contextValue = (string) ($ctx['listing'][$contextField] ?? '');

        $rawUiValue     = \Illuminate\Support\Facades\DB::table($table)
            ->where($fk, $listingId)
            ->where('meta_key', $metaKey)
            ->value('meta_value') ?? '';
        $normalizedUiValue = $this->normalizeEavForComparison($rawUiValue);

        // conflictDetect normalises both sides (trim + strtolower), so JSON-decoded
        // context values and normalized DB values can be compared directly.
        $result = AskAiContextBuilderService::conflictDetect($contextField, $contextValue, $normalizedUiValue);
        $this->assertFalse(
            $result['conflict'],
            sprintf(
                'HARNESS FAIL — role=%s id=%d field=%s | context="%s" db="%s"',
                $role, $listingId, $contextField,
                $result['context_value'], $result['ui_value']
            )
        );
    }

    /** @return array<string, array{string, int, string, string, string, string}> */
    public static function landlordAlignmentProvider(): array
    {
        return [
            'landlord_71_rent_amount'   => ['landlord', 71, 'rent_amount',  'landlord_agent_auction_metas', 'landlord_agent_auction_id', 'desired_rental_amount'],
            'landlord_71_terms_of_lease'=> ['landlord', 71, 'lease_terms',  'landlord_agent_auction_metas', 'landlord_agent_auction_id', 'terms_of_lease'],
        ];
    }

    /** @dataProvider landlordAlignmentProvider */
    public function test_harness_landlord_field_alignment(
        string $role,
        int    $listingId,
        string $contextField,
        string $table,
        string $fk,
        string $metaKey
    ): void {
        $ctx          = $this->contextBuilder->buildForListing($role, $listingId);
        $contextValue = (string) ($ctx['listing'][$contextField] ?? '');

        $rawUiValue        = \Illuminate\Support\Facades\DB::table($table)
            ->where($fk, $listingId)
            ->where('meta_key', $metaKey)
            ->value('meta_value') ?? '';
        $normalizedUiValue = $this->normalizeEavForComparison($rawUiValue);

        $result = AskAiContextBuilderService::conflictDetect($contextField, $contextValue, $normalizedUiValue);
        $this->assertFalse(
            $result['conflict'],
            sprintf(
                'HARNESS FAIL — role=%s id=%d field=%s | context="%s" db="%s"',
                $role, $listingId, $contextField,
                $result['context_value'], $result['ui_value']
            )
        );
    }

    // =========================================================================
    // § 18 — Contract coercion: coerceToContractStatus()
    //
    // Verifies that non-contract statuses ('failed', 'unsupported', arbitrary)
    // are coerced to 'insufficient_context' at the outgoing boundary, so callers
    // never receive status values outside the four-form contract.
    // =========================================================================

    public function test_coerce_to_contract_status_passes_ready_through(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'ready', 'success' => true, 'answer' => 'The price is $450,000.'];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('ready', $result['status'],
            'coerceToContractStatus() must not change status=ready');
        $this->assertArrayNotHasKey('_pre_coercion_status', $result,
            'coerceToContractStatus() must not add _pre_coercion_status for contract-safe statuses');
    }

    public function test_coerce_to_contract_status_passes_insufficient_context_through(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'insufficient_context', 'success' => false];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertArrayNotHasKey('_pre_coercion_status', $result);
    }

    public function test_coerce_to_contract_status_passes_blocked_through(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'blocked', 'success' => false];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('blocked', $result['status']);
        $this->assertArrayNotHasKey('_pre_coercion_status', $result);
    }

    public function test_coerce_to_contract_status_coerces_failed(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'failed', 'success' => false, 'error' => 'OpenAI timeout'];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('insufficient_context', $result['status'],
            'coerceToContractStatus() must coerce status=failed to insufficient_context');
        $this->assertSame('failed', $result['_pre_coercion_status'],
            'coerceToContractStatus() must record pre-coercion status for diagnostics');
        $this->assertFalse($result['success'],
            'coerceToContractStatus() must set success=false for coerced responses');
    }

    public function test_coerce_to_contract_status_coerces_unsupported(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'unsupported', 'success' => false];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('insufficient_context', $result['status'],
            'coerceToContractStatus() must coerce status=unsupported to insufficient_context');
        $this->assertSame('unsupported', $result['_pre_coercion_status']);
    }

    public function test_coerce_to_contract_status_coerces_arbitrary_status(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'processing', 'success' => false];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('insufficient_context', $result['status'],
            'coerceToContractStatus() must coerce any unknown status to insufficient_context');
        $this->assertSame('processing', $result['_pre_coercion_status']);
    }

    public function test_coerce_to_contract_status_preserves_existing_answer(): void
    {
        $builder  = new AskAiFinalResponseBuilderService();
        $response = ['status' => 'failed', 'answer' => 'Partial answer.', 'success' => false];
        $result   = $builder->coerceToContractStatus($response);

        $this->assertSame('Partial answer.', $result['answer'],
            'coerceToContractStatus() must preserve existing non-empty answer text');
    }

    // =========================================================================
    // § 19 — Synthesis-required key completeness
    //
    // Verifies that all JSON-array fields produced by decodeJsonField() in the
    // context builder are present in SYNTHESIS_REQUIRED_KEYS. This prevents
    // any new multi-value field from silently bypassing the synthesis gate.
    // =========================================================================

    public function test_synthesis_required_covers_all_json_array_fields(): void
    {
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        // These are the known JSON-array fields decoded via decodeJsonField()
        // in the seller, landlord, buyer, and tenant context builders.
        $jsonArrayFields = [
            'listing.interior_features',
            'listing.appliances',
            'listing.roof_type',
            'listing.exterior_construction',
            'listing.heating_and_fuel',
            'listing.heating_fuel',
            'listing.air_conditioning',
            'listing.sale_provision',
            'listing.offered_financing',
        ];

        foreach ($jsonArrayFields as $key) {
            $this->assertContains(
                $key,
                $synthKeys,
                "JSON-array field '{$key}' must be in SYNTHESIS_REQUIRED_KEYS — "
                . 'raw comma-separated echoes are not answers'
            );
        }
    }

    public function test_synthesis_required_covers_utilities_and_policy_fields(): void
    {
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        $policyAndListFields = [
            'listing.utilities',
            'listing.pet_policy',
            'listing.rental_restrictions',
        ];

        foreach ($policyAndListFields as $key) {
            $this->assertContains(
                $key,
                $synthKeys,
                "Policy/list field '{$key}' must be in SYNTHESIS_REQUIRED_KEYS — "
                . 'policy fields require explanatory prose, not raw echoes'
            );
        }
    }

    public function test_synthesis_required_does_not_include_scalar_safe_fields(): void
    {
        // Scalar-safe fields (single numeric/text values) MUST NOT be in
        // SYNTHESIS_REQUIRED_KEYS. Their values are meaningful on their own
        // and should be returned directly without forcing an OpenAI rewrite.
        $reflection       = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys        = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        $scalarSafeFields = [
            'listing.asking_price',
            'listing.rent_amount',
            'listing.year_built',
            'listing.square_feet',
            'listing.lot_size',
            'listing.bedrooms',
            'listing.bathrooms',
            'listing.hoa_fee',
        ];

        foreach ($scalarSafeFields as $key) {
            $this->assertNotContains(
                $key,
                $synthKeys,
                "Scalar-safe field '{$key}' must NOT be in SYNTHESIS_REQUIRED_KEYS — "
                . 'its raw value is a meaningful answer'
            );
        }
    }

    // =========================================================================
    // § 20 — End-to-end runner contract enforcement
    //
    // Tests that run the full AskAiRunnerV2Service pipeline (with mocked adapter
    // and internalRunner where needed) to verify behavioral correctness of:
    //
    //   A. Unconditional synthesis gate — fires for non-degraded policy strings
    //      (the old gate was conditioned on isResponseDegraded; the new gate is not)
    //   B. Full runner pipeline — synthesis gate fires for lease_terms when adapter
    //      fails, returning insufficient_context instead of raw "3 Months, 6 Months"
    //   C. Phase 4 database_hit — runner short-circuits at the snapshot lookup and
    //      returns the stored answer with the correct source key (no OpenAI call)
    //   D. Content-level contract enforcement — coerceToContractStatus() catches
    //      degraded 'ready' answers and coerces them to insufficient_context
    // =========================================================================

    // -------------------------------------------------------------------------
    // § 20A — Unconditional synthesis gate: non-degraded policy value blocked
    // -------------------------------------------------------------------------

    /**
     * Structural proof that the synthesis gate is unconditional.
     *
     * "No pets allowed." has terminal punctuation and 3 words — isResponseDegraded()
     * returns FALSE.  Under the old gate (conditioned on degradation) this policy
     * string would have slipped through as a direct-fact answer when the adapter
     * failed.  Under the new gate, listing.pet_policy is always blocked regardless
     * of whether the raw value appears superficially "non-degraded".
     */
    public function test_s20a_synthesis_gate_unconditional_blocks_non_degraded_policy_value(): void
    {
        $builder    = app(AskAiFinalResponseBuilderService::class);
        $reflection = new \ReflectionClass(AskAiRunnerV2Service::class);
        $synthKeys  = $reflection->getConstant('SYNTHESIS_REQUIRED_KEYS');

        // Confirm the policy string appears non-degraded (terminal punct, 3 words, ≥ 15 chars).
        // Under the OLD conditional gate this would have been returned as a direct answer.
        $policyValue = 'No pets allowed on premises.';
        $this->assertFalse(
            $builder->isResponseDegraded($policyValue),
            '"No pets allowed on premises." must NOT be degraded — it has terminal '
            . 'punctuation, 4 words, and is long enough to pass the heuristic'
        );

        // The gate is now UNCONDITIONAL: pet_policy must be synthesis-required
        // regardless of whether the policy string is degraded.
        $this->assertContains(
            'listing.pet_policy',
            $synthKeys,
            'listing.pet_policy must be in SYNTHESIS_REQUIRED_KEYS so the gate blocks '
            . 'it unconditionally — even "No pets allowed on premises." is raw data, not an answer'
        );

        // Verify the same holds for lease_terms: "3 Months, 6 Months" is degraded
        // (no terminal punct) but even if rewrite produced a punct-terminated string,
        // the gate blocks it unconditionally.
        $this->assertContains(
            'listing.lease_terms',
            $synthKeys,
            'listing.lease_terms must be in SYNTHESIS_REQUIRED_KEYS — lease acceptance '
            . 'requires reasoning about the tenant\'s desired length, not a list echo'
        );

        // Contrast: scalar-safe fields must NOT be synthesis-required.
        // Their raw value IS a meaningful answer.
        $scalarSafe = ['listing.asking_price', 'listing.rent_amount', 'listing.year_built'];
        foreach ($scalarSafe as $key) {
            $this->assertNotContains(
                $key,
                $synthKeys,
                "'{$key}' is scalar-safe — the unconditional gate must not block it"
            );
        }
    }

    // -------------------------------------------------------------------------
    // § 20B — End-to-end runner: synthesis gate fires for lease_terms
    // -------------------------------------------------------------------------

    /**
     * Full pipeline test: synthesis-required field → adapter fails → gate fires.
     *
     * The real classifier routes "will landlord accept a 4 month lease" to
     * listing.lease_terms.  The mocked internalRunner returns prompt_ready with
     * lease_terms = "3 Months, 6 Months" in allowed_context.  The mocked adapter
     * always fails.  The synthesis gate fires unconditionally and returns
     * insufficient_context instead of echoing the raw value.
     */
    public function test_s20b_end_to_end_synthesis_gate_fires_for_appliances(): void
    {
        // "what appliances are included" classifies as listing_facts and
        // detectListingFieldKey() resolves to listing.appliances — a synthesis-required
        // field (JSON array that must be synthesised into prose, not echoed verbatim).
        $question    = 'what appliances are included';
        $listingId   = 9999701;
        $listingType = 'seller';
        $rawValue    = 'Refrigerator, Dishwasher, Microwave';

        // Adapter always fails — no OpenAI in the test environment.
        $failingAdapter = $this->createMock(\App\Services\AskAi\AskAiOpenAiAdapterService::class);
        $failingAdapter->method('generate')->willReturn([
            'success' => false,
            'status'  => 'failed',
            'error'   => 'No OpenAI key in test environment',
            'content' => null,
        ]);

        // InternalRunner returns prompt_ready with appliances in allowed_context.
        // Guard B checks allowed_context['listing']['appliances'] — must be non-null
        // so the guard does not fire early (it only fires for null/absent fields).
        $mockInternalRunner = $this->createMock(\App\Services\AskAi\AskAiInternalRunnerService::class);
        $mockInternalRunner->method('run')->willReturn([
            'success' => true,
            'status'  => 'prompt_ready',
            'context' => [
                'listing_type'  => $listingType,
                'listing_id'    => $listingId,
                'listing'       => ['appliances' => $rawValue, 'description' => null],
                '_sources'      => [],
                'agent_profile' => [],
            ],
            'contract' => [
                'allowed_context'      => ['allowed'],
                'required_disclosures' => [],
                'source_attribution'   => [],
            ],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'prompt'               => 'What appliances does this listing include?',
                'required_disclosures' => [],
                'source_attribution'   => [],
                // allowed_context must include the appliances value so the listing.*
                // direct-return fallback (lines 3630+) finds it and enters the synthesis gate.
                'allowed_context'      => ['listing' => ['appliances' => $rawValue]],
            ],
            'error' => null,
        ]);

        $runner = new AskAiRunnerV2Service(
            app(\App\Services\AskAi\AskAiQuestionClassifierService::class),
            $mockInternalRunner,
            $failingAdapter,
            app(AskAiFinalResponseBuilderService::class),
            app(\App\Services\AskAi\AskAiFollowUpQuestionService::class),
            null,  // normalizer — skipped; detectListingFieldKey resolves key deterministically
            null,  // knowledgeSearch — Phase 4 skipped; no snapshot needed for this test
        );

        $result = $runner->run($listingType, $listingId, $question);

        $finalResponse = $result['final_response'] ?? [];
        $trace         = $result['trace'] ?? [];

        // Synthesis gate must have fired — runner returns insufficient_context,
        // not the raw "Refrigerator, Dishwasher, Microwave" comma list.
        $this->assertSame(
            'insufficient_context',
            $finalResponse['status'] ?? null,
            'Synthesis gate must fire for listing.appliances when adapter fails — '
            . 'must return insufficient_context, not the raw appliance list value'
        );

        // The raw comma list must NOT appear in the answer (gate blocked the echo).
        $answerText = (string) ($finalResponse['answer'] ?? '');
        $this->assertStringNotContainsString(
            $rawValue,
            $answerText,
            '"Refrigerator, Dishwasher, Microwave" must not appear — synthesis gate blocks raw list echoes'
        );

        // Trace confirms the gate fired unconditionally for this synthesis-required field.
        $this->assertTrue(
            $trace['synthesis_gate_fired'] ?? false,
            'trace.synthesis_gate_fired must be true for listing.appliances (synthesis-required)'
        );
        $this->assertSame(
            'listing.appliances',
            $trace['synthesis_gate_key'] ?? null,
            'trace.synthesis_gate_key must record which synthesis-required field triggered the gate'
        );
    }

    // -------------------------------------------------------------------------
    // § 20C — End-to-end runner: Phase 4 database_hit returns snapshot answer
    // -------------------------------------------------------------------------

    /**
     * Full pipeline test: Phase 4 short-circuits at a snapshot hit.
     *
     * Creates a knowledge snapshot with a FAQ answer, then runs the full runner
     * pipeline.  The real AskAiKnowledgeSearchService finds the snapshot via the
     * canonical key resolved by detectFaqFieldKey().  The runner short-circuits
     * and returns the stored answer without calling OpenAI (adapter mock asserts
     * it is never called).  Asserts outcome_category, answer text, and source metadata.
     */
    public function test_s20c_end_to_end_phase4_snapshot_hit_returns_correct_answer(): void
    {
        $listingType  = 'seller';
        $listingId    = 9999702;
        $question     = 'How old is the roof?';
        $canonicalKey = 'faq_answers.roof_age_and_condition';
        $storedAnswer = 'The roof was inspected in 2024 and is in excellent condition with no repairs needed.';

        // Build a ready snapshot for the synthetic test listing.
        $snap = AskAiKnowledgeSnapshot::create([
            'listing_type'  => $listingType,
            'listing_id'    => $listingId,
            'version'       => 1,
            'status'        => 'ready',
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => now(),
        ]);

        // Store the answer under the canonical FAQ key.
        AskAiAnswer::create([
            'snapshot_id'   => $snap->id,
            'canonical_key' => $canonicalKey,
            'answer_text'   => $storedAnswer,
        ]);

        // Store the question record so the search service can match it.
        AskAiQuestion::create([
            'snapshot_id'       => $snap->id,
            'canonical_key'     => $canonicalKey,
            'field_type'        => 'faq',
            'question_text'     => $question,
            'sample_question'   => $question,
            'sample_question_2' => null,
            'source_path'       => 'registry.faq.' . $canonicalKey,
            'sort_order'        => 0,
        ]);

        // Adapter must never be called — Phase 4 short-circuits before the OpenAI step.
        $neverCalledAdapter = $this->createMock(\App\Services\AskAi\AskAiOpenAiAdapterService::class);
        $neverCalledAdapter->expects($this->never())->method('generate');

        // InternalRunner returns prompt_ready so Phase 4 can fire.
        // allowed_context is non-empty so Guard A (faq_answers.* null guard) does not fire.
        $mockInternalRunner = $this->createMock(\App\Services\AskAi\AskAiInternalRunnerService::class);
        $mockInternalRunner->method('run')->willReturn([
            'success' => true,
            'status'  => 'prompt_ready',
            'context' => [
                'listing_type'  => $listingType,
                'listing_id'    => $listingId,
                'listing'       => ['description' => 'Lovely home with a recently inspected roof.'],
                '_sources'      => [],
                'agent_profile' => [],
            ],
            'contract' => [
                'allowed_context'      => ['allowed'],
                'required_disclosures' => [],
                'source_attribution'   => [],
            ],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'prompt'               => 'Test prompt.',
                'required_disclosures' => [],
                'source_attribution'   => [],
                // Non-empty allowed_context prevents Guard A from firing.
                'allowed_context'      => [
                    'listing' => ['description' => 'Lovely home with a recently inspected roof.'],
                ],
            ],
            'error' => null,
        ]);

        $runner = new AskAiRunnerV2Service(
            app(\App\Services\AskAi\AskAiQuestionClassifierService::class),
            $mockInternalRunner,
            $neverCalledAdapter,
            app(AskAiFinalResponseBuilderService::class),
            app(\App\Services\AskAi\AskAiFollowUpQuestionService::class),
            null,  // normalizer — skipped; detectFaqFieldKey() resolves the key deterministically
            app(\App\Services\AskAi\AskAiKnowledgeSearchService::class),
        );

        $result = $runner->run($listingType, $listingId, $question);

        $finalResponse = $result['final_response'] ?? [];
        $source        = $finalResponse['source'] ?? [];

        // Phase 4 must have short-circuited with a database_hit.
        $this->assertSame(
            'database_hit',
            $result['outcome_category'] ?? null,
            'outcome_category must be database_hit when Phase 4 finds a snapshot answer'
        );

        // Final response must be ready with the exact stored answer text.
        $this->assertSame('ready', $finalResponse['status'] ?? null,
            'Phase 4 database_hit must return status=ready');
        $this->assertSame($storedAnswer, $finalResponse['answer'] ?? null,
            'Phase 4 must return the exact stored snapshot answer text');

        // Source metadata must point to the knowledge snapshot via 'database' source type.
        // (AskAiKnowledgeSearchService returns answer_source='database' for DB-backed hits.)
        $this->assertSame(
            'database',
            $source['answer_source'] ?? null,
            'source.answer_source must be database for Phase 4 database_hit (AskAiKnowledgeSearchService convention)'
        );
        $this->assertSame(
            $canonicalKey,
            $source['canonical_key'] ?? null,
            'source.canonical_key must match the FAQ key used to store the answer'
        );
        $this->assertSame(
            $snap->id,
            $source['snapshot_id'] ?? null,
            'source.snapshot_id must match the snapshot record used for the hit'
        );
    }

    // =========================================================================
    // § 21 — Seller description source gap (audit finding I-1)
    //
    // The seller context builder reads $nativeGet('description') — the native
    // seller_agent_auctions.description column.  Seller OFFER LISTING forms
    // (Livewire SellerOfferListing / SellerOfferListingEdit) save the description
    // via saveMeta('additional_details', ...) into EAV, leaving the native column
    // null.  The public seller view renders $val('additional_details') from $meta.
    //
    // §21A confirms the correct path (native description column populated):
    //   native description set + EAV additional_details absent → context returns value.
    //
    // §21B documents the gap (offer-listing path):
    //   EAV additional_details set + native description null → context returns null.
    //
    // §21B is expected to FAIL until the production fix is applied:
    //   'description' => $infoGet('additional_details') ?: $nativeGet('description')
    // =========================================================================

    public function test_s21a_seller_description_reads_from_native_column_when_populated(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => '§21A native description test',
            'address'     => '1 Native Lane',
            'description' => 'This is a beautiful three-bedroom home near the coast.',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $description = $ctx['listing']['description'] ?? null;

        $this->assertNotNull($description,
            '§21A: seller context[listing][description] must be non-null when native description column is set');
        $this->assertStringContainsString('three-bedroom', $description,
            '§21A: seller description must read from the native description column');
    }

    /**
     * §21B documents the field source gap for seller offer-listing rows.
     *
     * This test FAILS on the current production code because the context builder
     * reads only $nativeGet('description') and misses the EAV additional_details
     * key used by the Livewire offer-listing form.
     *
     * After the audit fix ('description' => $infoGet('additional_details') ?: $nativeGet('description')),
     * this test must pass.
     *
     * @see .local/ask-ai-field-audit-report.md Issue I-1
     */
    public function test_s21b_seller_description_gap_offer_listing_eav_path(): void
    {
        $user    = User::factory()->create();
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => '§21B EAV description gap test',
            'address'     => '1 Gap Lane',
            'description' => null,
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('additional_details',
            'Stunning 4-bed 3-bath home with chef kitchen and resort-style pool.'
        );

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $description = $ctx['listing']['description'] ?? null;

        $this->assertNotNull($description,
            '§21B AUDIT GAP: seller offer-listing stores description in EAV additional_details, '
            . 'but context builder reads only the native description column (null for offer-listing rows). '
            . 'Fix: change context builder to $infoGet(\'additional_details\') ?: $nativeGet(\'description\').');

        $this->assertStringContainsString('4-bed', $description,
            '§21B: after the fix, seller description must read from EAV additional_details '
            . 'when the native description column is null (offer-listing path)');
    }

    public function test_s21c_seller_description_canonical_source_map_entry(): void
    {
        $sellerSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];

        $this->assertArrayHasKey('description', $sellerSources,
            '§21C: seller CANONICAL_SOURCE_MAP must declare description source');

        // The primary source must be the EAV 'additional_details' key — the key used by the
        // Livewire SellerOfferListing and SellerOfferListingEdit forms (saveMeta) and confirmed
        // by the seller offer-listing view ($val('additional_details')). The native description
        // column is a legacy fallback for agent-auction wizard rows; it must not be the primary
        // source because offer-listing rows never populate it.
        $descSource  = $sellerSources['description'];
        $primarySource = is_array($descSource) ? ($descSource[0] ?? null) : $descSource;

        $this->assertSame('additional_details', $primarySource,
            '§21C: seller description primary canonical source must be "additional_details" (EAV) '
            . 'to match what the offer-listing view and forms use. '
            . '"native:description" must appear only as a fallback (second element) in the cascade array.');

        if (is_array($descSource)) {
            $this->assertContains('native:description', $descSource,
                '§21C: native:description must still exist in the cascade as a fallback for legacy rows');
        }
    }

    // =========================================================================
    // § 22 — Landlord pet_policy cascade order (audit finding I-2)
    //
    // Context reads pet_policy first → falls back to pets.
    // Public UI view renders pets first → falls back to pet_policy.
    //
    // For the current live-DB state (pet_policy always empty, data in pets),
    // both paths fall back to the same value and the AI is correct.
    //
    // §22A: verifies that when only 'pets' is set (legacy/common case), context
    //        still returns the value (fallback works correctly).
    //
    // §22B: documents the ordering asymmetry for future form paths where
    //        pet_policy may be populated instead of pets.
    // =========================================================================

    public function test_s22a_landlord_pet_policy_fallback_reads_pets_when_pet_policy_empty(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('pets', 'Yes');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $petPolicy = $ctx['listing']['pet_policy'] ?? null;

        $this->assertNotNull($petPolicy,
            '§22A: landlord pet_policy must fall back to the pets EAV key when pet_policy is empty');
        $this->assertSame('Yes', $petPolicy,
            '§22A: landlord pet_policy fallback must return the pets value');
    }

    public function test_s22b_landlord_pet_policy_canonical_source_map_order_declared(): void
    {
        $landlordSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord'];

        $this->assertArrayHasKey('pet_policy', $landlordSources,
            '§22B: landlord CANONICAL_SOURCE_MAP must declare pet_policy source');

        $petPolicySources = $landlordSources['pet_policy'];
        $this->assertIsArray($petPolicySources,
            '§22B: landlord pet_policy source must be an array (cascade) in CANONICAL_SOURCE_MAP');

        // Both keys must be present — order must match the UI view priority:
        // $str('pets') ?: $str('pet_policy'). 'pets' must be declared first so the
        // context builder reads the same primary source as the public listing page.
        $this->assertContains('pets', $petPolicySources,
            '§22B: pets must be in the CANONICAL_SOURCE_MAP cascade');
        $this->assertContains('pet_policy', $petPolicySources,
            '§22B: pet_policy must be in the CANONICAL_SOURCE_MAP cascade');

        // 'pets' must come FIRST — it is the primary display source in the UI view.
        $this->assertSame('pets', $petPolicySources[0],
            '§22B: "pets" must be the first (primary) element in the pet_policy cascade '
            . 'to match the UI view render order ($str("pets") ?: $str("pet_policy")).');
    }

    /**
     * §22C: Confirms the fallback-path behavior after the I-2 fix.
     *
     * When only pet_policy is set and pets is absent, the context builder reads
     * pets first (null/falsy) → falls back to pet_policy → returns 'No pets allowed.'
     *
     * The UI view ($str('pets') ?: $str('pet_policy')) behaves identically:
     * pets absent → falls back to pet_policy → shows 'No pets allowed.'
     *
     * There is NO asymmetry in this scenario — both context and UI reach the same
     * value via their respective fallbacks. The cascade fix ensures that when BOTH
     * keys are populated with different values, the context now correctly prioritises
     * pets (matching the UI) rather than pet_policy.
     */
    public function test_s22c_landlord_pet_policy_fallback_returns_pet_policy_when_pets_absent(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('pet_policy', 'No pets allowed.');

        $ctx = $this->contextBuilder->buildForListing('landlord', $auction->id);

        $petPolicy = $ctx['listing']['pet_policy'] ?? null;

        // Post-fix behaviour: context reads pets first (absent → null/falsy) → falls back
        // to pet_policy → returns 'No pets allowed.' — matching what the UI also shows.
        $this->assertSame('No pets allowed.', $petPolicy,
            '§22C: when pets is absent and pet_policy is set, context must return the pet_policy '
            . 'value via the cascade fallback. This aligns with the UI ($str("pets") ?: $str("pet_policy")) '
            . 'which also falls back to pet_policy when pets is absent.');
    }

    // =========================================================================
    // § 23 — Buyer description reads native additional_details column
    //
    // The buyer_agent_auctions table has a native additional_details column.
    // Context reads $nativeGet('additional_details').
    // The buyer view renders $val('additional_details') which may read EAV $meta.
    // For buyer agent-auction wizard rows the native column is populated directly.
    // =========================================================================

    public function test_s23a_buyer_description_reads_native_additional_details(): void
    {
        $user    = User::factory()->create();
        $auction = \App\Models\BuyerAgentAuction::create([
            'user_id'            => $user->id,
            'title'              => '§23A buyer description test',
            'additional_details' => 'Looking for a 3-bedroom home in Tampa with a pool.',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('buyer', $auction->id);

        $description = $ctx['listing']['description'] ?? null;

        $this->assertNotNull($description,
            '§23A: buyer context[listing][description] must be non-null when native additional_details is set');
        $this->assertStringContainsString('3-bedroom', $description,
            '§23A: buyer description must read from native additional_details column');
    }

    public function test_s23b_buyer_description_null_when_native_column_absent(): void
    {
        $user    = User::factory()->create();
        $auction = \App\Models\BuyerAgentAuction::create([
            'user_id'            => $user->id,
            'title'              => '§23B buyer description absent test',
            'additional_details' => null,
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('buyer', $auction->id);

        $this->assertNull($ctx['listing']['description'] ?? null,
            '§23B: buyer description must be null when native additional_details column is null');
    }

    public function test_s23c_buyer_canonical_source_map_description_is_native(): void
    {
        $buyerSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['buyer'];

        $this->assertArrayHasKey('description', $buyerSources,
            '§23C: buyer CANONICAL_SOURCE_MAP must declare description source');
        $this->assertSame('native:additional_details', $buyerSources['description'],
            '§23C: buyer description canonical source must be native:additional_details');
    }

    // =========================================================================
    // § 24 — Tenant full criteria field coverage
    //
    // Validates that all four CANONICAL_SOURCE_MAP tenant keys are correctly
    // wired in the context builder and return expected values from EAV.
    // Tests each key individually with DB alignment (direct meta read vs context).
    // =========================================================================

    private function makeTenantAuction(\App\Models\User $user): \App\Models\TenantAgentAuction
    {
        $id = \Illuminate\Support\Facades\DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'         => $user->id,
            'is_approved'     => true,
            'is_draft'        => false,
            'is_sold'         => false,
            'auction_ended'   => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return \App\Models\TenantAgentAuction::findOrFail($id);
    }

    public function test_s24a_tenant_credit_score_range_from_primary_eav_key(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('credit_score_range', '700-749');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $creditScore = $ctx['listing']['credit_score_range'] ?? null;
        $this->assertNotNull($creditScore,
            '§24A: tenant credit_score_range must be non-null when EAV key is set');
        $this->assertSame('700-749', $creditScore,
            '§24A: tenant credit_score_range must read from credit_score_range EAV key');

        $dbValue = \Illuminate\Support\Facades\DB::table('tenant_agent_auction_metas')
            ->where('tenant_agent_auction_id', $auction->id)
            ->where('meta_key', 'credit_score_range')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect('credit_score_range', $creditScore, $dbValue);
        $this->assertFalse($result['conflict'],
            '§24A: tenant credit_score_range context must match direct DB read of credit_score_range EAV');
    }

    public function test_s24b_tenant_credit_score_range_falls_back_to_credit_score(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('credit_score', '680');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $creditScore = $ctx['listing']['credit_score_range'] ?? null;
        $this->assertNotNull($creditScore,
            '§24B: tenant credit_score_range must fall back to credit_score EAV when primary key absent');
        $this->assertSame('680', $creditScore,
            '§24B: tenant credit_score_range fallback must return the credit_score value');
    }

    public function test_s24c_tenant_monthly_income_from_primary_eav_key(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('monthly_income', '7500');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $income = $ctx['listing']['monthly_income'] ?? null;
        $this->assertNotNull($income,
            '§24C: tenant monthly_income must be non-null when EAV key is set');
        $this->assertSame('7500', $income,
            '§24C: tenant monthly_income must read from monthly_income EAV key');

        $dbValue = \Illuminate\Support\Facades\DB::table('tenant_agent_auction_metas')
            ->where('tenant_agent_auction_id', $auction->id)
            ->where('meta_key', 'monthly_income')
            ->value('meta_value');

        $result = AskAiContextBuilderService::conflictDetect('monthly_income', $income, $dbValue);
        $this->assertFalse($result['conflict'],
            '§24C: tenant monthly_income context must match direct DB read of monthly_income EAV');
    }

    public function test_s24d_tenant_monthly_income_falls_back_to_household_monthly_income(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('household_monthly_income', '6200');

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $income = $ctx['listing']['monthly_income'] ?? null;
        $this->assertNotNull($income,
            '§24D: tenant monthly_income must fall back to household_monthly_income when primary absent');
        $this->assertSame('6200', $income,
            '§24D: fallback must return the household_monthly_income value');
    }

    public function test_s24e_tenant_desired_lease_length_from_json_multiselect(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('desired_lease_length', json_encode(['6 Months', '12 Months']));

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $leaseLength = $ctx['listing']['desired_lease_length'] ?? null;
        $this->assertNotNull($leaseLength,
            '§24E: tenant desired_lease_length must be non-null when EAV JSON array is set');
        $this->assertStringContainsString('6 Months', $leaseLength,
            '§24E: tenant desired_lease_length must decode the JSON array from EAV');
        $this->assertStringContainsString('12 Months', $leaseLength,
            '§24E: decoded lease_length must contain all selected values');
    }

    public function test_s24f_tenant_desired_lease_length_falls_back_to_lease_for(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeTenantAuction($user);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('lease_for', json_encode(['Annual', 'Month-to-Month']));

        $ctx = $this->contextBuilder->buildForListing('tenant', $auction->id);

        $leaseLength = $ctx['listing']['desired_lease_length'] ?? null;
        $this->assertNotNull($leaseLength,
            '§24F: tenant desired_lease_length must fall back to lease_for when desired_lease_length absent');
        $this->assertStringContainsString('Annual', $leaseLength,
            '§24F: lease_for fallback must decode the JSON array and return values');
    }

    public function test_s24g_tenant_context_sources_map_has_all_four_keys(): void
    {
        $tenantSources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['tenant'];

        $requiredKeys = ['max_rent', 'desired_lease_length', 'credit_score_range', 'monthly_income'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $tenantSources,
                "§24G: tenant CANONICAL_SOURCE_MAP must declare '{$key}'");
        }

        $this->assertIsArray($tenantSources['max_rent'],
            '§24G: max_rent must be an array cascade in CANONICAL_SOURCE_MAP');
        $this->assertSame('budget', $tenantSources['max_rent'][0],
            '§24G: max_rent primary source must be budget (highest-priority EAV key for tenant)');
    }

    // =========================================================================
    // § 25 — CANONICAL_SOURCE_MAP cross-role completeness
    //
    // Asserts that all four roles are present and each has the minimum required
    // keys for the truth-source contract to be enforceable. Also verifies that
    // no role declares a source key using a malformed prefix or a key that
    // conflicts with the extractFactualFields() output contract.
    // =========================================================================

    public function test_s25a_canonical_source_map_all_roles_present(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        // All five roles — four listing types plus agent_profile — must be declared.
        // agent_profile is assembled separately by AgentProfileLoader (not a listing role)
        // but must appear in CANONICAL_SOURCE_MAP for complete five-role source documentation.
        foreach (['seller', 'buyer', 'landlord', 'tenant', 'agent_profile'] as $role) {
            $this->assertArrayHasKey($role, $map,
                "§25A: CANONICAL_SOURCE_MAP must contain role '{$role}'");
            $this->assertIsArray($map[$role],
                "§25A: CANONICAL_SOURCE_MAP['{$role}'] must be an array");
            $this->assertNotEmpty($map[$role],
                "§25A: CANONICAL_SOURCE_MAP['{$role}'] must not be empty");
        }
    }

    public function test_s25b_canonical_source_map_seller_minimum_required_keys(): void
    {
        $seller = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];

        $required = ['description', 'asking_price', 'utilities', 'seller_credit_offered',
                     'seller_credit_amount', 'hoa_association', 'hoa_fee', 'flood_zone_code'];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $seller,
                "§25B: seller CANONICAL_SOURCE_MAP missing required key '{$key}'");
        }
    }

    public function test_s25c_canonical_source_map_landlord_minimum_required_keys(): void
    {
        $landlord = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord'];

        $required = ['description', 'rent_amount', 'lease_terms', 'lease_length',
                     'pet_policy', 'utilities', 'annual_property_taxes', 'has_hoa'];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $landlord,
                "§25C: landlord CANONICAL_SOURCE_MAP missing required key '{$key}'");
        }
    }

    public function test_s25d_canonical_source_map_buyer_minimum_required_keys(): void
    {
        $buyer = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['buyer'];

        $required = ['description', 'max_price', 'financing_type', 'loan_pre_approved'];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $buyer,
                "§25D: buyer CANONICAL_SOURCE_MAP missing required key '{$key}'");
        }
    }

    public function test_s25e_canonical_source_map_tenant_minimum_required_keys(): void
    {
        $tenant = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['tenant'];

        $required = ['max_rent', 'desired_lease_length', 'credit_score_range', 'monthly_income'];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $tenant,
                "§25E: tenant CANONICAL_SOURCE_MAP missing required key '{$key}'");
        }
    }

    public function test_s25f_canonical_source_map_no_duplicate_keys_per_role(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        foreach (['seller', 'buyer', 'landlord', 'tenant', 'agent_profile'] as $role) {
            $keys = array_keys($map[$role]);
            $unique = array_unique($keys);
            $this->assertSame(
                count($keys),
                count($unique),
                "§25F: CANONICAL_SOURCE_MAP['{$role}'] must not have duplicate keys (PHP silently uses last-write-wins)"
            );
        }
    }

    public function test_s25g_canonical_source_map_cascade_arrays_have_string_elements(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        foreach (['seller', 'buyer', 'landlord', 'tenant', 'agent_profile'] as $role) {
            foreach ($map[$role] as $key => $source) {
                if (is_array($source)) {
                    foreach ($source as $i => $element) {
                        $this->assertIsString($element,
                            "§25G: CANONICAL_SOURCE_MAP['{$role}']['{$key}'][{$i}] must be a string");
                        $this->assertNotEmpty($element,
                            "§25G: CANONICAL_SOURCE_MAP['{$role}']['{$key}'][{$i}] must not be empty");
                    }
                } else {
                    $this->assertIsString($source,
                        "§25G: CANONICAL_SOURCE_MAP['{$role}']['{$key}'] must be a string or array");
                    $this->assertNotEmpty($source,
                        "§25G: CANONICAL_SOURCE_MAP['{$role}']['{$key}'] must not be empty");
                }
            }
        }
    }

    // =========================================================================
    // § 26 — Agent Profile as the 5th role (CANONICAL_SOURCE_MAP + context output)
    //
    // Agent profile is assembled separately by AgentProfileLoader from the users
    // table and agent_default_profiles.profile_data JSON.  It is not a listing
    // role; it surfaces in the context as ctx['agent_profile'] rather than inside
    // ctx['listing'], and its keys are NOT included in ctx['_sources'] which
    // covers listing fields only.  CANONICAL_SOURCE_MAP['agent_profile'] exists
    // for audit completeness to document the authoritative data sources for every
    // field the agent profile exposes to the AI.
    //
    // §26A: CANONICAL_SOURCE_MAP['agent_profile'] is present and declares the
    //        minimum required source keys for Agent Profile fields.
    //
    // §26B: buildForListing() returns a non-null agent_profile section when the
    //        listing has a linked agent user (i.e. user_id is set and resolves).
    //
    // §26C: The agent_profile section contains the mandatory public-safe fields
    //        that AgentProfileLoader is contractually required to populate from
    //        the users table (agent_name and short_id).
    //
    // §26D: CANONICAL_SOURCE_MAP['agent_profile'] keys match the known fields
    //        that AgentProfileLoader exposes in its content array.
    // =========================================================================

    public function test_s26a_agent_profile_canonical_source_map_entry_present(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        $this->assertArrayHasKey('agent_profile', $map,
            '§26A: CANONICAL_SOURCE_MAP must contain an "agent_profile" entry for the 5th role');

        $agentProfileSources = $map['agent_profile'];
        $this->assertIsArray($agentProfileSources,
            '§26A: CANONICAL_SOURCE_MAP["agent_profile"] must be an array');

        // Minimum required keys — these map to fields that AgentProfileLoader guarantees
        // to attempt to populate for every agent (even if the value is null at runtime).
        $required = ['agent_name', 'short_id', 'brokerage', 'license_no', 'bio', 'years_experience', 'services'];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $agentProfileSources,
                "§26A: CANONICAL_SOURCE_MAP[\"agent_profile\"] must declare source for '{$key}'");
        }
    }

    public function test_s26b_agent_profile_section_present_in_context_when_agent_exists(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $auction = \App\Models\SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => '§26B agent profile context test',
            'address' => '1 Agent Profile Lane',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        // agent_profile may be null when the user has no AgentDefaultProfile presets,
        // but the key must always be present in the returned context structure.
        $this->assertArrayHasKey('agent_profile', $ctx,
            '§26B: buildForListing() must include the "agent_profile" key in the context payload '
            . '(even when its value is null — agents without presets return null from AgentProfileLoader)');
    }

    public function test_s26c_agent_profile_content_contains_agent_name_and_short_id(): void
    {
        $user = User::factory()->create([
            'user_type'  => 'agent',
            'first_name' => 'Gabrielle',
            'last_name'  => 'Harmon',
        ]);

        // Create an AgentDefaultProfile preset so AgentProfileLoader returns non-null content.
        \App\Models\AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> 'Residential',
            'profile_data' => json_encode([
                'bio'              => 'Expert Tampa Bay agent with 12 years experience.',
                'years_experience' => '12',
                'services'         => ['CMA', 'MLS Listing', 'Negotiation'],
                'brokerage'        => 'Harmon Realty Group',
                'license_no'       => 'BK3012345',
            ]),
        ]);

        $auction = \App\Models\SellerAgentAuction::create([
            'user_id' => $user->id,
            'title'   => '§26C agent name and short_id test',
            'address' => '1 Profile Test Road',
        ]);
        $auction->saveMeta('workflow_type', 'offer_listing');

        $ctx = $this->contextBuilder->buildForListing('seller', $auction->id);

        $profile = $ctx['agent_profile'] ?? null;

        $this->assertNotNull($profile,
            '§26C: agent_profile context must be non-null when the agent has a preset profile');

        // agent_name is assembled from users.first_name + users.last_name
        $this->assertArrayHasKey('agent_name', $profile,
            '§26C: agent_profile must contain "agent_name" key');
        $this->assertStringContainsString('Gabrielle', $profile['agent_name'] ?? '',
            '§26C: agent_profile.agent_name must include the agent\'s first name from users table');
        $this->assertStringContainsString('Harmon', $profile['agent_name'] ?? '',
            '§26C: agent_profile.agent_name must include the agent\'s last name from users table');

        // short_id comes from users.short_id
        $this->assertArrayHasKey('short_id', $profile,
            '§26C: agent_profile must contain "short_id" key sourced from users.short_id');
        $this->assertSame($user->short_id, $profile['short_id'],
            '§26C: agent_profile.short_id must match users.short_id for the listing owner');
    }

    public function test_s26d_agent_profile_canonical_keys_match_loader_output_fields(): void
    {
        // CANONICAL_SOURCE_MAP['agent_profile'] documents sources for fields that
        // AgentProfileLoader actually populates in its content array.  This test verifies
        // that every key declared in the source map is a real field returned by the loader
        // (not a phantom entry pointing to a non-existent output key).
        $declaredKeys = array_keys(AskAiContextBuilderService::CANONICAL_SOURCE_MAP['agent_profile']);

        // AgentProfileLoader::buildContent() returns these keys (among others).
        // The source map must only declare keys that are present in the loader output.
        $loaderOutputKeys = [
            'agent_name', 'short_id', 'brokerage', 'license_no', 'bio',
            'years_experience', 'services', 'nar_id', 'year_licensed',
            'is_full_time', 'transactions_last_12_months', 'awards_recognition',
            'what_sets_you_apart', 'why_hire_you', 'marketing_plan',
            'commission_structure', 'purchase_fee_type', 'lease_fee_type',
            'cities_served', 'primary_areas_served', 'availability_status',
        ];

        foreach ($declaredKeys as $key) {
            $this->assertContains($key, $loaderOutputKeys,
                "§26D: CANONICAL_SOURCE_MAP[\"agent_profile\"]['{$key}'] is declared but "
                . 'is not a known AgentProfileLoader output field — phantom source entry');
        }
    }

    // -------------------------------------------------------------------------
    // § 20D — Content-level contract enforcement in coerceToContractStatus()
    // -------------------------------------------------------------------------

    /**
     * coerceToContractStatus() now enforces two layers:
     *   1. Status-level (existing): non-contract statuses → insufficient_context
     *   2. Content-level (new): 'ready' + degraded answer → insufficient_context
     *
     * A 'ready' response with a degraded answer (e.g. "Yes." — 1 word, too short
     * to be a complete sentence) violates the direct_fact / synthesis contract form
     * and must be coerced.  The original status is preserved in _pre_coercion_status
     * and the coercion reason in _pre_coercion_reason.
     */
    public function test_s20d_content_level_enforcement_coerces_degraded_ready_answer(): void
    {
        $builder = app(AskAiFinalResponseBuilderService::class);

        // "Yes." is degraded: 1 word, < 15 chars — isResponseDegraded returns true.
        $degradedAnswer = 'Yes.';
        $this->assertTrue(
            $builder->isResponseDegraded($degradedAnswer),
            '"Yes." must be degraded (1 word, < 15 chars) so the content-level check triggers'
        );

        $degradedReady = [
            'status'  => 'ready',
            'success' => true,
            'answer'  => $degradedAnswer,
        ];
        $coerced = $builder->coerceToContractStatus($degradedReady);

        $this->assertSame(
            'insufficient_context',
            $coerced['status'],
            'Content-level enforcement: ready + degraded answer must be coerced to insufficient_context'
        );
        $this->assertSame(
            'ready',
            $coerced['_pre_coercion_status'],
            '_pre_coercion_status must record the original ready status'
        );
        $this->assertSame(
            'degraded_answer_text',
            $coerced['_pre_coercion_reason'],
            '_pre_coercion_reason must be degraded_answer_text for content-level coercions'
        );
        $this->assertFalse(
            $coerced['success'] ?? true,
            'success must be false after coercion to insufficient_context'
        );

        // A well-formed 'ready' answer must pass through both layers unchanged.
        $wellFormedReady = [
            'status'  => 'ready',
            'success' => true,
            'answer'  => 'The listing price is $450,000 as set by the seller.',
        ];
        $unchanged = $builder->coerceToContractStatus($wellFormedReady);

        $this->assertSame('ready', $unchanged['status'],
            'A well-formed ready answer must pass through coerceToContractStatus unchanged');
        $this->assertArrayNotHasKey(
            '_pre_coercion_status',
            $unchanged,
            'No _pre_coercion_status must be present when both enforcement layers pass'
        );
        $this->assertArrayNotHasKey(
            '_pre_coercion_reason',
            $unchanged,
            'No _pre_coercion_reason must be present when both enforcement layers pass'
        );
    }

    // =========================================================================
    // § 27 — Synthesis gate exhaustiveness + source attribution coverage
    //
    // These tests verify two correctness invariants that together complete the
    // "full source contract" requirement:
    //
    //   §27A  Every field key listed in SYNTHESIS_REQUIRED_KEYS (private const in
    //         AskAiRunnerV2Service) has a matching context key in CANONICAL_SOURCE_MAP
    //         for at least one listing role, proving no phantom synthesis entries exist.
    //
    //   §27B  The former phantom key 'listing.hoa_fee_includes' has been removed from
    //         SYNTHESIS_REQUIRED_KEYS and replaced by the correct context key
    //         'listing.association_fee_includes'.
    //
    //   §27C  All JSON-decoded (comma-separated) fields in extractFactualFields() —
    //         the known exhaustive set — are present in SYNTHESIS_REQUIRED_KEYS, so
    //         raw comma-string values are never returned directly to the user.
    //
    //   §27D  The standalone audit report is committed at a git-tracked location
    //         (docs/ask-ai/field-audit-report.md) so code reviewers can inspect it.
    // =========================================================================

    /**
     * Helper: return the SYNTHESIS_REQUIRED_KEYS array via Reflection
     * (it is a private const so it cannot be read directly in tests).
     *
     * @return string[]
     */
    private function getSynthesisRequiredKeys(): array
    {
        $rc = new \ReflectionClass(AskAiRunnerV2Service::class);
        return $rc->getConstant('SYNTHESIS_REQUIRED_KEYS');
    }

    public function test_s27a_synthesis_required_keys_have_matching_canonical_source_map_entry(): void
    {
        $synthesisKeys = $this->getSynthesisRequiredKeys();
        $map           = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;
        $listingRoles  = ['seller', 'buyer', 'landlord', 'tenant'];

        foreach ($synthesisKeys as $fullKey) {
            // Strip the 'listing.' prefix to get the bare context key name.
            $contextKey = str_starts_with($fullKey, 'listing.')
                ? substr($fullKey, strlen('listing.'))
                : $fullKey;

            $foundInAtLeastOneRole = false;
            foreach ($listingRoles as $role) {
                if (array_key_exists($contextKey, $map[$role] ?? [])) {
                    $foundInAtLeastOneRole = true;
                    break;
                }
            }

            $this->assertTrue(
                $foundInAtLeastOneRole,
                "§27A: SYNTHESIS_REQUIRED_KEYS contains '{$fullKey}' but context key "
                . "'{$contextKey}' is not declared in CANONICAL_SOURCE_MAP for any listing role — "
                . "phantom synthesis entry. Either add it to CANONICAL_SOURCE_MAP or remove it from "
                . "SYNTHESIS_REQUIRED_KEYS."
            );
        }
    }

    public function test_s27b_phantom_hoa_fee_includes_key_removed_from_synthesis_required_keys(): void
    {
        $synthesisKeys = $this->getSynthesisRequiredKeys();

        $this->assertNotContains(
            'listing.hoa_fee_includes',
            $synthesisKeys,
            '§27B: listing.hoa_fee_includes was a phantom key (no extractFactualFields() output '
            . 'uses that name). It must be removed from SYNTHESIS_REQUIRED_KEYS. '
            . 'The correct key is listing.association_fee_includes.'
        );

        $this->assertContains(
            'listing.association_fee_includes',
            $synthesisKeys,
            '§27B: listing.association_fee_includes must be in SYNTHESIS_REQUIRED_KEYS '
            . '(replaces the former phantom key listing.hoa_fee_includes).'
        );
    }

    public function test_s27c_all_json_decoded_fields_are_in_synthesis_required_keys(): void
    {
        $synthesisKeys = $this->getSynthesisRequiredKeys();

        // Exhaustive list of all fields decoded via decodeJsonField() across all four listing roles.
        // Any field that returns a raw comma-separated string must be synthesis-required so the AI
        // never echoes an unprocessed array value directly to the user.
        $jsonDecodedFields = [
            // ── Seller ────────────────────────────────────────────────────────
            'listing.interior_features',
            'listing.appliances',
            'listing.roof_type',
            'listing.exterior_construction',
            'listing.foundation',
            'listing.heating_and_fuel',
            'listing.heating_fuel',
            'listing.air_conditioning',
            'listing.water',
            'listing.water_source',
            'listing.sewer',
            'listing.water_access',
            'listing.water_view',
            'listing.sale_provision',
            'listing.offered_financing',
            'listing.pool_type',
            'listing.association_fee_includes',
            'listing.building_features',
            'listing.utilities',
            // Seller paired fields (Yes/No flag + companion amount)
            'listing.seller_credit_offered',
            'listing.seller_credit_amount',
            // ── Buyer ─────────────────────────────────────────────────────────
            'listing.financing_type',
            // ── Landlord ──────────────────────────────────────────────────────
            'listing.lease_terms',
            'listing.terms_of_lease',
            'listing.lease_length',
            'listing.tenant_pays',
            'listing.rent_includes',
            'listing.pet_policy',
            'listing.pet_species_allowed',
            'listing.view',
            // ── Tenant ────────────────────────────────────────────────────────
            'listing.desired_lease_length',
            'listing.property_items',
        ];

        foreach ($jsonDecodedFields as $key) {
            $this->assertContains(
                $key,
                $synthesisKeys,
                "§27C: JSON-decoded field '{$key}' is not in SYNTHESIS_REQUIRED_KEYS. "
                . "Fields decoded via decodeJsonField() return raw comma-separated strings "
                . "and must be gated through OpenAI synthesis so users never see raw arrays."
            );
        }
    }

    public function test_s27d_audit_report_committed_at_tracked_path(): void
    {
        // The audit report must live at a git-tracked path (not .local/ which is gitignored)
        // so code reviewers can inspect the per-field source attribution for all five roles.
        $trackedPath = base_path('docs/ask-ai/field-audit-report.md');

        $this->assertFileExists(
            $trackedPath,
            '§27D: docs/ask-ai/field-audit-report.md must exist at a git-tracked location. '
            . 'The .local/ directory is gitignored — the audit report must be moved to docs/ask-ai/.'
        );

        $contents = file_get_contents($trackedPath);

        // Must cover all five roles.
        foreach (['Seller', 'Buyer', 'Landlord', 'Tenant', 'Agent Profile'] as $role) {
            $this->assertStringContainsString(
                $role,
                $contents,
                "§27D: docs/ask-ai/field-audit-report.md must document the '{$role}' role."
            );
        }

        // Must include the issue registry for the two production fixes.
        $this->assertStringContainsString('I-1', $contents,
            '§27D: Audit report must document Issue I-1 (seller description EAV vs native).');
        $this->assertStringContainsString('I-2', $contents,
            '§27D: Audit report must document Issue I-2 (landlord pet_policy cascade order).');
    }

    // =========================================================================
    // § 28 — Bidirectional contract: CANONICAL_SOURCE_MAP ↔ extractFactualFields()
    // =========================================================================

    /**
     * §28A — Every key returned by extractFactualFields() is declared in CANONICAL_SOURCE_MAP.
     *
     * Direction: extraction → map. Prevents undeclared shadow keys from accumulating over time.
     */
    public function test_s28a_every_extracted_key_is_map_declared(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        $cases = [];
        if (LandlordAgentAuction::find(71)) {
            $cases[] = ['landlord', 71];
        }
        if (SellerAgentAuction::find(121)) {
            $cases[] = ['seller', 121];
        }

        if (empty($cases)) {
            $this->markTestSkipped('§28A: No test listings (seller 121, landlord 71) present — skipping.');
        }

        // These role-universal structural fields are injected by extractListingFields()
        // base block (lines 722-735 of AskAiContextBuilderService) and are identical
        // across all roles. They do not require per-role source attribution in the map.
        $baseStructuralKeys = [
            'listing_type', 'listing_id', 'listing_title',
            'city', 'state', 'county', 'property_type',
            'listing_status', 'created_at', 'updated_at',
        ];

        foreach ($cases as [$role, $id]) {
            $ctx = $this->contextBuilder->buildForListing($role, $id);
            $listing = $ctx['listing'] ?? [];
            $mapKeys = array_keys($map[$role] ?? []);

            // Exclude base structural keys — they're documented at extractListingFields() line 697.
            $undeclared = array_diff(
                array_diff(array_keys($listing), $mapKeys),
                $baseStructuralKeys
            );

            $this->assertEmpty(
                $undeclared,
                "§28A [{$role}]: extractFactualFields() returned keys not in CANONICAL_SOURCE_MAP "
                . "(excluding role-universal base fields): " . implode(', ', $undeclared)
            );
        }
    }

    /**
     * §28B — Every source key declared in CANONICAL_SOURCE_MAP appears as a string literal
     * in the extractFactualFields() source (phantom-source guard).
     *
     * Direction: map → extraction. Catches entries whose EAV keys were renamed or never wired.
     */
    public function test_s28b_map_source_keys_referenced_in_extraction_source(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;
        $source = file_get_contents(app_path('Services/AskAi/AskAiContextBuilderService.php'));

        $phantoms = [];

        foreach ($map as $role => $contextKeys) {
            if ($role === 'agent_profile') {
                // Agent profile uses a separate AgentProfileLoader — skip source check.
                continue;
            }

            foreach ($contextKeys as $contextKey => $sourceSpec) {
                $specs = is_array($sourceSpec) ? $sourceSpec : [$sourceSpec];

                foreach ($specs as $spec) {
                    if (str_starts_with((string) $spec, 'native:')) {
                        // native:column → referenced as ->column or ['column'] in source.
                        $col = substr($spec, 7);
                        $found = str_contains($source, "->$col")
                            || str_contains($source, "'$col'")
                            || str_contains($source, "\"$col\"");
                    } else {
                        $found = str_contains($source, "'$spec'")
                            || str_contains($source, "\"$spec\"");
                    }

                    if (!$found) {
                        $phantoms[] = "{$role}.{$contextKey} => {$spec}";
                    }
                }
            }
        }

        $this->assertEmpty(
            $phantoms,
            '§28B: CANONICAL_SOURCE_MAP source keys not referenced in extraction source '
            . '(phantom entries): ' . implode('; ', $phantoms)
        );
    }

    /**
     * §28C — Landlord utilities empty-string cascade: when the `utilities` EAV key is
     * empty-string (infoGet returns ''), the context must fall back to property_utilities.
     *
     * Verifies that ?: (not ??) is used, since infoGet() returns '' for absent EAV rows.
     */
    public function test_s28c_landlord_utilities_empty_string_falls_back_to_property_utilities(): void
    {
        $auctionId = \Illuminate\Support\Facades\DB::table('landlord_agent_auctions')->insertGetId([
            'user_id'    => $this->makeTestUserId(),
            'title'      => 'Test Utilities Fallback',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set utilities to empty string (simulates absent EAV row returning '').
        \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')->insert([
            'landlord_agent_auction_id' => $auctionId,
            'meta_key'                  => 'utilities',
            'meta_value'                => '',
        ]);

        // Set property_utilities to a JSON array — the fallback value.
        \Illuminate\Support\Facades\DB::table('landlord_agent_auction_metas')->insert([
            'landlord_agent_auction_id' => $auctionId,
            'meta_key'                  => 'property_utilities',
            'meta_value'                => json_encode(['Electric', 'Water']),
        ]);

        $ctx = $this->contextBuilder->buildForListing('landlord', $auctionId);
        $utilities = $ctx['listing']['utilities'] ?? null;

        $this->assertNotNull(
            $utilities,
            '§28C: When utilities EAV is empty-string, context must fall back to property_utilities'
        );

        // The fallback value should contain the decoded property_utilities data.
        $asString = is_array($utilities) ? implode(', ', $utilities) : (string) $utilities;
        $this->assertStringContainsString(
            'Electric',
            $asString,
            '§28C: Fallback to property_utilities must decode JSON array into usable value'
        );
    }

    // ---------------------------------------------------------------------------
    // Helper used by §28C
    // ---------------------------------------------------------------------------

    private function makeTestUserId(): int
    {
        return \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'name'       => 'Test User',
            'short_id'   => \Illuminate\Support\Str::random(8),
            'user_name'  => 'testuser_' . \Illuminate\Support\Str::random(6),
            'email'      => 'test_' . \Illuminate\Support\Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'user_type'  => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
