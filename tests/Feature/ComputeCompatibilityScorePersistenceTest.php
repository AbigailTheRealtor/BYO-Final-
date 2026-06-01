<?php

namespace Tests\Feature;

use App\Jobs\ComputeCompatibilityScore;
use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for ComputeCompatibilityScore persistence completeness (Phase H).
 *
 * Asserts that after the job runs:
 *   §1  — physical_match_score, financial_match_score, terms_match_score are non-null floats
 *           (or null only when no dimensions in that bucket could be resolved)
 *   §2  — location_match_score is always null (no Location DNA Phase 2 yet)
 *   §3  — compatibility_readiness_score is a non-null float
 *   §4  — compatibility_narrative is a non-empty string
 *   §5  — compatibility_summary_json is a valid array with the required keys
 *   §6  — compatibility_highlights is a JSON array (may be empty if no aligned dimensions)
 *   §7  — compatibility_warnings is a JSON array (non-empty when ineligible dims exist)
 *   §8  — score_explanation contains an 'explanations' key
 *   §9  — With aligned dimensions (pet policy): physical_match_score is populated
 *   §10 — Append-only: a second run creates version 2 and archives version 1
 */
class ComputeCompatibilityScorePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a minimal PropertyDnaProfile and return the model.
     */
    private function makeSupplyProfile(array $overrides = []): PropertyDnaProfile
    {
        $now = now();
        $id  = DB::table('property_dna_profiles')->insertGetId(array_merge([
            'listing_type'               => 'seller',
            'listing_id'                 => random_int(10000, 99999),
            'version'                    => 1,
            'ai_buyer_archetype_tags'    => json_encode([]),
            'ai_marketing_hooks'         => json_encode([]),
            'source_listing_updated_at'  => $now,
            'computed_at'                => $now,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ], $overrides));

        return PropertyDnaProfile::find($id);
    }

    /**
     * Insert a minimal BuyerTenantDnaProfile and return the model.
     */
    private function makeDemandProfile(array $overrides = []): BuyerTenantDnaProfile
    {
        $now = now();
        $id  = DB::table('buyer_tenant_dna_profiles')->insertGetId(array_merge([
            'listing_type'               => 'buyer',
            'listing_id'                 => random_int(10000, 99999),
            'version'                    => 1,
            'lifestyle_tags'             => json_encode([]),
            'deal_breaker_flags'         => json_encode([]),
            'source_listing_updated_at'  => $now,
            'computed_at'                => $now,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ], $overrides));

        return BuyerTenantDnaProfile::find($id);
    }

    /**
     * Run the job synchronously and return the persisted score row.
     */
    private function runJob(PropertyDnaProfile $supply, BuyerTenantDnaProfile $demand): ListingCompatibilityScore
    {
        $job = new ComputeCompatibilityScore(
            $demand->listing_type,
            $demand->listing_id,
            $supply->listing_type,
            $supply->listing_id
        );
        $job->handle(app(\App\Services\Dna\Compatibility\CompatibilityEngine::class));

        return ListingCompatibilityScore::where('demand_listing_type', $demand->listing_type)
            ->where('demand_listing_id', $demand->listing_id)
            ->where('supply_listing_type', $supply->listing_type)
            ->where('supply_listing_id', $supply->listing_id)
            ->whereNull('archived_at')
            ->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // §1 — Grouped scores are non-null floats when bucket dimensions are resolved
    //      (with no signals, all eligible dims are unresolved → bucket scores null)
    // -------------------------------------------------------------------------

    public function test_grouped_scores_are_null_when_no_signals_present(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        // With empty tags, no eligible dimension resolves, so all buckets return null.
        $this->assertNull($score->physical_match_score,  'physical_match_score should be null when no signals');
        $this->assertNull($score->financial_match_score, 'financial_match_score should be null when no signals');
        $this->assertNull($score->terms_match_score,     'terms_match_score should be null when no signals');
    }

    // -------------------------------------------------------------------------
    // §2 — location_match_score is always null
    // -------------------------------------------------------------------------

    public function test_location_match_score_is_always_null(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNull($score->location_match_score, 'location_match_score must always be null (Phase H)');
    }

    // -------------------------------------------------------------------------
    // §3 — compatibility_readiness_score is a non-null float
    // -------------------------------------------------------------------------

    public function test_readiness_score_is_a_non_null_float(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->compatibility_readiness_score);
        $this->assertIsFloat((float) $score->compatibility_readiness_score);
        $this->assertGreaterThanOrEqual(0.0, (float) $score->compatibility_readiness_score);
        $this->assertLessThanOrEqual(100.0, (float) $score->compatibility_readiness_score);
    }

    // -------------------------------------------------------------------------
    // §4 — compatibility_narrative is a non-empty string
    // -------------------------------------------------------------------------

    public function test_compatibility_narrative_is_a_non_empty_string(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->compatibility_narrative);
        $this->assertIsString($score->compatibility_narrative);
        $this->assertNotEmpty(trim($score->compatibility_narrative));
    }

    // -------------------------------------------------------------------------
    // §5 — compatibility_summary_json has required keys
    // -------------------------------------------------------------------------

    public function test_compatibility_summary_json_has_required_keys(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->compatibility_summary_json);
        $summary = (array) $score->compatibility_summary_json;

        foreach (['overall_score', 'physical_score', 'financial_score', 'terms_score',
                  'matched_dimensions', 'unresolved_dimensions', 'conflicting_dimensions',
                  'narrative_summary'] as $key) {
            $this->assertArrayHasKey($key, $summary, "compatibility_summary_json missing key: {$key}");
        }

        $this->assertIsArray($summary['matched_dimensions']);
        $this->assertIsArray($summary['unresolved_dimensions']);
        $this->assertIsArray($summary['conflicting_dimensions']);
    }

    // -------------------------------------------------------------------------
    // §6 — compatibility_highlights is a JSON array
    // -------------------------------------------------------------------------

    public function test_compatibility_highlights_is_an_array(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->compatibility_highlights);
        $this->assertIsArray($score->compatibility_highlights);
    }

    // -------------------------------------------------------------------------
    // §7 — compatibility_warnings is a non-empty JSON array
    //      (ineligible dimensions always produce warnings)
    // -------------------------------------------------------------------------

    public function test_compatibility_warnings_is_a_non_empty_array(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->compatibility_warnings);
        $this->assertIsArray($score->compatibility_warnings);
        // The 6 ineligible dimensions always return unresolved, so warnings can't be empty.
        $this->assertNotEmpty($score->compatibility_warnings,
            'compatibility_warnings should be non-empty due to ineligible dimensions');
    }

    // -------------------------------------------------------------------------
    // §8 — score_explanation contains an 'explanations' key
    // -------------------------------------------------------------------------

    public function test_score_explanation_contains_explanations_key(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        $score = $this->runJob($supply, $demand);

        $this->assertNotNull($score->score_explanation);
        $explanation = (array) $score->score_explanation;
        $this->assertArrayHasKey('explanations', $explanation,
            'score_explanation must contain an explanations key');

        $explanations = $explanation['explanations'];
        $this->assertIsArray($explanations);
        $this->assertArrayHasKey('aligned',     $explanations);
        $this->assertArrayHasKey('conflicting',  $explanations);
        $this->assertArrayHasKey('unresolved',   $explanations);
    }

    // -------------------------------------------------------------------------
    // §9 — With pet + pets-allowed signal: pet_policy_alignment resolves aligned,
    //       terms bucket populates, highlight appears, no warning for pet policy
    // -------------------------------------------------------------------------

    public function test_pet_policy_alignment_resolved_with_matching_signals(): void
    {
        $supply = $this->makeSupplyProfile([
            'ai_buyer_archetype_tags' => json_encode(['policy:pets-allowed']),
        ]);
        $demand = $this->makeDemandProfile([
            'lifestyle_tags' => json_encode(['has-pets']),
        ]);

        $score = $this->runJob($supply, $demand);

        // pet_policy_alignment is in the 'terms' bucket — should be non-null now
        $this->assertNotNull($score->terms_match_score,
            'terms_match_score should be non-null when pet_policy_alignment resolves');
        $this->assertIsNumeric($score->terms_match_score);

        // 'Pet Policy Compatible' highlight should appear
        $highlights = (array) $score->compatibility_highlights;
        $this->assertContains('Pet Policy Compatible', $highlights,
            'Pet Policy Compatible highlight expected for aligned pet policy');

        // 'Pet Policy Comparison Unavailable' warning should NOT appear
        $warnings = (array) $score->compatibility_warnings;
        $this->assertNotContains('Pet Policy Comparison Unavailable', $warnings,
            'Pet Policy Comparison Unavailable warning should be absent when dimension resolves');
    }

    // -------------------------------------------------------------------------
    // §10 — Append-only: second run creates version 2 and archives version 1
    // -------------------------------------------------------------------------

    public function test_second_run_creates_version_2_and_archives_version_1(): void
    {
        $supply = $this->makeSupplyProfile();
        $demand = $this->makeDemandProfile();

        // First run
        $this->runJob($supply, $demand);

        // Second run
        $job = new ComputeCompatibilityScore(
            $demand->listing_type,
            $demand->listing_id,
            $supply->listing_type,
            $supply->listing_id
        );
        $job->handle(app(\App\Services\Dna\Compatibility\CompatibilityEngine::class));

        $allRows = ListingCompatibilityScore::where('demand_listing_type', $demand->listing_type)
            ->where('demand_listing_id', $demand->listing_id)
            ->where('supply_listing_type', $supply->listing_type)
            ->where('supply_listing_id', $supply->listing_id)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $allRows, 'Two rows should exist after two runs');

        $v1 = $allRows->firstWhere('version', 1);
        $v2 = $allRows->firstWhere('version', 2);

        $this->assertNotNull($v1, 'Version 1 row must exist');
        $this->assertNotNull($v2, 'Version 2 row must exist');
        $this->assertNotNull($v1->archived_at, 'Version 1 must be archived after second run');
        $this->assertNull($v2->archived_at,    'Version 2 must be active (not archived)');

        // New columns persisted on v2
        $this->assertNotNull($v2->compatibility_narrative);
        $this->assertNotNull($v2->compatibility_summary_json);
        $this->assertNull($v2->location_match_score, 'location_match_score must be null on v2');
    }
}
