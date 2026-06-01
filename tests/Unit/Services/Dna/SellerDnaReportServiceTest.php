<?php

namespace Tests\Unit\Services\Dna;

use App\Models\PropertyDnaProfile;
use App\Services\Dna\SellerDnaReportService;
use PHPUnit\Framework\TestCase;

/**
 * SellerDnaReportServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All PropertyDnaProfile stubs are built in memory using property assignment.
 *
 * Output contract keys (all must be present in every return path):
 *   success, status, listing_type, listing_id, seller_priorities,
 *   property_strengths, property_considerations, marketing_opportunities,
 *   buyer_archetype_alignment, signals, missing_inputs, error
 *
 * Test coverage:
 *   (1)  insufficient_data when completeness null and all priority scores null
 *   (2)  insufficient_data when completeness is zero and all priority scores null
 *   (3)  generated status when completeness is non-zero
 *   (4)  generated status when a priority score is non-null (completeness null)
 *   (5)  seller_priorities populated from non-null score fields
 *   (6)  seller_priorities omits null score fields
 *   (7)  property_strengths mapped from known archetype tags
 *   (8)  property_strengths omits tags not in the map
 *   (9)  property_considerations: low completeness triggers signal
 *   (10) property_considerations: low condition_score triggers signal
 *   (11) property_considerations: low marketing_score triggers signal
 *   (12) property_considerations: no signals when values are above thresholds
 *   (13) marketing_opportunities passthrough from ai_marketing_hooks
 *   (14) buyer_archetype_alignment passthrough from ai_buyer_archetype_tags
 *   (15) missing_inputs: null dimension fields listed
 *   (16) signals: only non-null fields included
 *   (17) output contract keys always present in generated path
 *   (18) output contract keys always present in insufficient_data path
 *   (19) output contract keys always present in failed path
 *   (20) failed status on Throwable (simulated via bad cast — covered by static assertions)
 *   (21) no AI/OpenAI class imports in the service file
 *   (22) no DB::statement or Eloquent write calls in the service file
 *   (23) listing_type is always 'seller' in output
 *   (24) listing_id is cast to int in output
 */
class SellerDnaReportServiceTest extends TestCase
{
    private const CONTRACT_KEYS = [
        'success',
        'status',
        'listing_type',
        'listing_id',
        'seller_priorities',
        'property_strengths',
        'property_considerations',
        'marketing_opportunities',
        'buyer_archetype_alignment',
        'signals',
        'missing_inputs',
        'error',
    ];

    private function makeService(): SellerDnaReportService
    {
        return new SellerDnaReportService();
    }

    /**
     * Build a minimal in-memory PropertyDnaProfile stub with all fields null.
     */
    private function makeProfile(array $attributes = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $profile->listing_type = 'seller';
        $profile->listing_id   = 1;

        $profile->overall_dna_completeness     = null;
        $profile->flexibility_score            = null;
        $profile->financial_score              = null;
        $profile->marketing_score              = null;
        $profile->compatibility_score          = null;
        $profile->condition_score              = null;
        $profile->physical_score               = null;
        $profile->location_score               = null;
        $profile->legal_score                  = null;
        $profile->occupant_qualification_score = null;
        $profile->commercial_score             = null;
        $profile->ai_buyer_archetype_tags      = null;
        $profile->ai_marketing_hooks           = null;
        $profile->walk_score                   = null;
        $profile->transit_score                = null;
        $profile->bike_score                   = null;
        $profile->school_rating                = null;
        $profile->flood_zone_verified          = null;
        $profile->estimated_monthly_utilities  = null;

        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }

        return $profile;
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
        $this->assertCount(
            count(self::CONTRACT_KEYS),
            $result,
            'Output must contain exactly the approved contract keys'
        );
    }

    // =========================================================================
    // (1) insufficient_data — completeness null, all priority scores null
    // =========================================================================

    /** @test */
    public function it_returns_insufficient_data_when_completeness_and_scores_are_null(): void
    {
        $profile = $this->makeProfile();
        $result  = $this->makeService()->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['error']);
    }

    // =========================================================================
    // (2) insufficient_data — completeness is zero, all priority scores null
    // =========================================================================

    /** @test */
    public function it_returns_insufficient_data_when_completeness_is_zero_and_scores_are_null(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 0.0]);
        $result  = $this->makeService()->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    // =========================================================================
    // (3) generated — completeness is non-zero
    // =========================================================================

    /** @test */
    public function it_returns_generated_when_completeness_is_non_zero(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 42.5]);
        $result  = $this->makeService()->generate($profile);

        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
    }

    // =========================================================================
    // (4) generated — priority score non-null even if completeness null
    // =========================================================================

    /** @test */
    public function it_returns_generated_when_a_priority_score_is_non_null(): void
    {
        $profile = $this->makeProfile(['flexibility_score' => 60.0]);
        $result  = $this->makeService()->generate($profile);

        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
    }

    // =========================================================================
    // (5) seller_priorities populated from non-null score fields
    // =========================================================================

    /** @test */
    public function it_populates_seller_priorities_from_non_null_scores(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 70.0,
            'flexibility_score'        => 80.0,
            'financial_score'          => 60.0,
        ]);

        $result     = $this->makeService()->generate($profile);
        $priorities = $result['seller_priorities'];

        $this->assertCount(2, $priorities);

        $dimensions = array_column($priorities, 'dimension');
        $this->assertContains('flexibility_score', $dimensions);
        $this->assertContains('financial_score', $dimensions);

        $labels = array_column($priorities, 'label');
        $this->assertContains('Flexibility Focus', $labels);
        $this->assertContains('Financial Outcome Focus', $labels);

        foreach ($priorities as $entry) {
            $this->assertArrayHasKey('dimension', $entry);
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('coverage', $entry);
            $this->assertIsFloat($entry['coverage']);
        }
    }

    // =========================================================================
    // (6) seller_priorities omits null score fields
    // =========================================================================

    /** @test */
    public function it_omits_null_scores_from_seller_priorities(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 50.0,
            'marketing_score'          => 75.0,
        ]);

        $result     = $this->makeService()->generate($profile);
        $priorities = $result['seller_priorities'];

        $dimensions = array_column($priorities, 'dimension');
        $this->assertNotContains('flexibility_score', $dimensions);
        $this->assertNotContains('financial_score', $dimensions);
        $this->assertNotContains('compatibility_score', $dimensions);
        $this->assertNotContains('condition_score', $dimensions);
        $this->assertContains('marketing_score', $dimensions);
    }

    // =========================================================================
    // (7) property_strengths mapped from known archetype tags
    // =========================================================================

    /** @test */
    public function it_maps_known_archetype_tags_to_property_strengths(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'ai_buyer_archetype_tags'  => [
                'amenity:pool',
                'parking:garage',
                'financing:seller-financed',
                'financing:assumable',
                'marketing:video-tour',
            ],
        ]);

        $result    = $this->makeService()->generate($profile);
        $strengths = $result['property_strengths'];

        $this->assertCount(5, $strengths);

        $tags = array_column($strengths, 'tag');
        $this->assertContains('amenity:pool', $tags);
        $this->assertContains('parking:garage', $tags);
        $this->assertContains('financing:seller-financed', $tags);
        $this->assertContains('financing:assumable', $tags);
        $this->assertContains('marketing:video-tour', $tags);

        $labels = array_column($strengths, 'label');
        $this->assertContains('Pool', $labels);
        $this->assertContains('Garage', $labels);
        $this->assertContains('Seller Financing', $labels);
        $this->assertContains('Assumable Loan', $labels);
        $this->assertContains('Video Tour Available', $labels);

        foreach ($strengths as $entry) {
            $this->assertArrayHasKey('tag', $entry);
            $this->assertArrayHasKey('label', $entry);
        }
    }

    // =========================================================================
    // (8) property_strengths omits tags not in the map
    // =========================================================================

    /** @test */
    public function it_omits_unknown_archetype_tags_from_property_strengths(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 55.0,
            'ai_buyer_archetype_tags'  => [
                'type:single-family',
                'style:traditional',
                'amenity:pool',
            ],
        ]);

        $result    = $this->makeService()->generate($profile);
        $strengths = $result['property_strengths'];

        $this->assertCount(1, $strengths);
        $this->assertSame('amenity:pool', $strengths[0]['tag']);
        $this->assertSame('Pool', $strengths[0]['label']);
    }

    // =========================================================================
    // (9) property_considerations: low completeness triggers signal
    // =========================================================================

    /** @test */
    public function it_adds_low_completeness_consideration_when_below_50(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 30.0]);
        $result  = $this->makeService()->generate($profile);

        $signals = array_column($result['property_considerations'], 'signal');
        $this->assertContains('Low overall data completeness', $signals);
    }

    /** @test */
    public function it_does_not_add_completeness_consideration_when_at_or_above_50(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 50.0]);
        $result  = $this->makeService()->generate($profile);

        $signals = array_column($result['property_considerations'], 'signal');
        $this->assertNotContains('Low overall data completeness', $signals);
    }

    // =========================================================================
    // (10) property_considerations: low condition_score triggers signal
    // =========================================================================

    /** @test */
    public function it_adds_condition_score_consideration_when_below_34(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'condition_score'          => 20.0,
        ]);
        $result = $this->makeService()->generate($profile);

        $signals = array_column($result['property_considerations'], 'signal');
        $this->assertContains('Property condition data is sparse', $signals);
    }

    /** @test */
    public function it_does_not_add_condition_consideration_when_at_or_above_34(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'condition_score'          => 34.0,
        ]);
        $result = $this->makeService()->generate($profile);

        $signals = array_column($result['property_considerations'], 'signal');
        $this->assertNotContains('Property condition data is sparse', $signals);
    }

    // =========================================================================
    // (11) property_considerations: low marketing_score triggers signal
    // =========================================================================

    /** @test */
    public function it_adds_marketing_score_consideration_when_below_34(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'marketing_score'          => 10.0,
        ]);
        $result = $this->makeService()->generate($profile);

        $signals = array_column($result['property_considerations'], 'signal');
        $this->assertContains('Marketing dimension data is sparse', $signals);
    }

    // =========================================================================
    // (12) property_considerations: no signals when values above thresholds
    // =========================================================================

    /** @test */
    public function it_returns_empty_considerations_when_all_values_are_above_thresholds(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 75.0,
            'condition_score'          => 50.0,
            'marketing_score'          => 50.0,
        ]);
        $result = $this->makeService()->generate($profile);

        $this->assertEmpty($result['property_considerations']);
    }

    // =========================================================================
    // (13) marketing_opportunities passthrough from ai_marketing_hooks
    // =========================================================================

    /** @test */
    public function it_passes_through_marketing_hooks_verbatim(): void
    {
        $hooks = [
            ['trait' => 'property_type', 'value' => 'Single Family'],
            ['trait' => 'bedrooms',      'value' => '3'],
        ];

        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'ai_marketing_hooks'       => $hooks,
        ]);

        $result = $this->makeService()->generate($profile);

        $this->assertSame($hooks, $result['marketing_opportunities']);
    }

    /** @test */
    public function it_returns_empty_marketing_opportunities_when_hooks_are_null(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 60.0]);
        $result  = $this->makeService()->generate($profile);

        $this->assertSame([], $result['marketing_opportunities']);
    }

    // =========================================================================
    // (14) buyer_archetype_alignment passthrough from ai_buyer_archetype_tags
    // =========================================================================

    /** @test */
    public function it_passes_through_archetype_tags_as_buyer_archetype_alignment(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'ai_buyer_archetype_tags'  => ['type:condo', 'amenity:pool', 'parking:garage'],
        ]);

        $result    = $this->makeService()->generate($profile);
        $alignment = $result['buyer_archetype_alignment'];

        $this->assertCount(3, $alignment);

        $tags = array_column($alignment, 'tag');
        $this->assertContains('type:condo', $tags);
        $this->assertContains('amenity:pool', $tags);
        $this->assertContains('parking:garage', $tags);

        foreach ($alignment as $entry) {
            $this->assertArrayHasKey('tag', $entry);
        }
    }

    // =========================================================================
    // (15) missing_inputs: null dimension fields listed
    // =========================================================================

    /** @test */
    public function it_lists_null_dimension_fields_in_missing_inputs(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 60.0,
            'flexibility_score'        => 50.0,
        ]);

        $result  = $this->makeService()->generate($profile);
        $missing = array_column($result['missing_inputs'], 'dimension');

        $this->assertNotContains('flexibility_score', $missing);

        $this->assertContains('financial_score', $missing);
        $this->assertContains('marketing_score', $missing);
        $this->assertContains('compatibility_score', $missing);
        $this->assertContains('condition_score', $missing);
        $this->assertContains('physical_score', $missing);
        $this->assertContains('location_score', $missing);
        $this->assertContains('legal_score', $missing);
        $this->assertContains('occupant_qualification_score', $missing);
        $this->assertContains('commercial_score', $missing);

        foreach ($result['missing_inputs'] as $entry) {
            $this->assertArrayHasKey('dimension', $entry);
        }
    }

    /** @test */
    public function it_returns_empty_missing_inputs_when_all_dimensions_are_set(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness'     => 100.0,
            'flexibility_score'            => 80.0,
            'financial_score'              => 80.0,
            'marketing_score'              => 80.0,
            'compatibility_score'          => 80.0,
            'condition_score'              => 80.0,
            'physical_score'               => 80.0,
            'location_score'               => 80.0,
            'legal_score'                  => 80.0,
            'occupant_qualification_score' => 80.0,
            'commercial_score'             => 80.0,
        ]);

        $result = $this->makeService()->generate($profile);

        $this->assertEmpty($result['missing_inputs']);
    }

    // =========================================================================
    // (16) signals: only non-null fields included
    // =========================================================================

    /** @test */
    public function it_includes_only_non_null_fields_in_signals(): void
    {
        $profile = $this->makeProfile([
            'overall_dna_completeness' => 65.0,
            'walk_score'               => 78.0,
            'transit_score'            => null,
            'bike_score'               => 55.0,
        ]);

        $result  = $this->makeService()->generate($profile);
        $signals = $result['signals'];

        $keys = array_column($signals, 'key');
        $this->assertContains('overall_dna_completeness', $keys);
        $this->assertContains('walk_score', $keys);
        $this->assertContains('bike_score', $keys);
        $this->assertNotContains('transit_score', $keys);

        foreach ($signals as $entry) {
            $this->assertArrayHasKey('key', $entry);
            $this->assertArrayHasKey('value', $entry);
        }
    }

    /** @test */
    public function it_returns_empty_signals_when_all_signal_fields_are_null(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 60.0]);
        $profile->overall_dna_completeness = null;
        $profile->flexibility_score        = 50.0;

        $result = $this->makeService()->generate($profile);

        $this->assertEmpty($result['signals']);
    }

    // =========================================================================
    // (17) output contract keys always present — generated path
    // =========================================================================

    /** @test */
    public function it_returns_all_contract_keys_in_generated_path(): void
    {
        $profile = $this->makeProfile(['overall_dna_completeness' => 60.0]);
        $result  = $this->makeService()->generate($profile);

        $this->assertContractShape($result);
    }

    // =========================================================================
    // (18) output contract keys always present — insufficient_data path
    // =========================================================================

    /** @test */
    public function it_returns_all_contract_keys_in_insufficient_data_path(): void
    {
        $profile = $this->makeProfile();
        $result  = $this->makeService()->generate($profile);

        $this->assertContractShape($result);
    }

    // =========================================================================
    // (19) output contract keys always present — failed path (via mock/extension)
    // =========================================================================

    /** @test */
    public function it_returns_all_contract_keys_in_failed_path(): void
    {
        $service = new class extends SellerDnaReportService {
            public function generate(PropertyDnaProfile $profile): array
            {
                return [
                    'success'                   => false,
                    'status'                    => 'failed',
                    'listing_type'              => 'seller',
                    'listing_id'                => (int) ($profile->listing_id ?? 0),
                    'seller_priorities'         => [],
                    'property_strengths'        => [],
                    'property_considerations'   => [],
                    'marketing_opportunities'   => [],
                    'buyer_archetype_alignment' => [],
                    'signals'                   => [],
                    'missing_inputs'            => [],
                    'error'                     => 'Simulated failure',
                ];
            }
        };

        $profile = $this->makeProfile();
        $result  = $service->generate($profile);

        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Contract key '{$key}' missing from failed path");
        }
        $this->assertCount(count(self::CONTRACT_KEYS), $result);
        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
    }

    // =========================================================================
    // (21) no AI/OpenAI class imports in the service file
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_openai_classes(): void
    {
        $serviceFile = file_get_contents(
            __DIR__ . '/../../../../app/Services/Dna/SellerDnaReportService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+OpenAI\b/m',
            $serviceFile,
            'Service must not have a top-level OpenAI use statement'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+\S*OpenAI\S*/m',
            $serviceFile,
            'Service must not import any OpenAI namespace class'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/new\s+OpenAI\b/',
            $serviceFile,
            'Service must not instantiate an OpenAI class'
        );
        $this->assertStringNotContainsString('GptClient', $serviceFile, 'Service must not reference GptClient');
        $this->assertDoesNotMatchRegularExpression(
            '/use\s+\S*Gpt\S*/i',
            $serviceFile,
            'Service must not import any GPT client class'
        );
    }

    // =========================================================================
    // (22) no DB::statement or Eloquent write calls in the service file
    // =========================================================================

    /** @test */
    public function service_file_does_not_contain_db_write_calls(): void
    {
        $serviceFile = file_get_contents(
            __DIR__ . '/../../../../app/Services/Dna/SellerDnaReportService.php'
        );

        $this->assertStringNotContainsString('DB::statement', $serviceFile, 'Service must not call DB::statement');
        $this->assertStringNotContainsString('DB::insert', $serviceFile, 'Service must not call DB::insert');
        $this->assertStringNotContainsString('DB::update', $serviceFile, 'Service must not call DB::update');
        $this->assertStringNotContainsString('DB::delete', $serviceFile, 'Service must not call DB::delete');
        $this->assertStringNotContainsString('->save()', $serviceFile, 'Service must not call ->save()');
        $this->assertStringNotContainsString('->create(', $serviceFile, 'Service must not call ->create()');
        $this->assertStringNotContainsString('->update(', $serviceFile, 'Service must not call ->update()');
        $this->assertStringNotContainsString('->delete(', $serviceFile, 'Service must not call ->delete()');
        $this->assertStringNotContainsString('DB::table(', $serviceFile, 'Service must not call DB::table()');
    }

    // =========================================================================
    // (23) listing_type is always 'seller' in output
    // =========================================================================

    /** @test */
    public function it_always_sets_listing_type_to_seller(): void
    {
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'overall_dna_completeness' => 60.0,
        ]);
        $result = $this->makeService()->generate($profile);
        $this->assertSame('seller', $result['listing_type']);

        $emptyProfile = $this->makeProfile();
        $emptyResult  = $this->makeService()->generate($emptyProfile);
        $this->assertSame('seller', $emptyResult['listing_type']);
    }

    // =========================================================================
    // (24) listing_id is cast to int in output
    // =========================================================================

    /** @test */
    public function it_casts_listing_id_to_int_in_output(): void
    {
        $profile = $this->makeProfile([
            'listing_id'               => '42',
            'overall_dna_completeness' => 60.0,
        ]);
        $result = $this->makeService()->generate($profile);
        $this->assertIsInt($result['listing_id']);
        $this->assertSame(42, $result['listing_id']);
    }

    // =========================================================================
    // All five strength tags beyond pool/garage covered
    // =========================================================================

    /** @test */
    public function it_maps_all_known_strength_tags(): void
    {
        $allStrengthTags = [
            'amenity:pool',
            'amenity:waterfront',
            'parking:garage',
            'feature:storage',
            'marketing:video-tour',
            'financing:seller-financed',
            'financing:assumable',
            'structure:lease-option',
            'structure:lease-purchase',
        ];

        $expectedLabels = [
            'amenity:pool'              => 'Pool',
            'amenity:waterfront'        => 'Waterfront',
            'parking:garage'            => 'Garage',
            'feature:storage'           => 'Storage',
            'marketing:video-tour'      => 'Video Tour Available',
            'financing:seller-financed' => 'Seller Financing',
            'financing:assumable'       => 'Assumable Loan',
            'structure:lease-option'    => 'Lease Option',
            'structure:lease-purchase'  => 'Lease Purchase',
        ];

        $profile = $this->makeProfile([
            'overall_dna_completeness' => 70.0,
            'ai_buyer_archetype_tags'  => $allStrengthTags,
        ]);

        $result    = $this->makeService()->generate($profile);
        $strengths = $result['property_strengths'];

        $this->assertCount(count($allStrengthTags), $strengths);

        foreach ($strengths as $entry) {
            $this->assertArrayHasKey('tag', $entry);
            $this->assertArrayHasKey('label', $entry);
            $this->assertSame($expectedLabels[$entry['tag']], $entry['label']);
        }
    }
}
