<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaCompatibilityComparisonService;
use PHPUnit\Framework\TestCase;

/**
 * ByaCompatibilityComparisonServiceTest
 *
 * Verifies the BYA_COMP_V1 comparison layer against in-memory profile stubs.
 * No database connection is required — all test data is fabricated inline.
 *
 * Each test asserts one or more of:
 *   (a) All 12 canonical dimensions are always present when at least one profile is valid
 *   (b) Missing/null values yield relationship: unknown, never an exception
 *   (c) The service has no database dependency (structural verification — it imports no
 *       database classes and declares no DB interaction of any kind)
 *   (d) None of the forbidden terms appear in the service source file
 *   (e) relationship values are strictly one of: same | similar | different | unknown
 *   (f) Profile version metadata is forwarded into the payload for auditability
 */
class ByaCompatibilityComparisonServiceTest extends TestCase
{
    private ByaCompatibilityComparisonService $service;

    private const CANONICAL_DIMENSIONS = [
        'communication_style',
        'communication_frequency',
        'decision_speed',
        'risk_tolerance',
        'negotiation_style',
        'advisor_expectation',
        'technology_preference',
        'market_education_preference',
        'property_search_involvement',
        'transaction_guidance_level',
        'availability_expectation',
        'personality_style',
    ];

    private const VALID_RELATIONSHIPS = ['same', 'similar', 'different', 'unknown'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaCompatibilityComparisonService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build normalized profile stubs
    // -------------------------------------------------------------------------

    /**
     * Build a minimal consumer profile stub (BYA_NORM_V1 shape).
     *
     * @param  array  $traits  Associative array of trait_key => slot_array.
     */
    private function makeConsumerProfile(array $traits = []): array
    {
        return [
            'normalization_version' => 'BYA_NORM_V1',
            'role'                  => 'seller',
            'listing_id'            => 1,
            'traits'                => $traits,
            'informational_context' => [],
            'proxy_risk_flags'      => [],
        ];
    }

    /**
     * Build a minimal agent profile stub (BYA_AGENT_NORM_V1 shape).
     *
     * @param  array  $traits  Associative array of trait_key => slot_array.
     */
    private function makeAgentProfile(array $traits = []): array
    {
        return [
            'normalization_version' => 'BYA_AGENT_NORM_V1',
            'role'                  => 'seller',
            'bid_id'                => 10,
            'traits'                => $traits,
            'informational_context' => [],
            'proxy_risk_flags'      => [],
        ];
    }

    /**
     * Build a trait slot with a non-null scalar value (answered state).
     */
    private function answeredSlot(mixed $value): array
    {
        return ['value' => $value, 'missing' => false];
    }

    /**
     * Build a trait slot with a null value (skipped state).
     */
    private function skippedSlot(): array
    {
        return ['value' => null, 'missing' => false];
    }

    /**
     * Build a full set of 12 consumer traits, all skipped, optionally overriding some.
     */
    private function allSkippedConsumerTraits(array $overrides = []): array
    {
        $keys = [
            'communication_channel', 'communication_frequency', 'responsiveness_expectation',
            'negotiation_style', 'guidance_level', 'decision_making_style', 'transaction_pace',
            'risk_tolerance', 'collaboration_style', 'representation_priorities',
            'representation_philosophy', 'property_strategy_fit',
        ];
        $traits = array_fill_keys($keys, $this->skippedSlot());
        foreach ($overrides as $k => $v) {
            $traits[$k] = $v;
        }
        return $traits;
    }

    /**
     * Build a full set of 12 agent traits, all skipped, optionally overriding some.
     */
    private function allSkippedAgentTraits(array $overrides = []): array
    {
        return $this->allSkippedConsumerTraits($overrides);
    }

    // -------------------------------------------------------------------------
    // (A) Structural shape: all 12 dimensions always present
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_all_12_dimensions_when_both_profiles_are_fully_populated(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits([
            'negotiation_style' => $this->answeredSlot('collaborative'),
            'risk_tolerance'    => $this->answeredSlot('moderate'),
        ]));
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits([
            'negotiation_style' => $this->answeredSlot('collaborative'),
            'risk_tolerance'    => $this->answeredSlot('moderate'),
        ]));

        $result = $this->service->compare($consumer, $agent);

        $this->assertArrayHasKey('comparison_version', $result);
        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertArrayHasKey('dimensions', $result);
        $this->assertIsArray($result['dimensions']);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertArrayHasKey($dim, $result['dimensions'], "Missing dimension: {$dim}");
        }
    }

    /** @test */
    public function it_returns_all_12_dimensions_when_only_consumer_profile_is_valid(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits());
        $agent    = null;

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertCount(12, $result['dimensions']);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertArrayHasKey($dim, $result['dimensions'], "Missing dimension: {$dim}");
        }
    }

    /** @test */
    public function it_returns_all_12_dimensions_when_only_agent_profile_is_valid(): void
    {
        $consumer = [];
        $agent    = $this->makeAgentProfile($this->allSkippedAgentTraits());

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertCount(12, $result['dimensions']);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertArrayHasKey($dim, $result['dimensions'], "Missing dimension: {$dim}");
        }
    }

    /** @test */
    public function each_dimension_always_has_consumer_agent_and_relationship_keys(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits());
        $agent    = $this->makeAgentProfile($this->allSkippedAgentTraits());

        $result = $this->service->compare($consumer, $agent);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $dimension = $result['dimensions'][$dim];
            $this->assertArrayHasKey('consumer',     $dimension, "{$dim} missing consumer key");
            $this->assertArrayHasKey('agent',        $dimension, "{$dim} missing agent key");
            $this->assertArrayHasKey('relationship', $dimension, "{$dim} missing relationship key");
        }
    }

    // -------------------------------------------------------------------------
    // (F) Profile version metadata in the payload
    // -------------------------------------------------------------------------

    /** @test */
    public function payload_includes_consumer_and_agent_profile_version_metadata(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits());
        $agent    = $this->makeAgentProfile($this->allSkippedAgentTraits());

        $result = $this->service->compare($consumer, $agent);

        $this->assertArrayHasKey('consumer_profile_version', $result);
        $this->assertArrayHasKey('agent_profile_version',    $result);
        $this->assertSame('BYA_NORM_V1',       $result['consumer_profile_version']);
        $this->assertSame('BYA_AGENT_NORM_V1', $result['agent_profile_version']);
    }

    /** @test */
    public function consumer_profile_version_is_null_when_consumer_profile_is_invalid(): void
    {
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits());

        $result = $this->service->compare(null, $agent);

        $this->assertNull($result['consumer_profile_version']);
        $this->assertSame('BYA_AGENT_NORM_V1', $result['agent_profile_version']);
    }

    /** @test */
    public function agent_profile_version_is_null_when_agent_profile_is_invalid(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits());

        $result = $this->service->compare($consumer, null);

        $this->assertSame('BYA_NORM_V1', $result['consumer_profile_version']);
        $this->assertNull($result['agent_profile_version']);
    }

    /** @test */
    public function stub_payload_includes_null_profile_version_fields(): void
    {
        $result = $this->service->compare(null, null);

        $this->assertArrayHasKey('consumer_profile_version', $result);
        $this->assertArrayHasKey('agent_profile_version',    $result);
        $this->assertNull($result['consumer_profile_version']);
        $this->assertNull($result['agent_profile_version']);
    }

    // -------------------------------------------------------------------------
    // (B) Missing/null values → relationship: unknown, no exception
    // -------------------------------------------------------------------------

    /** @test */
    public function missing_consumer_value_yields_unknown_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'negotiation_style' => $this->skippedSlot(),
        ]);
        $agent = $this->makeAgentProfile([
            'negotiation_style' => $this->answeredSlot('competitive'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('unknown', $result['dimensions']['negotiation_style']['relationship']);
    }

    /** @test */
    public function missing_agent_value_yields_unknown_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'negotiation_style' => $this->answeredSlot('collaborative'),
        ]);
        $agent = $this->makeAgentProfile([
            'negotiation_style' => $this->skippedSlot(),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('unknown', $result['dimensions']['negotiation_style']['relationship']);
    }

    /** @test */
    public function both_null_values_yield_unknown_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([]);
        $agent    = $this->makeAgentProfile([]);

        $result = $this->service->compare($consumer, $agent);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertSame(
                'unknown',
                $result['dimensions'][$dim]['relationship'],
                "Expected unknown for {$dim} when both traits are absent"
            );
        }
    }

    /** @test */
    public function dimensions_with_no_profile_mapping_always_yield_unknown(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits([
            'communication_channel' => $this->answeredSlot('phone'),
        ]));
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits([
            'communication_channel' => $this->answeredSlot('phone'),
        ]));

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('unknown', $result['dimensions']['technology_preference']['relationship'],
            'technology_preference has no profile mapping — must always be unknown');
        $this->assertSame('unknown', $result['dimensions']['market_education_preference']['relationship'],
            'market_education_preference has no profile mapping — must always be unknown');
    }

    /** @test */
    public function empty_consumer_profile_contributes_null_for_all_values(): void
    {
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits([
            'risk_tolerance' => $this->answeredSlot('moderate'),
        ]));

        $result = $this->service->compare([], $agent);

        $this->assertNull($result['dimensions']['risk_tolerance']['consumer']);
        $this->assertSame('unknown', $result['dimensions']['risk_tolerance']['relationship']);
    }

    // -------------------------------------------------------------------------
    // Both profiles invalid → stub payload with dimensions: []
    // -------------------------------------------------------------------------

    /** @test */
    public function both_invalid_profiles_return_stub_payload_with_empty_dimensions(): void
    {
        $result = $this->service->compare(null, null);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function both_non_array_profiles_return_stub_payload(): void
    {
        $result = $this->service->compare('not-an-array', 12345);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function both_empty_array_profiles_return_stub_payload(): void
    {
        $result = $this->service->compare([], []);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function one_valid_one_non_array_profile_returns_full_12_dimensions(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits());

        $result = $this->service->compare($consumer, 'invalid');

        $this->assertCount(12, $result['dimensions']);
    }

    // -------------------------------------------------------------------------
    // Relationship correctness
    // -------------------------------------------------------------------------

    /** @test */
    public function identical_scalar_values_yield_same_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'negotiation_style' => $this->answeredSlot('collaborative'),
            'risk_tolerance'    => $this->answeredSlot('moderate'),
        ]);
        $agent = $this->makeAgentProfile([
            'negotiation_style' => $this->answeredSlot('collaborative'),
            'risk_tolerance'    => $this->answeredSlot('moderate'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('same', $result['dimensions']['negotiation_style']['relationship']);
        $this->assertSame('same', $result['dimensions']['risk_tolerance']['relationship']);
    }

    /** @test */
    public function differing_scalar_values_yield_different_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'negotiation_style' => $this->answeredSlot('collaborative'),
        ]);
        $agent = $this->makeAgentProfile([
            'negotiation_style' => $this->answeredSlot('competitive'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('different', $result['dimensions']['negotiation_style']['relationship']);
    }

    /** @test */
    public function identical_array_values_yield_same_regardless_of_ordering(): void
    {
        $consumer = $this->makeConsumerProfile([
            'collaboration_style' => $this->answeredSlot(['hands-on', 'proactive', 'data-driven']),
        ]);
        $agent = $this->makeAgentProfile([
            'collaboration_style' => $this->answeredSlot(['proactive', 'data-driven', 'hands-on']),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('same', $result['dimensions']['property_search_involvement']['relationship']);
    }

    /** @test */
    public function differing_array_values_yield_different_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'collaboration_style' => $this->answeredSlot(['hands-on', 'proactive']),
        ]);
        $agent = $this->makeAgentProfile([
            'collaboration_style' => $this->answeredSlot(['hands-off', 'reactive']),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('different', $result['dimensions']['property_search_involvement']['relationship']);
    }

    /** @test */
    public function mixed_scalar_and_array_values_yield_different_relationship(): void
    {
        $consumer = $this->makeConsumerProfile([
            'communication_channel' => $this->answeredSlot('phone'),
        ]);
        $agent = $this->makeAgentProfile([
            'communication_channel' => $this->answeredSlot(['phone', 'email']),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('different', $result['dimensions']['communication_style']['relationship']);
    }

    // -------------------------------------------------------------------------
    // Dimension trait key mappings
    // -------------------------------------------------------------------------

    /** @test */
    public function communication_style_maps_to_communication_channel_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'communication_channel' => $this->answeredSlot('email'),
        ]);
        $agent = $this->makeAgentProfile([
            'communication_channel' => $this->answeredSlot('email'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('email', $result['dimensions']['communication_style']['consumer']);
        $this->assertSame('email', $result['dimensions']['communication_style']['agent']);
        $this->assertSame('same',  $result['dimensions']['communication_style']['relationship']);
    }

    /** @test */
    public function decision_speed_maps_to_transaction_pace_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'transaction_pace' => $this->answeredSlot('fast'),
        ]);
        $agent = $this->makeAgentProfile([
            'transaction_pace' => $this->answeredSlot('slow'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('fast',      $result['dimensions']['decision_speed']['consumer']);
        $this->assertSame('slow',      $result['dimensions']['decision_speed']['agent']);
        $this->assertSame('different', $result['dimensions']['decision_speed']['relationship']);
    }

    /** @test */
    public function advisor_expectation_maps_to_guidance_level_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'guidance_level' => $this->answeredSlot('high-touch'),
        ]);
        $agent = $this->makeAgentProfile([
            'guidance_level' => $this->answeredSlot('high-touch'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('same', $result['dimensions']['advisor_expectation']['relationship']);
    }

    /** @test */
    public function availability_expectation_maps_to_responsiveness_expectation_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'responsiveness_expectation' => $this->answeredSlot('within-1-hour'),
        ]);
        $agent = $this->makeAgentProfile([
            'responsiveness_expectation' => $this->answeredSlot('within-4-hours'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('within-1-hour',  $result['dimensions']['availability_expectation']['consumer']);
        $this->assertSame('within-4-hours', $result['dimensions']['availability_expectation']['agent']);
        $this->assertSame('different',      $result['dimensions']['availability_expectation']['relationship']);
    }

    /** @test */
    public function personality_style_maps_to_representation_philosophy_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'representation_philosophy' => $this->answeredSlot('trust-advisor'),
        ]);
        $agent = $this->makeAgentProfile([
            'representation_philosophy' => $this->answeredSlot('trust-advisor'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('same', $result['dimensions']['personality_style']['relationship']);
    }

    /** @test */
    public function transaction_guidance_level_maps_to_decision_making_style_trait(): void
    {
        $consumer = $this->makeConsumerProfile([
            'decision_making_style' => $this->answeredSlot('data-driven'),
        ]);
        $agent = $this->makeAgentProfile([
            'decision_making_style' => $this->answeredSlot('intuitive'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('different', $result['dimensions']['transaction_guidance_level']['relationship']);
    }

    // -------------------------------------------------------------------------
    // Relationship values are always in the permitted set
    // -------------------------------------------------------------------------

    /** @test */
    public function all_relationship_values_are_within_the_permitted_set(): void
    {
        $consumer = $this->makeConsumerProfile($this->allSkippedConsumerTraits([
            'negotiation_style'     => $this->answeredSlot('collaborative'),
            'risk_tolerance'        => $this->answeredSlot('moderate'),
            'communication_channel' => $this->answeredSlot('email'),
        ]));
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits([
            'negotiation_style'     => $this->answeredSlot('competitive'),
            'risk_tolerance'        => $this->answeredSlot('moderate'),
            'communication_channel' => $this->answeredSlot('phone'),
        ]));

        $result = $this->service->compare($consumer, $agent);

        foreach ($result['dimensions'] as $dim => $data) {
            $this->assertContains(
                $data['relationship'],
                self::VALID_RELATIONSHIPS,
                "Dimension {$dim} emitted invalid relationship: {$data['relationship']}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // (C) No database dependency — structural verification
    // -------------------------------------------------------------------------

    /**
     * @test
     *
     * This service has no database dependency. Rather than using a runtime query
     * listener (which requires a Laravel application container this unit test does
     * not boot), we verify the contract structurally: the service file must not
     * import any database facade, model, or query builder class, and must not
     * reference DB::, Eloquent, or query() calls.
     *
     * The governance constraint ("Pure read-only service. Never writes to the
     * database.") is enforced by the class design: the service accepts plain
     * arrays, calls no external dependencies, and returns a plain array.
     */
    public function service_has_no_database_imports_or_calls(): void
    {
        $serviceFile = file_get_contents(
            __DIR__ . '/../../../../../app/Services/Dna/Compatibility/ByaCompatibilityComparisonService.php'
        );

        $this->assertIsString($serviceFile);

        $forbiddenPatterns = [
            'use Illuminate\\Support\\Facades\\DB',
            'use Illuminate\\Database',
            'Eloquent',
            'DB::',
            '->query(',
            '->insert(',
            '->update(',
            '->delete(',
            '->save(',
            '->create(',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $serviceFile,
                "Database pattern \"{$pattern}\" found in ByaCompatibilityComparisonService.php"
            );
        }
    }

    // -------------------------------------------------------------------------
    // (D) Forbidden terms absent from the service source file
    // -------------------------------------------------------------------------

    /** @test */
    public function service_file_contains_none_of_the_forbidden_terms(): void
    {
        $serviceFile = file_get_contents(
            __DIR__ . '/../../../../../app/Services/Dna/Compatibility/ByaCompatibilityComparisonService.php'
        );

        $this->assertIsString($serviceFile, 'Could not read ByaCompatibilityComparisonService.php');

        $forbiddenTerms = ['score', 'weight', 'rank', 'recommend', 'winner', 'best', 'match_percentage'];

        foreach ($forbiddenTerms as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $serviceFile,
                "Forbidden term \"{$term}\" found in ByaCompatibilityComparisonService.php"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Never throws — exception safety
    // -------------------------------------------------------------------------

    /** @test */
    public function compare_never_throws_for_deeply_malformed_input(): void
    {
        $malformedInputs = [
            [null,                    null],
            [false,                   false],
            ['string',                12345],
            [new \stdClass(),         []],
            [['traits' => 'not-array'], ['traits' => 42]],
        ];

        foreach ($malformedInputs as [$consumer, $agent]) {
            $result = $this->service->compare($consumer, $agent);

            $this->assertArrayHasKey('comparison_version', $result,
                'Stub payload must always have comparison_version');
            $this->assertArrayHasKey('dimensions', $result,
                'Stub payload must always have dimensions');
            $this->assertSame('BYA_COMP_V1', $result['comparison_version'],
                'Version must always be BYA_COMP_V1');
        }
    }

    /** @test */
    public function compare_returns_valid_payload_when_traits_contains_unexpected_slot_shapes(): void
    {
        $consumer = $this->makeConsumerProfile([
            'negotiation_style' => 'not-a-slot-array',
            'risk_tolerance'    => null,
            'guidance_level'    => ['unexpected_key' => 'unexpected_value'],
        ]);
        $agent = $this->makeAgentProfile([
            'negotiation_style' => $this->answeredSlot('collaborative'),
        ]);

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertCount(12, $result['dimensions']);

        $this->assertContains(
            $result['dimensions']['negotiation_style']['relationship'],
            self::VALID_RELATIONSHIPS
        );
    }

    /** @test */
    public function malformed_traits_value_in_otherwise_valid_profile_still_produces_12_dimensions(): void
    {
        $consumer = [
            'normalization_version' => 'BYA_NORM_V1',
            'role'                  => 'seller',
            'traits'                => 'not-an-array',
        ];
        $agent = $this->makeAgentProfile($this->allSkippedAgentTraits([
            'negotiation_style' => $this->answeredSlot('collaborative'),
        ]));

        $result = $this->service->compare($consumer, $agent);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
        $this->assertCount(12, $result['dimensions'],
            'A non-empty profile with malformed traits should still produce 12 dimensions with unknown relationships');

        foreach ($result['dimensions'] as $dim => $data) {
            $this->assertNull($data['consumer'],
                "Consumer value for {$dim} should be null when traits is malformed");
            $this->assertSame('unknown', $data['relationship'],
                "Relationship for {$dim} should be unknown when consumer traits is malformed");
        }
    }
}
