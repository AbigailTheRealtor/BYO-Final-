<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AskAiKnowledgeSearchServiceTest — Phase 4: Database-First Answer Layer
 *
 * Uses DatabaseTransactions so each method runs inside a rolled-back
 * transaction, keeping the schema clean between methods.
 *
 * Test coverage:
 *   A. search() returns not_found when no snapshot exists for the listing.
 *   B. search() returns not_found when snapshot exists but has no matching data.
 *   C. database_hit via canonical key (faq_answers.*) — answer stored under bare key.
 *   D. database_hit via canonical key (faq_answers.*) — answer stored under full path key.
 *   E. blank_information_not_provided when FAQ question is registered but answer absent.
 *   F. database_hit via canonical key (listing.*) — listing fact lookup.
 *   G. restricted when listing fact is marked restricted.
 *   H. blank_information_not_provided when listing fact question exists but no fact row.
 *   I. database_hit via exact question_text match.
 *   J. database_hit via sample_question match (exact_question type).
 *   K. database_hit via sample_question_2 match (alternate_question type).
 *   L. database_hit via normalized variant matching.
 *   M. not_found returned when snapshot status is 'failed' (not 'ready').
 *   N. Latest version snapshot is used when multiple versions exist.
 *   O. search() never throws — Throwable is caught; not_found returned.
 *   P. normalizeQuestion() applies synonym map and strips filler phrases.
 *   Q. source metadata block is correct on database_hit result.
 *   R. source metadata block is null-filled on not_found result.
 *   S. blank result carries 'Information not provided.' answer string.
 *   T. restricted result carries null answer.
 */
class AskAiKnowledgeSearchServiceTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): AskAiKnowledgeSearchService
    {
        return new AskAiKnowledgeSearchService();
    }

    private function makeSnapshot(
        string $listingType = 'seller',
        int    $listingId   = 999001,
        string $status      = 'ready',
        int    $version     = 1
    ): AskAiKnowledgeSnapshot {
        return AskAiKnowledgeSnapshot::create([
            'listing_type'  => $listingType,
            'listing_id'    => $listingId,
            'version'       => $version,
            'status'        => $status,
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => $status === 'ready' ? now() : null,
        ]);
    }

    private function addFact(AskAiKnowledgeSnapshot $snap, string $key, ?string $value, bool $restricted = false): AskAiFact
    {
        return AskAiFact::create([
            'snapshot_id'    => $snap->id,
            'canonical_key'  => $key,
            'value'          => $value,
            'visibility'     => $restricted ? 'restricted' : 'public_allowed',
            'listing_type'   => $snap->listing_type,
            'listing_id'     => $snap->listing_id,
            'label'          => ucwords(str_replace('_', ' ', $key)),
            'value_type'     => $value === null ? 'null' : 'string',
            'source_path'    => 'context.listing.' . $key,
            'classification' => $restricted ? 'compliance_sensitive' : 'public_factual',
            'public_allowed' => !$restricted,
            'restricted'     => $restricted,
            'sort_order'     => 0,
        ]);
    }

    private function addQuestion(
        AskAiKnowledgeSnapshot $snap,
        string  $canonicalKey,
        string  $fieldType       = 'faq',
        ?string $questionText    = null,
        ?string $sampleQuestion  = null,
        ?string $sampleQuestion2 = null
    ): AskAiQuestion {
        return AskAiQuestion::create([
            'snapshot_id'     => $snap->id,
            'canonical_key'   => $canonicalKey,
            'field_type'      => $fieldType,
            'question_text'   => $questionText ?? $sampleQuestion,
            'sample_question' => $sampleQuestion,
            'sample_question_2' => $sampleQuestion2,
            'source_path'     => 'registry.' . $fieldType . '.' . $canonicalKey,
            'sort_order'      => 0,
        ]);
    }

    private function addAnswer(AskAiKnowledgeSnapshot $snap, string $canonicalKey, ?string $answerText): AskAiAnswer
    {
        return AskAiAnswer::create([
            'snapshot_id'   => $snap->id,
            'canonical_key' => $canonicalKey,
            'answer_text'   => $answerText,
        ]);
    }

    // =========================================================================
    // Case A — not_found when no snapshot exists
    // =========================================================================

    public function test_case_A_not_found_when_no_snapshot(): void
    {
        $result = $this->makeService()->search('seller', 9999999, 'How old is the roof?');

        $this->assertEquals('not_found', $result['outcome']);
        $this->assertNull($result['answer']);
        $this->assertNull($result['source']['snapshot_id']);
    }

    // =========================================================================
    // Case B — not_found when snapshot exists but no matching data
    // =========================================================================

    public function test_case_B_not_found_when_no_match(): void
    {
        $snap = $this->makeSnapshot(listingId: 999002);

        $result = $this->makeService()->search('seller', 999002, 'Tell me something random');

        $this->assertEquals('not_found', $result['outcome']);
        $this->assertNull($result['answer']);
    }

    // =========================================================================
    // Case C — database_hit via faq_answers.* canonical key (bare answer key)
    // =========================================================================

    public function test_case_C_database_hit_via_faq_canonical_key_bare(): void
    {
        $snap = $this->makeSnapshot(listingId: 999003);
        $this->addQuestion($snap, 'faq_answers.roof_age_and_condition', 'faq', 'How old is the roof?', 'How old is the roof?');
        $this->addAnswer($snap, 'roof_age_and_condition', '10-year-old asphalt shingle roof, replaced in 2014.');

        $result = $this->makeService()->search('seller', 999003, 'any question', [
            'normalized_field_key' => 'faq_answers.roof_age_and_condition',
        ]);

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertStringContainsString('asphalt shingle', $result['answer']);
        $this->assertEquals('canonical_field', $result['source']['match_type']);
        $this->assertEquals($snap->id, $result['source']['snapshot_id']);
        $this->assertEquals('faq_answers.roof_age_and_condition', $result['source']['canonical_key']);
    }

    // =========================================================================
    // Case D — database_hit via faq_answers.* canonical key (full path answer key)
    // =========================================================================

    public function test_case_D_database_hit_via_faq_canonical_key_full_path(): void
    {
        $snap = $this->makeSnapshot(listingId: 999004);
        $this->addQuestion($snap, 'faq_answers.hvac_system_age', 'faq', 'How old is the HVAC?', 'How old is the HVAC?');
        // Stored with full path as canonical_key
        $this->addAnswer($snap, 'faq_answers.hvac_system_age', 'HVAC replaced in 2019, dual-zone system.');

        $result = $this->makeService()->search('seller', 999004, 'any question', [
            'normalized_field_key' => 'faq_answers.hvac_system_age',
        ]);

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertStringContainsString('2019', $result['answer']);
        $this->assertEquals('canonical_field', $result['source']['match_type']);
    }

    // =========================================================================
    // Case E — blank when FAQ question registered but no answer row
    // =========================================================================

    public function test_case_E_blank_when_faq_question_exists_but_no_answer(): void
    {
        $snap = $this->makeSnapshot(listingId: 999005);
        $this->addQuestion($snap, 'faq_answers.water_heater_age_type', 'faq');

        $result = $this->makeService()->search('seller', 999005, 'any question', [
            'normalized_field_key' => 'faq_answers.water_heater_age_type',
        ]);

        $this->assertEquals('blank_information_not_provided', $result['outcome']);
        $this->assertEquals(AskAiKnowledgeSearchService::INFORMATION_NOT_PROVIDED, $result['answer']);
        $this->assertEquals('database', $result['source']['answer_source']);
    }

    // =========================================================================
    // Case F — database_hit via listing.* canonical key
    // =========================================================================

    public function test_case_F_database_hit_via_listing_canonical_key(): void
    {
        $snap = $this->makeSnapshot(listingId: 999006);
        $this->addFact($snap, 'bedrooms', '4');
        $this->addQuestion($snap, 'listing.bedrooms', 'listing_model', 'How many bedrooms?', 'How many bedrooms?');

        $result = $this->makeService()->search('seller', 999006, 'any question', [
            'normalized_field_key' => 'listing.bedrooms',
        ]);

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertEquals('4', $result['answer']);
        $this->assertEquals('canonical_field', $result['source']['match_type']);
        $this->assertEquals($snap->version, $result['source']['snapshot_version']);
    }

    // =========================================================================
    // Case G — restricted when listing fact is marked restricted
    // =========================================================================

    public function test_case_G_restricted_for_restricted_fact(): void
    {
        $snap = $this->makeSnapshot(listingId: 999007);
        $this->addFact($snap, 'flood_zone_code', 'AE', restricted: true);
        $this->addQuestion($snap, 'listing.flood_zone_code', 'listing_model');

        $result = $this->makeService()->search('seller', 999007, 'any question', [
            'normalized_field_key' => 'listing.flood_zone_code',
        ]);

        $this->assertEquals('restricted', $result['outcome']);
        $this->assertNull($result['answer']);
        $this->assertEquals('database', $result['source']['answer_source']);
        $this->assertEquals('canonical_field', $result['source']['match_type']);
    }

    // =========================================================================
    // Case H — blank when listing question registered but fact row absent
    // =========================================================================

    public function test_case_H_blank_when_listing_question_exists_but_no_fact(): void
    {
        $snap = $this->makeSnapshot(listingId: 999008);
        $this->addQuestion($snap, 'listing.bathrooms', 'listing_model');

        $result = $this->makeService()->search('seller', 999008, 'any question', [
            'normalized_field_key' => 'listing.bathrooms',
        ]);

        $this->assertEquals('blank_information_not_provided', $result['outcome']);
        $this->assertEquals(AskAiKnowledgeSearchService::INFORMATION_NOT_PROVIDED, $result['answer']);
    }

    // =========================================================================
    // Case I — database_hit via exact question_text match
    // =========================================================================

    public function test_case_I_database_hit_via_exact_question_text(): void
    {
        $snap = $this->makeSnapshot(listingId: 999009);
        $this->addQuestion(
            $snap,
            'faq_answers.roof_age_and_condition',
            'faq',
            questionText: 'How old is the roof?',
            sampleQuestion: 'How old is the roof?'
        );
        $this->addAnswer($snap, 'roof_age_and_condition', 'Roof is 5 years old.');

        $result = $this->makeService()->search('seller', 999009, 'How old is the roof?');

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertEquals('Roof is 5 years old.', $result['answer']);
        $this->assertEquals('exact_question', $result['source']['match_type']);
    }

    // =========================================================================
    // Case J — database_hit via sample_question match (case-insensitive)
    // =========================================================================

    public function test_case_J_database_hit_via_sample_question_case_insensitive(): void
    {
        $snap = $this->makeSnapshot(listingId: 999010);
        $this->addQuestion(
            $snap,
            'faq_answers.hvac_system_age',
            'faq',
            questionText: 'How old is the HVAC?',
            sampleQuestion: 'How old is the HVAC system?'
        );
        $this->addAnswer($snap, 'hvac_system_age', 'HVAC is 7 years old.');

        $result = $this->makeService()->search('seller', 999010, 'how old is the hvac system?');

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertEquals('exact_question', $result['source']['match_type']);
    }

    // =========================================================================
    // Case K — database_hit via sample_question_2 (alternate_question type)
    // =========================================================================

    public function test_case_K_database_hit_via_sample_question_2(): void
    {
        $snap = $this->makeSnapshot(listingId: 999011);
        $this->addQuestion(
            $snap,
            'faq_answers.water_heater_age_type',
            'faq',
            questionText:    'How old is the water heater?',
            sampleQuestion:  'How old is the water heater?',
            sampleQuestion2: 'What type of water heater does the property have?'
        );
        $this->addAnswer($snap, 'water_heater_age_type', 'Tankless gas water heater, installed 2020.');

        $result = $this->makeService()->search(
            'seller',
            999011,
            'What type of water heater does the property have?'
        );

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertEquals('alternate_question', $result['source']['match_type']);
        $this->assertStringContainsString('Tankless', $result['answer']);
    }

    // =========================================================================
    // Case L — database_hit via normalized variant matching
    // =========================================================================

    public function test_case_L_database_hit_via_normalized_variant(): void
    {
        $snap = $this->makeSnapshot(listingId: 999012);
        $this->addQuestion(
            $snap,
            'faq_answers.roof_age_and_condition',
            'faq',
            questionText:   'How old is the roof?',
            sampleQuestion: 'How old is the roof?'
        );
        $this->addAnswer($snap, 'roof_age_and_condition', 'Roof replaced 8 years ago.');

        // Different phrasing that normalises to the same tokens.
        $result = $this->makeService()->search('seller', 999012, 'how old is the roof');

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertContains($result['source']['match_type'], ['normalized_variant', 'exact_question']);
    }

    // =========================================================================
    // Case M — not_found when snapshot status is not 'ready'
    // =========================================================================

    public function test_case_M_not_found_when_snapshot_not_ready(): void
    {
        $snap = $this->makeSnapshot(listingId: 999013, status: 'failed');
        $this->addQuestion($snap, 'faq_answers.roof_age_and_condition', 'faq', 'How old is the roof?');
        $this->addAnswer($snap, 'roof_age_and_condition', 'Roof is 5 years old.');

        $result = $this->makeService()->search('seller', 999013, 'How old is the roof?', [
            'normalized_field_key' => 'faq_answers.roof_age_and_condition',
        ]);

        $this->assertEquals('not_found', $result['outcome']);
    }

    // =========================================================================
    // Case N — latest version snapshot is used when multiple exist
    // =========================================================================

    public function test_case_N_uses_latest_ready_snapshot_version(): void
    {
        $snap1 = $this->makeSnapshot(listingId: 999014, version: 1);
        $this->addAnswer($snap1, 'roof_age_and_condition', 'Old answer from v1.');

        $snap2 = $this->makeSnapshot(listingId: 999014, version: 2);
        $this->addQuestion($snap2, 'faq_answers.roof_age_and_condition', 'faq', 'How old is the roof?');
        $this->addAnswer($snap2, 'roof_age_and_condition', 'New answer from v2.');

        $result = $this->makeService()->search('seller', 999014, 'any question', [
            'normalized_field_key' => 'faq_answers.roof_age_and_condition',
        ]);

        $this->assertEquals('database_hit', $result['outcome']);
        $this->assertStringContainsString('v2', $result['answer']);
        $this->assertEquals($snap2->id, $result['source']['snapshot_id']);
        $this->assertEquals(2, $result['source']['snapshot_version']);
    }

    // =========================================================================
    // Case O — search() never throws; Throwable caught and not_found returned
    // =========================================================================

    public function test_case_O_never_throws(): void
    {
        $exception = null;
        $result    = null;

        try {
            // Passing invalid listing_type with DB table that doesn't have it
            // won't throw — the service catches all Throwable internally.
            $result = $this->makeService()->search('seller', -1, 'test question');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'search() must not throw; caught: ' . ($exception?->getMessage() ?? ''));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('outcome', $result);
    }

    // =========================================================================
    // Case P — normalizeQuestion() applies synonym map and strips fillers
    // =========================================================================

    public function test_case_P_normalize_question(): void
    {
        $svc = $this->makeService();

        // 'sq footage' synonym-maps to 'square footage'; 'what is the' stripped
        $this->assertEquals(
            'square footage of the property',
            $svc->normalizeQuestion('What is the sq footage of the property?'),
            'sq footage synonym-mapped to square footage; what is the filler stripped'
        );

        // 'tell me about the' is a filler phrase → stripped
        $this->assertEquals(
            'roof situation',
            $svc->normalizeQuestion('Tell me about the roof situation'),
            'tell me about the filler phrase stripped'
        );

        // 'how many' stripped; remaining tokens kept
        $this->assertEquals(
            'bedrooms does the property have',
            $svc->normalizeQuestion('How many bedrooms does the property have?'),
            'how many stripped; remaining phrase preserved'
        );

        // Empty input → empty output
        $this->assertEquals('', $svc->normalizeQuestion(''));
    }

    // =========================================================================
    // Case Q — source metadata is correct on database_hit
    // =========================================================================

    public function test_case_Q_source_metadata_on_database_hit(): void
    {
        $snap = $this->makeSnapshot(listingId: 999015, version: 3);
        $this->addQuestion($snap, 'faq_answers.roof_age_and_condition', 'faq');
        $this->addAnswer($snap, 'roof_age_and_condition', 'Roof is brand new.');

        $result = $this->makeService()->search('seller', 999015, 'any', [
            'normalized_field_key' => 'faq_answers.roof_age_and_condition',
        ]);

        $this->assertEquals('database_hit', $result['outcome']);
        $source = $result['source'];
        $this->assertEquals('database', $source['answer_source']);
        $this->assertEquals($snap->id, $source['snapshot_id']);
        $this->assertEquals('faq_answers.roof_age_and_condition', $source['canonical_key']);
        $this->assertEquals('canonical_field', $source['match_type']);
        $this->assertEquals(3, $source['snapshot_version']);
    }

    // =========================================================================
    // Case R — source metadata is null-filled on not_found
    // =========================================================================

    public function test_case_R_source_metadata_on_not_found(): void
    {
        $result = $this->makeService()->search('seller', 9999888, 'anything');

        $this->assertEquals('not_found', $result['outcome']);
        $source = $result['source'];
        $this->assertNull($source['answer_source']);
        $this->assertNull($source['snapshot_id']);
        $this->assertNull($source['canonical_key']);
        $this->assertNull($source['match_type']);
        $this->assertNull($source['snapshot_version']);
    }

    // =========================================================================
    // Case S — blank result carries the 'Information not provided.' string
    // =========================================================================

    public function test_case_S_blank_answer_string(): void
    {
        $snap = $this->makeSnapshot(listingId: 999016);
        $this->addQuestion($snap, 'faq_answers.roof_age_and_condition', 'faq');

        $result = $this->makeService()->search('seller', 999016, 'any', [
            'normalized_field_key' => 'faq_answers.roof_age_and_condition',
        ]);

        $this->assertEquals('blank_information_not_provided', $result['outcome']);
        $this->assertEquals('Information not provided.', $result['answer']);
    }

    // =========================================================================
    // Case T — restricted result carries null answer
    // =========================================================================

    public function test_case_T_restricted_answer_is_null(): void
    {
        $snap = $this->makeSnapshot(listingId: 999017);
        $this->addFact($snap, 'security_deposit', '2000', restricted: true);
        $this->addQuestion($snap, 'listing.security_deposit', 'listing_model');

        $result = $this->makeService()->search('seller', 999017, 'any', [
            'normalized_field_key' => 'listing.security_deposit',
        ]);

        $this->assertEquals('restricted', $result['outcome']);
        $this->assertNull($result['answer']);
    }
}
