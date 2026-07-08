<?php

namespace Tests\Feature\MatchCheck;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\EnrichmentGuardDecision;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleStore;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\LocationDnaEnrichmentGuard;
use App\Services\Stellar\MatchCheck\MatchCheckAnalysis;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckResult;
use App\Services\Stellar\MatchCheck\MatchReport;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * git-C15 — standalone Match Check compliance regression suite (F7 / F8 / F9).
 *
 * Implements the authoritative git-C15 scope doc: one canary payload planted in real
 * BridgeProperty `raw_json`, swept end-to-end through the REAL analyzeBy*() pipeline and the
 * REAL ListingVisibilityGate, asserting it leaks through NONE of the output surfaces
 * (analysis toArray, candidate list, full rendered HTTP response) and that a non-IDX listing
 * is blocked end-to-end with zero enrichment dispatch.
 *
 *   F7 — no restricted source data (raw / raw_json, PublicRemarks, agent/office PII, lockbox,
 *        showing instructions) reaches any output surface. Enforced by PropertyCandidate::
 *        toArray() excluding $raw by default; this suite proves the default is never bypassed.
 *   F8 — a MatchReport with a NON-NULL narrative renders no narrative content or affordance.
 *        NUANCE: MatchReport::toArray() legitimately keeps a nullable `narrative` KEY (a data
 *        slot). The compliance boundary is RENDERING, not serialization — F8 is asserted on the
 *        rendered HTML, and the DTO key is explicitly allowed to exist.
 *   F9 — a non-IDX listing (IDXParticipationYN=false) is driven through the REAL gate to
 *        BLOCKED; the render shows only neutral copy, never the machine reason, and NO
 *        Location DNA enrichment is dispatched.
 *
 * PRACTICALITY BOUNDARY: block, ambiguous, not-found and flag-off short-circuit before any
 * scoring, so they run fully end-to-end through the real orchestrator with only the Bridge
 * API seam (BridgeListingLookupService) mocked — exactly the composition-test pattern. A REAL
 * SCORED end-to-end additionally requires persisted preferred-criteria + the scoring engine;
 * that heavy fixture is out of scope for a compliance regression, so the SCORED F7/F8
 * guarantees are asserted at the report-render + DTO-serialization boundary instead. The
 * canary can only ever reach a scored report via $raw, which the report never reads.
 *
 * Test-only. No production code changes; mls_match_check.enabled stays OFF except where a
 * single request opts into the ON path via Config::set.
 */
class MatchCheckComplianceTest extends TestCase
{
    use DatabaseTransactions;

    /** The single canary + the specific restricted VALUES planted in raw_json. None may leak. */
    private const CANARY = 'CANARY_RESTRICTED_DO_NOT_LEAK';
    private const RESTRICTED_MARKERS = [
        self::CANARY,
        'canary_restricted_lower_sentinel',
        'CANARY_PII_Jane_Agent',
        'canary-agent@secret-brokerage.example',
        '941-555-0199',
        'CANARY_LOCKBOX_front_door_4821',
        'CANARY_SHOWING_call_office_first',
    ];

    /** Restricted RESO KEY names that must not leak structurally either. */
    private const RESTRICTED_KEYS = ['PublicRemarks', 'ListAgentEmail', 'LockBoxLocation', 'ShowingInstructions'];

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake(); // intercept ComputeLocationDna so a leak of enrichment I/O is observable
        Config::set('mls_match_check.enabled', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** raw_json blob carrying the IDX flag + a full set of restricted source fields. */
    private function canaryRawJson(string $key, bool $idxParticipation): string
    {
        return json_encode([
            'ListingKey'           => $key,
            'IDXParticipationYN'   => $idxParticipation,
            'PublicRemarks'        => self::CANARY.' — remarks that must never publish',
            'public_remarks'       => 'canary_restricted_lower_sentinel',
            'ListAgentFullName'    => 'CANARY_PII_Jane_Agent',
            'ListAgentEmail'       => 'canary-agent@secret-brokerage.example',
            'ListAgentDirectPhone' => '941-555-0199',
            'LockBoxLocation'      => 'CANARY_LOCKBOX_front_door_4821',
            'ShowingInstructions'  => 'CANARY_SHOWING_call_office_first',
        ]);
    }

    /** Persist a listing with a canary raw_json; $idx drives the F9 gate decision. */
    private function seedListing(
        string $suffix,
        bool $idx,
        string $propertyType = 'Residential',
        string $propertySubType = 'Single Family Residence',
    ): BridgeProperty {
        $key = 'PHPUNIT-C15-K-'.$suffix;

        return BridgeProperty::create([
            'listing_key'       => $key,
            'listing_id'        => 'PHPUNIT-C15-'.$suffix,
            'standard_status'   => 'Active',
            'property_type'     => $propertyType,
            'property_sub_type' => $propertySubType,
            'list_price'        => 550000,
            'unparsed_address'  => $suffix.' Compliance Way',
            'city'              => 'Sarasota',
            'state_or_province' => 'FL',
            'postal_code'       => '34236',
            'raw_json'          => $this->canaryRawJson($key, $idx),
        ]);
    }

    private function candidateFor(BridgeProperty $listing): \App\Services\Property\PropertyCandidate
    {
        return (new BridgePropertyCandidateAdapter())->fromModel($listing->fresh());
    }

    /** An unpersisted candidate carrying restricted $raw (AMBIGUOUS never hits BridgeProperty::find). */
    private function detachedCandidate(string $suffix, string $subType, string $address): \App\Services\Property\PropertyCandidate
    {
        $key   = 'PHPUNIT-C15-K-'.$suffix;
        $model = new BridgeProperty([
            'listing_key'       => $key,
            'listing_id'        => 'PHPUNIT-C15-'.$suffix,
            'property_type'     => str_contains($subType, 'Commercial') ? 'Commercial Sale' : 'Residential',
            'property_sub_type' => $subType,
            'unparsed_address'  => $address,
            'city'              => 'Sarasota',
            'raw_json'          => $this->canaryRawJson($key, true),
        ]);

        return (new BridgePropertyCandidateAdapter())->fromModel($model);
    }

    private function strictMock(string $class, string ...$forbidden): object
    {
        $m = Mockery::mock($class);
        foreach ($forbidden as $method) {
            $m->shouldNotReceive($method);
        }

        return $m;
    }

    /**
     * Bind a REAL orchestrator (real gate + real enrichment guard) whose only mocked seam is the
     * Bridge lookup, so the HTTP request exercises the genuine pipeline. Collaborators unreached on
     * the block/ambiguous paths are strict mocks that fail if touched.
     */
    private function bindRealOrchestrator(BridgeListingLookupService $lookup): void
    {
        $orchestrator = new MatchCheckOrchestrator(
            visibilityGate: new ListingVisibilityGate(),
            intentDetector: $this->strictMock(CriteriaIntentDetector::class, 'detectFromModel'),
            criteriaResolver: $this->strictMock(CriteriaListingResolver::class, 'resolvePreferred'),
            scorer: null,
            criteriaLoader: null,
            lookup: $lookup,
            enrichmentGuard: new LocationDnaEnrichmentGuard(),
            throttleStore: $this->strictMock(EnrichmentThrottleStore::class, 'snapshot', 'recordAttempt'),
        );

        $this->instance(MatchCheckOrchestrator::class, $orchestrator);
    }

    private function assertNoRestrictedData($response): void
    {
        foreach (self::RESTRICTED_MARKERS as $marker) {
            $response->assertDontSee($marker);
        }
        foreach (self::RESTRICTED_KEYS as $key) {
            $response->assertDontSee($key);
        }
    }

    // ── F9: non-IDX listing blocked end-to-end through the real gate ─────────────────────

    /** @test */
    public function non_idx_listing_is_blocked_end_to_end_hides_reason_and_dispatches_no_enrichment(): void
    {
        $listing = $this->seedListing('BLOCK', idx: false);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()
            ->with($listing->listing_id, false)
            ->andReturn($this->candidateFor($listing));
        $this->bindRealOrchestrator($lookup);

        // PRG (git-C14.1): the lookup POST redirects; follow it so the F9 sweep runs against
        // the rendered result page. The compliance assertions are otherwise unchanged.
        $response = $this->actingAs($this->user())
            ->followingRedirects()
            ->post('/match-check', ['mode' => 'mls', 'mls_number' => $listing->listing_id])
            ->assertOk()
            ->assertSee('data-status="blocked"', false)
            ->assertSee('available for a match check');

        // F9 — the real gate's machine reason is never exposed…
        $response->assertDontSee('idx_participation_false');
        // …and no restricted source data leaks on the blocked page.
        $this->assertNoRestrictedData($response);

        // No Location DNA enrichment is dispatched for a blocked listing.
        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    // ── F7: ambiguous list end-to-end, residential + non-residential ─────────────────────

    /** @test */
    public function ambiguous_address_end_to_end_leaks_no_restricted_data_across_property_types(): void
    {
        $candidates = collect([
            $this->detachedCandidate('RES-1', 'Condominium', '1 Ocean Dr Unit 5'),
            $this->detachedCandidate('COM-1', 'Commercial', '200 Industrial Blvd'),
        ]);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('searchByAddress')->once()->andReturn($candidates);
        $this->bindRealOrchestrator($lookup);

        // PRG (git-C14.1): the lookup POST redirects; follow it so the F7 sweep runs against
        // the rendered result page. The compliance assertions are otherwise unchanged.
        $response = $this->actingAs($this->user())
            ->followingRedirects()
            ->post('/match-check', ['mode' => 'address', 'address' => '1 Ocean Dr'])
            ->assertOk()
            ->assertSee('data-status="ambiguous"', false)
            // Publishable facts DO render (residential + non-residential), so the sweep is not
            // a vacuous empty-payload pass.
            ->assertSee('1 Ocean Dr Unit 5')
            ->assertSee('200 Industrial Blvd')
            ->assertSee('PHPUNIT-C15-RES-1')
            ->assertSee('PHPUNIT-C15-COM-1');

        $this->assertNoRestrictedData($response);
        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    // ── F7: DTO serialization + adapter contract ────────────────────────────────────────

    /** @test */
    public function ambiguous_analysis_toArray_excludes_raw_and_canary(): void
    {
        $analysis = MatchCheckAnalysis::ambiguous(collect([
            $this->detachedCandidate('RES-1', 'Condominium', '1 Ocean Dr Unit 5'),
            $this->detachedCandidate('COM-1', 'Commercial', '200 Industrial Blvd'),
        ]));

        $json = json_encode($analysis->toArray());

        $this->assertStringNotContainsString('"raw"', $json, 'candidates must not serialize a raw key');
        foreach (array_merge(self::RESTRICTED_MARKERS, self::RESTRICTED_KEYS) as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }

        // Facts still present → exclusion is field-level, not a blanket empty payload.
        $this->assertStringContainsString('1 Ocean Dr Unit 5', $json);
        $this->assertStringContainsString('200 Industrial Blvd', $json);
    }

    /** @test */
    public function property_candidate_from_canary_source_excludes_raw_by_default(): void
    {
        $candidate = $this->candidateFor($this->seedListing('CAND', idx: true));

        $default = $candidate->toArray();
        $this->assertArrayNotHasKey('raw', $default, 'default serialization must never include $raw');
        foreach (self::RESTRICTED_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, json_encode($default));
        }

        // Opting in (which the analysis path never does) is the only way to surface $raw — and it
        // DID carry the canary, proving the default exclusion above is meaningful, not vacuous.
        $withRaw = $candidate->toArray(true);
        $this->assertArrayHasKey('raw', $withRaw);
        $this->assertStringContainsString(self::CANARY, json_encode($withRaw));
    }

    // ── Flag-OFF inertness ──────────────────────────────────────────────────────────────

    /** @test */
    public function flag_off_post_404s_and_leaks_nothing(): void
    {
        Config::set('mls_match_check.enabled', false);
        $listing = $this->seedListing('OFF', idx: true);

        $response = $this->actingAs($this->user())
            ->post('/match-check', ['mode' => 'mls', 'mls_number' => $listing->listing_id])
            ->assertNotFound();

        $this->assertNoRestrictedData($response);
        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    // ── F8: narrative never rendered, but nullable DTO key preserved ─────────────────────

    private function scoredAnalysis(?array $narrative = null): MatchCheckAnalysis
    {
        $prep = MatchCheckPreparation::ready(
            VisibilityDecision::visible('idx_true'),
            'buyer',
            ['id' => 1, 'type' => 'buyer_criteria'],
        );
        $result = MatchCheckResult::scored($prep, 'LK-SCORED-1', 87, ['location' => 30, 'price' => 25]);

        $report = new MatchReport(
            criteriaId: 1,
            criteriaType: 'buyer_criteria',
            listingKey: 'LK-SCORED-1',
            source: 'bridge',
            totalScore: 87,
            categoryScores: ['location' => 30, 'price' => 25],
            whyThisMatches: ['Great location match'],
            whyNot: ['Slightly over budget'],
            tradeoffs: ['Smaller lot than preferred'],
            missingData: ['garage'],
            confidence: ['level' => 'high'],
            recommendations: ['Ask about HOA fees'],
            generatedAt: '2026-07-07T00:00:00+00:00',
            narrative: $narrative,
        );

        return MatchCheckAnalysis::fromResult($result, EnrichmentGuardDecision::REASON_ALLOWED, $report);
    }

    /** @test */
    public function scored_report_renders_but_never_the_narrative(): void
    {
        $analysis = $this->scoredAnalysis([
            'headline' => 'HIDDEN_NARRATIVE_HEADLINE_SENTINEL',
            'text'     => 'HIDDEN_NARRATIVE_BODY_SENTINEL_do_not_render',
        ]);

        $html = view('match-check.partials._result_body', ['analysis' => $analysis])->render();

        $this->assertStringContainsString('data-status="scored"', $html);
        $this->assertStringContainsString('87/100', $html);
        $this->assertStringContainsString('Great location match', $html);

        // F8 — no narrative content, no restricted markers.
        $this->assertStringNotContainsString('HIDDEN_NARRATIVE_HEADLINE_SENTINEL', $html);
        $this->assertStringNotContainsString('HIDDEN_NARRATIVE_BODY_SENTINEL_do_not_render', $html);
        foreach (self::RESTRICTED_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $html);
        }
    }

    /** @test */
    public function match_report_toArray_retains_the_nullable_narrative_key_by_design(): void
    {
        // The compliance boundary is RENDERING, not serialization: MatchReport::toArray() keeps a
        // `narrative` KEY. A future change asserting its absence here would be wrong — locked.
        $withoutNarrative = $this->scoredAnalysis()->report->toArray();
        $this->assertArrayHasKey('narrative', $withoutNarrative);
        $this->assertNull($withoutNarrative['narrative']);

        $withNarrative = $this->scoredAnalysis(['text' => 'present'])->report->toArray();
        $this->assertArrayHasKey('narrative', $withNarrative);
        $this->assertSame(['text' => 'present'], $withNarrative['narrative']);
    }
}
