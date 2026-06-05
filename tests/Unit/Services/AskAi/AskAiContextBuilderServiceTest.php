<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiContextBuilderServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All model stubs are built in memory using property assignment.
 * AskAiContextBuilderService is tested via a partial mock that stubs
 * all protected finder methods, eliminating any Eloquent/DB calls.
 *
 * PropertyIntelligenceProfileService is constructor-injected and mocked so
 * buildPayloadReadOnly() returns controlled data without touching the DB.
 *
 * Test coverage (cases A–Q):
 *   A. not_found status when listing is absent
 *   B. Exact top-level output contract shape is always present (including 'error' key)
 *   C. CONTEXT_VERSION constant equals 'ASK_AI_CONTEXT_V1'
 *   D. No write call strings in the service file (static grep on non-comment lines)
 *   E. missing_sources lists expected-but-absent intelligence sections
 *   F. buyer_avatar populated for buyer listings; null for other types
 *   G. tenant_avatar populated for tenant listings; null for other types
 *   H. property_intelligence returns approved fields (strengths, highlights, positioning, etc.)
 *      via buildPayloadReadOnly(); null for buyer/tenant listings
 *   I. location_intelligence includes lifestyle_json and derived sub-fields
 *   J. compatibility populated only when demand+supply pair options are supplied
 *   K. offer_analysis includes all AcceptedBidSummary columns; null when absent
 *   L. source_versions contains available version values including lifestyle_version
 *   M. status is 'partial' when expected sources are missing; 'assembled' when all present
 *   N. location_intelligence includes nearest_highlights and thematic blocks when intelligence
 *      service returns available; optional fields absent when service returns missing
 *   O. marketing_context sub-key present in location_intelligence when marketing service available
 *   P. Non-available status from intelligence/marketing services adds warning; no missing_source
 *   Q. Governance scan — no fair housing, crime, demographic, or protected-class language
 *      introduced by the new Location DNA context paths
 */
class AskAiContextBuilderServiceTest extends TestCase
{
    private const REQUIRED_TOP_LEVEL_KEYS = [
        'success',
        'listing_type',
        'listing_id',
        'context_version',
        'status',
        'listing',
        'faq_answers',
        'property_intelligence',
        'location_intelligence',
        'buyer_avatar',
        'tenant_avatar',
        'compatibility',
        'offer_analysis',
        'missing_sources',
        'warnings',
        'source_versions',
        'assembled_at',
        'error',
    ];

    private const SOURCE_VERSIONS_KEYS = [
        'ask_ai_context',
        'property_intelligence_version',
        'location_dna_lifestyle_version',
        'buyer_avatar_version',
        'tenant_avatar_version',
        'compatibility_version',
    ];

    /**
     * The approved property intelligence field keys returned by buildPropertyIntelligence().
     */
    private const PROPERTY_INTELLIGENCE_KEYS = [
        'property_strengths',
        'property_highlights',
        'property_positioning',
        'property_target_audiences',
        'property_personality_tags',
        'property_story',
        'location_intelligence_context',
        'property_intelligence_version',
        'source_profile_id',
        'source_profile_version',
        'source_profile_computed_at',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
    }

    /**
     * Build a mock PropertyIntelligenceProfileService that allows us to stub
     * buildPayloadReadOnly() per test without touching the DB.
     *
     * @return PropertyIntelligenceProfileService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    /**
     * Build a mock LocationDnaIntelligenceContextService.
     * Default return value is a 'missing' status response (no merge happens).
     *
     * @return LocationDnaIntelligenceContextService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeLocationDnaIntelligenceServiceMock(
        ?array $returnValue = null
    ): LocationDnaIntelligenceContextService {
        $mock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn($returnValue ?? [
            'success'                       => false,
            'status'                        => 'missing',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'location_intelligence_context' => null,
            'error'                         => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    /**
     * Build a mock LocationDnaMarketingContextService.
     * Default return value is a 'missing' status response (no merge happens).
     *
     * @return LocationDnaMarketingContextService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeLocationDnaMarketingServiceMock(
        ?array $returnValue = null
    ): LocationDnaMarketingContextService {
        $mock = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn($returnValue ?? [
            'success'                    => false,
            'status'                     => 'missing',
            'listing_type'               => 'seller',
            'listing_id'                 => 1,
            'marketing_location_context' => null,
            'error'                      => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    /**
     * Build a canonical available response for LocationDnaIntelligenceContextService.
     */
    private function makeIntelligenceContextAvailable(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'available',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'location_intelligence_context' => [
                'coastal_features'    => ['nearest_beach_miles' => 2.3, 'nearest_marina_miles' => 1.1],
                'daily_convenience'   => ['nearest_grocery_miles' => 0.4],
                'outdoor_recreation'  => ['nearest_park_miles' => 0.7],
                'transportation'      => ['nearest_transit_miles' => 0.2],
                'nearest_highlights'  => [
                    'nearest_beach_miles'   => 2.3,
                    'nearest_marina_miles'  => 1.1,
                    'nearest_grocery_miles' => 0.4,
                    'nearest_park_miles'    => 0.7,
                    'nearest_transit_miles' => 0.2,
                ],
                'available_categories' => ['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'],
                'missing_categories'   => [],
            ],
            'error' => null,
        ], $overrides);
    }

    /**
     * Build a canonical available response for LocationDnaMarketingContextService.
     */
    private function makeMarketingContextAvailable(array $overrides = []): array
    {
        return array_merge([
            'success'                    => true,
            'status'                     => 'available',
            'listing_type'               => 'seller',
            'listing_id'                 => 1,
            'marketing_location_context' => [
                'coastal_features'    => ['nearest_beach_miles' => 2.3, 'nearest_marina_miles' => 1.1],
                'daily_convenience'   => ['nearest_grocery_miles' => 0.4],
                'outdoor_recreation'  => ['nearest_park_miles' => 0.7],
                'transportation'      => ['nearest_transit_miles' => 0.2],
                'available_categories' => ['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'],
                'missing_categories'   => [],
            ],
            'error' => null,
        ], $overrides);
    }

    /**
     * A successful buildPayloadReadOnly() return value carrying the approved fields.
     */
    private function makeIntelligencePayload(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'generated',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'property_strengths'            => ['Pool', 'Garage'],
            'property_highlights'           => ['Pool', 'Garage', 'Pets Allowed'],
            'property_positioning'          => 'Move-Up Home',
            'property_target_audiences'     => ['Move-Up Families'],
            'property_personality_tags'     => ['Outdoor Living', 'Family-Friendly'],
            'property_story'                => 'This property is a Move-Up Home.',
            'location_intelligence_context' => null,
            'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            'error'                         => null,
        ], $overrides);
    }

    /**
     * Build a partial mock of AskAiContextBuilderService that stubs only the
     * protected finder methods. All three constructor services are passed in;
     * the Location DNA services default to 'missing' return values so that
     * existing tests are unaffected (no merging happens, only warnings added).
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeService(
        ?PropertyIntelligenceProfileService $intelligenceService = null,
        ?LocationDnaIntelligenceContextService $locationDnaIntelligenceService = null,
        ?LocationDnaMarketingContextService $locationDnaMarketingService = null
    ): AskAiContextBuilderService {
        $intelligenceService          ??= $this->makeIntelligenceServiceMock();
        $locationDnaIntelligenceService ??= $this->makeLocationDnaIntelligenceServiceMock();
        $locationDnaMarketingService    ??= $this->makeLocationDnaMarketingServiceMock();

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([
                $intelligenceService,
                $locationDnaIntelligenceService,
                $locationDnaMarketingService,
            ])
            ->onlyMethods([
                'findListing',
                'findPropertyDnaProfile',
                'findPropertyLocationDna',
                'findBuyerTenantDnaProfile',
                'findCompatibilityScore',
                'findAcceptedBidSummary',
            ])
            ->getMock();
    }

    /**
     * Stub a minimal listing object so the service proceeds past not_found.
     */
    private function makeListingStub(): object
    {
        $stub = new \stdClass();
        $stub->id          = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    /**
     * Create a PropertyDnaProfile stub in memory (no DB).
     */
    private function makePropertyDnaProfile(array $attrs = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $profile->id                       = $attrs['id'] ?? 10;
        $profile->listing_type             = $attrs['listing_type'] ?? 'seller';
        $profile->listing_id               = $attrs['listing_id'] ?? 1;
        $profile->version                  = $attrs['version'] ?? 'v1';
        $profile->overall_dna_completeness = $attrs['overall_dna_completeness'] ?? 70.0;
        $profile->ai_buyer_archetype_tags  = $attrs['ai_buyer_archetype_tags'] ?? ['amenity:pool'];
        $profile->ai_marketing_hooks       = $attrs['ai_marketing_hooks'] ?? [];
        $profile->location_intelligence_context = $attrs['location_intelligence_context'] ?? null;
        $profile->computed_at              = $attrs['computed_at'] ?? null;
        $profile->archived_at              = null;
        return $profile;
    }

    /**
     * Create a PropertyLocationDna stub in memory (no DB).
     */
    private function makeLocationDna(array $attrs = []): PropertyLocationDna
    {
        $dna = new PropertyLocationDna();
        $dna->listing_type   = $attrs['listing_type'] ?? 'seller';
        $dna->listing_id     = $attrs['listing_id'] ?? 1;
        $dna->geocode_status = $attrs['geocode_status'] ?? 'success';
        $dna->lifestyle_json = $attrs['lifestyle_json'] ?? [
            'scores'     => ['walkability' => 72],
            'categories' => ['walkable'],
            'narrative'  => 'A walkable neighborhood.',
        ];
        $dna->generated_at   = $attrs['generated_at'] ?? null;
        return $dna;
    }

    /**
     * Create a BuyerTenantDnaProfile stub in memory (no DB).
     */
    private function makeAvatarProfile(array $attrs = []): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        $profile->listing_type              = $attrs['listing_type'] ?? 'buyer';
        $profile->listing_id                = $attrs['listing_id'] ?? 1;
        $profile->avatar_type               = $attrs['avatar_type'] ?? 'First-Time Buyer';
        $profile->primary_motivation        = $attrs['primary_motivation'] ?? 'stability';
        $profile->secondary_motivation      = $attrs['secondary_motivation'] ?? 'investment';
        $profile->buyer_narrative           = $attrs['buyer_narrative'] ?? 'Buyer narrative text.';
        $profile->buyer_preference_summary  = $attrs['buyer_preference_summary'] ?? ['min_beds' => 3];
        $profile->buyer_personality_tags    = $attrs['buyer_personality_tags'] ?? ['value-seeker'];
        $profile->buyer_match_preferences   = $attrs['buyer_match_preferences'] ?? [];
        $profile->avatar_confidence_score   = $attrs['avatar_confidence_score'] ?? 80;
        $profile->buyer_readiness_score     = $attrs['buyer_readiness_score'] ?? 75;
        $profile->buyer_avatar_version      = $attrs['buyer_avatar_version'] ?? 'BUYER_AVATAR_V1';
        $profile->tenant_narrative          = $attrs['tenant_narrative'] ?? 'Tenant narrative text.';
        $profile->tenant_preference_summary = $attrs['tenant_preference_summary'] ?? [];
        $profile->tenant_personality_tags   = $attrs['tenant_personality_tags'] ?? [];
        $profile->tenant_match_preferences  = $attrs['tenant_match_preferences'] ?? [];
        $profile->tenant_avatar_version     = $attrs['tenant_avatar_version'] ?? 'TENANT_AVATAR_V1';
        $profile->archived_at               = null;
        return $profile;
    }

    /**
     * Create a ListingCompatibilityScore stub in memory (no DB).
     */
    private function makeCompatibilityScore(array $attrs = []): ListingCompatibilityScore
    {
        $score = new ListingCompatibilityScore();
        $score->overall_score                = $attrs['overall_score'] ?? 82.5;
        $score->physical_match_score         = $attrs['physical_match_score'] ?? 85.0;
        $score->financial_match_score        = $attrs['financial_match_score'] ?? 80.0;
        $score->terms_match_score            = $attrs['terms_match_score'] ?? 78.0;
        $score->location_match_score         = $attrs['location_match_score'] ?? 87.0;
        $score->compatibility_summary_json   = $attrs['compatibility_summary_json'] ?? ['result' => 'strong'];
        $score->compatibility_highlights     = $attrs['compatibility_highlights'] ?? ['Price match'];
        $score->compatibility_warnings       = $attrs['compatibility_warnings'] ?? [];
        $score->compatibility_readiness_score= $attrs['compatibility_readiness_score'] ?? 0.9;
        $score->compatibility_narrative      = $attrs['compatibility_narrative'] ?? 'Strong match overall.';
        $score->score_explanation            = $attrs['score_explanation'] ?? [];
        $score->version                      = $attrs['version'] ?? 'BYA_COMPAT_V1';
        $score->computed_at                  = null;
        return $score;
    }

    /**
     * Create an AcceptedBidSummary stub in memory (no DB).
     */
    private function makeAcceptedBidSummary(array $attrs = []): AcceptedBidSummary
    {
        $summary = new AcceptedBidSummary();
        $summary->id                    = $attrs['id'] ?? 99;
        $summary->listing_type          = $attrs['listing_type'] ?? 'seller';
        $summary->listing_id            = $attrs['listing_id'] ?? 1;
        $summary->accepted_bid_id       = $attrs['accepted_bid_id'] ?? 55;
        $summary->accepted_counter_id   = $attrs['accepted_counter_id'] ?? null;
        $summary->tenant_user_id        = $attrs['tenant_user_id'] ?? 7;
        $summary->agent_user_id         = $attrs['agent_user_id'] ?? 8;
        $summary->summary_html          = $attrs['summary_html'] ?? '<p>Accepted offer summary.</p>';
        $summary->summary_pdf_path      = $attrs['summary_pdf_path'] ?? null;
        $summary->tenant_signature_name = $attrs['tenant_signature_name'] ?? 'Jane Doe';
        $summary->tenant_signed_at      = $attrs['tenant_signed_at'] ?? null;
        $summary->tenant_ip_address     = $attrs['tenant_ip_address'] ?? '127.0.0.1';
        $summary->tenant_timezone       = $attrs['tenant_timezone'] ?? 'America/New_York';
        $summary->tenant_user_agent     = $attrs['tenant_user_agent'] ?? 'Mozilla/5.0';
        $summary->agent_signature_name  = $attrs['agent_signature_name'] ?? 'John Smith';
        $summary->agent_signed_at       = $attrs['agent_signed_at'] ?? null;
        $summary->agent_ip_address      = $attrs['agent_ip_address'] ?? '127.0.0.2';
        $summary->agent_timezone        = $attrs['agent_timezone'] ?? 'America/Chicago';
        $summary->agent_user_agent      = $attrs['agent_user_agent'] ?? 'Mozilla/5.0';
        $summary->created_at            = null;
        $summary->updated_at            = null;
        return $summary;
    }

    // =========================================================================
    // Case A — not_found when listing is absent
    // =========================================================================

    public function test_case_A_returns_not_found_when_listing_is_absent(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('not_found', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertSame(AskAiContextBuilderService::CONTEXT_VERSION, $result['context_version']);
    }

    // =========================================================================
    // Case B — exact top-level contract shape is always present (incl. 'error')
    // =========================================================================

    public function test_case_B_output_contains_all_required_top_level_keys_not_found_path(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result,
                "Top-level key '{$key}' missing in not_found path"
            );
        }

        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertNull($result['error'], "'error' must be null in not_found path");
    }

    public function test_case_B_output_contains_all_required_top_level_keys_assembled_path(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result,
                "Top-level key '{$key}' missing in assembled path"
            );
        }

        $this->assertTrue($result['success']);
        $this->assertSame('buyer', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertNull($result['error'], "'error' must be null in non-failed path");
    }

    public function test_case_B_error_key_is_populated_on_failed_path(): void
    {
        $intellService       = $this->makeIntelligenceServiceMock();
        $locationDnaIntSvc   = $this->makeLocationDnaIntelligenceServiceMock();
        $locationDnaMktSvc   = $this->makeLocationDnaMarketingServiceMock();
        $service = $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intellService, $locationDnaIntSvc, $locationDnaMktSvc])
            ->onlyMethods(['findListing'])
            ->getMock();

        $service->method('findListing')->willThrowException(new \RuntimeException('DB offline'));

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertSame('DB offline', $result['error']);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Top-level key '{$key}' missing in failed path");
        }
    }

    // =========================================================================
    // Case C — CONTEXT_VERSION constant equals 'ASK_AI_CONTEXT_V1'
    // =========================================================================

    public function test_case_C_context_version_constant_is_correct(): void
    {
        $this->assertSame('ASK_AI_CONTEXT_V1', AskAiContextBuilderService::CONTEXT_VERSION);

        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);
        $result = $service->buildForListing('seller', 1);

        $this->assertSame('ASK_AI_CONTEXT_V1', $result['context_version']);
    }

    // =========================================================================
    // Case D — No write call strings in service file (static grep on code lines)
    // =========================================================================

    public function test_case_D_service_file_contains_no_write_or_openai_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        // Strip comment lines so prohibition keywords in governance docs don't false-positive.
        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        // Prohibited import/namespace patterns
        $prohibitedImports = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibitedImports as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not import or call '{$term}'"
            );
        }

        // Prohibited write/HTTP call patterns in non-comment code
        $prohibitedCalls = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            'Http::post',
            'Http::get',
            'curl_exec',
        ];

        foreach ($prohibitedCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain write/HTTP call '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case E — missing_sources lists expected-but-absent intelligence sections
    // =========================================================================

    public function test_case_E_missing_sources_lists_absent_intelligence_for_seller(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertContains('location_intelligence', $result['missing_sources']);
        $this->assertSame('partial', $result['status']);
    }

    public function test_case_E_missing_sources_when_buildPayloadReadOnly_returns_insufficient(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn([
            'success' => false,
            'status'  => 'insufficient_data',
            'error'   => 'No data',
        ]);

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertNull($result['property_intelligence']);
    }

    public function test_case_E_missing_sources_lists_absent_buyer_avatar(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $service->buildForListing('buyer', 1);

        $this->assertContains('buyer_avatar', $result['missing_sources']);
        $this->assertContains('location_intelligence', $result['missing_sources']);
    }

    // =========================================================================
    // Case F — buyer_avatar populated for buyer listings; null for non-buyer
    // =========================================================================

    public function test_case_F_buyer_avatar_populated_for_buyer_listing(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['listing_type' => 'buyer']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['buyer_avatar']);
        $this->assertArrayHasKey('avatar_type', $result['buyer_avatar']);
        $this->assertArrayHasKey('buyer_narrative', $result['buyer_avatar']);
        $this->assertArrayHasKey('buyer_avatar_version', $result['buyer_avatar']);
        $this->assertSame('First-Time Buyer', $result['buyer_avatar']['avatar_type']);
    }

    public function test_case_F_buyer_avatar_is_null_for_seller_listing(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertNull($result['buyer_avatar']);
    }

    // =========================================================================
    // Case G — tenant_avatar populated for tenant listings; null for non-tenant
    // =========================================================================

    public function test_case_G_tenant_avatar_populated_for_tenant_listing(): void
    {
        $avatarProfile = $this->makeAvatarProfile([
            'listing_type'          => 'tenant',
            'tenant_avatar_version' => 'TENANT_AVATAR_V1',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('tenant', 1);

        $this->assertNotNull($result['tenant_avatar']);
        $this->assertArrayHasKey('avatar_type', $result['tenant_avatar']);
        $this->assertArrayHasKey('tenant_narrative', $result['tenant_avatar']);
        $this->assertArrayHasKey('tenant_avatar_version', $result['tenant_avatar']);
        $this->assertSame('TENANT_AVATAR_V1', $result['tenant_avatar']['tenant_avatar_version']);
    }

    public function test_case_G_tenant_avatar_is_null_for_buyer_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['tenant_avatar']);
    }

    // =========================================================================
    // Case H — property_intelligence returns approved spec fields via buildPayloadReadOnly()
    //           null for buyer/tenant listings (only seller and landlord)
    // =========================================================================

    public function test_case_H_property_intelligence_contains_all_approved_fields_for_seller(): void
    {
        $intelligencePayload = $this->makeIntelligencePayload([
            'property_strengths'        => ['Pool', 'Garage'],
            'property_highlights'       => ['Pool', 'Garage'],
            'property_positioning'      => 'Move-Up Home',
            'property_target_audiences' => ['Move-Up Families'],
            'property_personality_tags' => ['Outdoor Living'],
            'property_story'            => 'This property is a Move-Up Home. Key features include Pool and Garage.',
        ]);

        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn($intelligencePayload);

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertNotNull($result['property_intelligence']);

        foreach (self::PROPERTY_INTELLIGENCE_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result['property_intelligence'],
                "property_intelligence missing approved field '{$key}'"
            );
        }

        $this->assertSame(['Pool', 'Garage'], $result['property_intelligence']['property_strengths']);
        $this->assertSame(['Pool', 'Garage'], $result['property_intelligence']['property_highlights']);
        $this->assertSame('Move-Up Home', $result['property_intelligence']['property_positioning']);
        $this->assertSame(['Move-Up Families'], $result['property_intelligence']['property_target_audiences']);
        $this->assertSame(['Outdoor Living'], $result['property_intelligence']['property_personality_tags']);
        $this->assertStringContainsString('Move-Up Home', $result['property_intelligence']['property_story']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $result['property_intelligence']['property_intelligence_version']);
        $this->assertSame(10, $result['property_intelligence']['source_profile_id']);
    }

    public function test_case_H_property_intelligence_populated_for_landlord(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload(['listing_type' => 'landlord']));

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')
            ->willReturn($this->makePropertyDnaProfile(['listing_type' => 'landlord']));
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('landlord', 1);

        $this->assertNotNull($result['property_intelligence']);
        $this->assertArrayHasKey('property_strengths', $result['property_intelligence']);
        $this->assertArrayHasKey('property_highlights', $result['property_intelligence']);
        $this->assertArrayHasKey('property_positioning', $result['property_intelligence']);
    }

    public function test_case_H_property_intelligence_is_null_for_buyer_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['property_intelligence']);
    }

    public function test_case_H_property_intelligence_is_null_for_tenant_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('tenant', 1);

        $this->assertNull($result['property_intelligence']);
    }

    // =========================================================================
    // Case I — location_intelligence includes lifestyle_json and derived sub-fields
    // =========================================================================

    public function test_case_I_location_intelligence_includes_lifestyle_json(): void
    {
        $lifestyleJson = [
            'scores'     => ['walkability' => 88, 'transit' => 60],
            'categories' => ['walkable', 'transit-friendly'],
            'narrative'  => 'A highly walkable urban neighborhood.',
            'version'    => 'LIFESTYLE_V2',
        ];

        $locationDna = $this->makeLocationDna([
            'lifestyle_json' => $lifestyleJson,
            'geocode_status' => 'success',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['location_intelligence']);
        $this->assertSame($lifestyleJson, $result['location_intelligence']['lifestyle_json']);
        $this->assertSame(
            ['walkability' => 88, 'transit' => 60],
            $result['location_intelligence']['lifestyle_scores']
        );
        $this->assertSame(
            ['walkable', 'transit-friendly'],
            $result['location_intelligence']['lifestyle_categories']
        );
        $this->assertSame(
            'A highly walkable urban neighborhood.',
            $result['location_intelligence']['location_narrative']
        );
        $this->assertSame('success', $result['location_intelligence']['geocode_status']);
        $this->assertSame('LIFESTYLE_V2', $result['location_intelligence']['lifestyle_version']);
    }

    // =========================================================================
    // Case J — compatibility populated only when demand+supply pair options supplied
    // =========================================================================

    public function test_case_J_compatibility_is_null_when_no_pair_options_supplied(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['compatibility']);
    }

    public function test_case_J_compatibility_is_null_when_only_demand_options_supplied(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
        ]);

        $this->assertNull($result['compatibility']);
    }

    public function test_case_J_compatibility_populated_when_pair_options_and_score_exists(): void
    {
        $compatScore = $this->makeCompatibilityScore(['overall_score' => 85.0]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findCompatibilityScore')->willReturn($compatScore);

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 5,
        ]);

        $this->assertNotNull($result['compatibility']);
        $this->assertSame(85.0, (float) $result['compatibility']['overall_score']);
        $this->assertArrayHasKey('compatibility_narrative', $result['compatibility']);
        $this->assertArrayHasKey('compatibility_highlights', $result['compatibility']);
    }

    public function test_case_J_compatibility_null_with_warning_when_pair_requested_but_no_score(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findCompatibilityScore')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 5,
        ]);

        $this->assertNull($result['compatibility']);
        $this->assertNotEmpty($result['warnings']);
    }

    // =========================================================================
    // Case K — offer_analysis includes all AcceptedBidSummary columns; null when absent
    // =========================================================================

    public function test_case_K_offer_analysis_null_when_no_accepted_bid_summary(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['offer_analysis']);
    }

    public function test_case_K_offer_analysis_includes_approved_deal_content_fields(): void
    {
        $summary = $this->makeAcceptedBidSummary([
            'id'               => 42,
            'listing_type'     => 'seller',
            'listing_id'       => 1,
            'accepted_bid_id'  => 77,
            'summary_html'     => '<p>Accepted offer.</p>',
            'summary_pdf_path' => 'summaries/seller/42.pdf',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findAcceptedBidSummary')->willReturn($summary);

        $result = $service->buildForListing('seller', 1);

        $oa = $result['offer_analysis'];
        $this->assertNotNull($oa);

        // Approved fields are present
        $this->assertSame(42, $oa['id']);
        $this->assertSame('seller', $oa['listing_type']);
        $this->assertSame(1, $oa['listing_id']);
        $this->assertSame(77, $oa['accepted_bid_id']);
        $this->assertArrayHasKey('accepted_counter_id', $oa);
        $this->assertSame('<p>Accepted offer.</p>', $oa['summary_html']);
        $this->assertSame('summaries/seller/42.pdf', $oa['summary_pdf_path']);
        $this->assertArrayHasKey('created_at', $oa);
        $this->assertArrayHasKey('updated_at', $oa);

        // PII / identity metadata must NOT appear
        $deniedKeys = [
            'tenant_user_id', 'agent_user_id',
            'tenant_signature_name', 'agent_signature_name',
            'tenant_signed_at', 'agent_signed_at',
            'tenant_ip_address', 'agent_ip_address',
            'tenant_timezone', 'agent_timezone',
            'tenant_user_agent', 'agent_user_agent',
        ];
        foreach ($deniedKeys as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $oa,
                "offer_analysis must not expose PII/identity field '{$key}'"
            );
        }
    }

    // =========================================================================
    // Case L — source_versions contains available version values
    // =========================================================================

    public function test_case_L_source_versions_always_contains_all_required_keys(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);
        $result = $service->buildForListing('seller', 1);

        foreach (self::SOURCE_VERSIONS_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result['source_versions'],
                "source_versions is missing key '{$key}'"
            );
        }

        $this->assertSame(
            AskAiContextBuilderService::CONTEXT_VERSION,
            $result['source_versions']['ask_ai_context']
        );
    }

    public function test_case_L_source_versions_carries_property_intelligence_version(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame(
            'PROPERTY_INTELLIGENCE_V1',
            $result['source_versions']['property_intelligence_version']
        );
    }

    public function test_case_L_source_versions_carries_buyer_avatar_version(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['buyer_avatar_version' => 'BUYER_AVATAR_V1']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('BUYER_AVATAR_V1', $result['source_versions']['buyer_avatar_version']);
    }

    public function test_case_L_source_versions_carries_location_dna_lifestyle_version(): void
    {
        $locationDna = $this->makeLocationDna([
            'lifestyle_json' => [
                'scores'     => ['walkability' => 80],
                'categories' => ['walkable'],
                'narrative'  => 'Walkable.',
                'version'    => 'LIFESTYLE_DNA_V3',
            ],
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('LIFESTYLE_DNA_V3', $result['source_versions']['location_dna_lifestyle_version']);
    }

    // =========================================================================
    // Case M — status 'partial' when missing sources; 'assembled' when all present
    // =========================================================================

    public function test_case_M_status_is_partial_when_property_intelligence_missing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('partial', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_sources']);
    }

    public function test_case_M_status_is_assembled_when_all_expected_sources_present_for_buyer(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['listing_type' => 'buyer']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('assembled', $result['status']);
        $this->assertEmpty($result['missing_sources']);
    }

    public function test_case_M_status_is_assembled_when_all_expected_sources_present_for_seller(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('assembled', $result['status']);
        $this->assertEmpty($result['missing_sources']);
    }

    public function test_case_M_status_is_not_found_for_absent_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 999);

        $this->assertSame('not_found', $result['status']);
    }

    // =========================================================================
    // Case N — location_intelligence includes nearest_highlights and thematic blocks
    //           when LocationDnaIntelligenceContextService returns available
    // =========================================================================

    public function test_case_N_location_intelligence_includes_nearest_highlights_when_service_available(): void
    {
        $dnaIntelligenceMock = $this->makeLocationDnaIntelligenceServiceMock(
            $this->makeIntelligenceContextAvailable()
        );

        $service = $this->makeService(null, $dnaIntelligenceMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['location_intelligence']);
        $li = $result['location_intelligence'];
        $this->assertArrayHasKey('nearest_highlights', $li);
        $this->assertArrayHasKey('available_categories', $li);
        $this->assertArrayHasKey('missing_categories', $li);
        $this->assertNotEmpty($li['nearest_highlights']);
        $this->assertNotEmpty($li['available_categories']);
    }

    public function test_case_N_location_intelligence_includes_all_four_thematic_blocks_when_available(): void
    {
        $dnaIntelligenceMock = $this->makeLocationDnaIntelligenceServiceMock(
            $this->makeIntelligenceContextAvailable()
        );

        $service = $this->makeService(null, $dnaIntelligenceMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $li = $result['location_intelligence'];
        $this->assertArrayHasKey('coastal_features', $li);
        $this->assertArrayHasKey('daily_convenience', $li);
        $this->assertArrayHasKey('outdoor_recreation', $li);
        $this->assertArrayHasKey('transportation', $li);
    }

    public function test_case_N_thematic_blocks_absent_when_intelligence_service_returns_missing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $li = $result['location_intelligence'];
        $this->assertNotNull($li, 'location_intelligence should still be populated from lifestyle_json');
        $this->assertArrayNotHasKey('nearest_highlights', $li);
        $this->assertArrayNotHasKey('coastal_features', $li);
        $this->assertArrayNotHasKey('daily_convenience', $li);
        $this->assertArrayNotHasKey('outdoor_recreation', $li);
        $this->assertArrayNotHasKey('transportation', $li);
    }

    public function test_case_N_lifestyle_json_fields_always_present_regardless_of_intelligence_service(): void
    {
        $lifestyleJson = [
            'scores'     => ['walkability' => 90],
            'categories' => ['walkable'],
            'narrative'  => 'Very walkable area.',
            'version'    => 'LIFESTYLE_V3',
        ];

        $locationDna = $this->makeLocationDna(['lifestyle_json' => $lifestyleJson]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $li = $result['location_intelligence'];
        $this->assertSame($lifestyleJson, $li['lifestyle_json']);
        $this->assertSame('Very walkable area.', $li['location_narrative']);
        $this->assertSame(['walkability' => 90], $li['lifestyle_scores']);
        $this->assertSame(['walkable'], $li['lifestyle_categories']);
        $this->assertSame('LIFESTYLE_V3', $li['lifestyle_version']);
    }

    // =========================================================================
    // Case O — marketing_context sub-key present in location_intelligence
    //           when LocationDnaMarketingContextService returns available
    // =========================================================================

    public function test_case_O_marketing_context_present_when_marketing_service_available(): void
    {
        $marketingMock = $this->makeLocationDnaMarketingServiceMock(
            $this->makeMarketingContextAvailable()
        );

        $service = $this->makeService(null, null, $marketingMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $li = $result['location_intelligence'];
        $this->assertNotNull($li);
        $this->assertArrayHasKey('marketing_context', $li);
        $this->assertIsArray($li['marketing_context']);
        $this->assertArrayHasKey('coastal_features', $li['marketing_context']);
        $this->assertArrayHasKey('daily_convenience', $li['marketing_context']);
        $this->assertArrayHasKey('outdoor_recreation', $li['marketing_context']);
        $this->assertArrayHasKey('transportation', $li['marketing_context']);
    }

    public function test_case_O_marketing_context_absent_when_marketing_service_returns_missing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $li = $result['location_intelligence'];
        $this->assertNotNull($li);
        $this->assertArrayNotHasKey('marketing_context', $li);
    }

    public function test_case_O_marketing_context_available_categories_present(): void
    {
        $marketingMock = $this->makeLocationDnaMarketingServiceMock(
            $this->makeMarketingContextAvailable()
        );

        $service = $this->makeService(null, null, $marketingMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertArrayHasKey('available_categories', $result['location_intelligence']['marketing_context']);
        $this->assertNotEmpty($result['location_intelligence']['marketing_context']['available_categories']);
    }

    // =========================================================================
    // Case P — Non-available status from intelligence/marketing services
    //           adds warning; does not add to missing_sources;
    //           lifestyle_json-derived fields still present
    // =========================================================================

    public function test_case_P_missing_intelligence_service_adds_warning_not_missing_source(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotContains('location_intelligence', $result['missing_sources'],
            'location_intelligence must not be in missing_sources when lifestyle_json record exists');
        $this->assertNotEmpty($result['warnings'],
            'A warning should be recorded when intelligence service returns non-available');
    }

    public function test_case_P_missing_marketing_service_adds_warning_not_missing_source(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotContains('location_intelligence', $result['missing_sources'],
            'location_intelligence must not be in missing_sources when marketing service is missing');
        $warningsText = implode(' ', $result['warnings']);
        $this->assertStringContainsString('location', strtolower($warningsText),
            'Warnings should mention location context availability');
    }

    public function test_case_P_location_intelligence_still_populated_when_both_sub_services_missing(): void
    {
        $locationDna = $this->makeLocationDna([
            'lifestyle_json' => [
                'scores'     => ['walkability' => 75],
                'categories' => ['walkable'],
                'narrative'  => 'Walkable neighborhood.',
                'version'    => 'V5',
            ],
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['location_intelligence'],
            'location_intelligence must still be populated from lifestyle_json even when sub-services are missing');
        $this->assertSame('Walkable neighborhood.', $result['location_intelligence']['location_narrative']);
        $this->assertSame(['walkability' => 75], $result['location_intelligence']['lifestyle_scores']);
    }

    public function test_case_P_no_missing_sources_when_dna_record_exists_but_sub_services_missing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotContains(
            'location_intelligence',
            $result['missing_sources'],
            'location_intelligence must not be in missing_sources when the base PropertyLocationDna record exists'
        );
    }

    // =========================================================================
    // Case Q — Governance scan: no fair housing, crime, demographic, or
    //           protected-class language in new Location DNA context paths
    // =========================================================================

    public function test_case_Q_governance_no_protected_class_language_in_nearest_highlights(): void
    {
        $dnaIntelligenceMock = $this->makeLocationDnaIntelligenceServiceMock(
            $this->makeIntelligenceContextAvailable()
        );

        $service = $this->makeService(null, $dnaIntelligenceMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $nearestHighlights = $result['location_intelligence']['nearest_highlights'] ?? [];
        $asText            = json_encode($nearestHighlights);

        $prohibited = [
            'race', 'color', 'religion', 'sex', 'national origin',
            'familial status', 'disability', 'crime', 'criminal',
            'demographic', 'minority', 'ethnic', 'school district rating',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $asText,
                "nearest_highlights must not contain protected-class language: '{$term}'"
            );
        }
    }

    public function test_case_Q_governance_no_protected_class_language_in_thematic_blocks(): void
    {
        $dnaIntelligenceMock = $this->makeLocationDnaIntelligenceServiceMock(
            $this->makeIntelligenceContextAvailable()
        );

        $service = $this->makeService(null, $dnaIntelligenceMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $li      = $result['location_intelligence'];
        $blocks  = array_filter([
            $li['coastal_features']   ?? null,
            $li['daily_convenience']  ?? null,
            $li['outdoor_recreation'] ?? null,
            $li['transportation']     ?? null,
        ]);
        $asText = json_encode($blocks);

        $prohibited = [
            'race', 'color', 'religion', 'sex', 'national origin',
            'familial status', 'disability', 'crime', 'criminal',
            'demographic', 'minority', 'ethnic',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $asText,
                "Thematic blocks must not contain protected-class language: '{$term}'"
            );
        }
    }

    public function test_case_Q_governance_no_protected_class_language_in_marketing_context(): void
    {
        $marketingMock = $this->makeLocationDnaMarketingServiceMock(
            $this->makeMarketingContextAvailable()
        );

        $service = $this->makeService(null, null, $marketingMock);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $marketingContext = $result['location_intelligence']['marketing_context'] ?? [];
        $asText           = json_encode($marketingContext);

        $prohibited = [
            'race', 'color', 'religion', 'sex', 'national origin',
            'familial status', 'disability', 'crime', 'criminal',
            'demographic', 'minority', 'ethnic', 'school district rating',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $asText,
                "marketing_context must not contain protected-class language: '{$term}'"
            );
        }
    }

    public function test_case_Q_service_file_contains_no_protected_class_language_in_location_intelligence_method(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'crime',
            'criminal',
            'demographic',
            'school district rating',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $codeLines,
                "Service file must not reference prohibited governance term: '{$term}'"
            );
        }
    }

    // =========================================================================
    // Helpers for Cases R / S (factual fields + FAQ wiring)
    // =========================================================================

    /**
     * Build a listing stub with arbitrary native column values.
     * Optionally, an EAV meta store can be provided via the $meta array.
     * When $meta is non-empty, the stub gains an info() method.
     *
     * @param  array $native  Associative array of native column values.
     * @param  array $meta    Associative array of EAV meta values (optional).
     * @return object
     */
    private function makeListingStubWithFields(array $native = [], array $meta = []): object
    {
        return new class($native, $meta) {
            public int    $id          = 1;
            public bool   $is_approved = true;
            public string $created_at  = '2026-01-01 00:00:00';
            public string $updated_at  = '2026-01-01 00:00:00';
            private array $metaStore;
            private array $dynamicProps = [];

            public function __construct(array $native, array $meta)
            {
                $this->metaStore = $meta;
                foreach ($native as $key => $value) {
                    $this->dynamicProps[$key] = $value;
                }
            }

            public function __set(string $name, mixed $value): void
            {
                $this->dynamicProps[$name] = $value;
            }

            public function __get(string $name): mixed
            {
                return $this->dynamicProps[$name] ?? null;
            }

            public function __isset(string $name): bool
            {
                return isset($this->dynamicProps[$name]);
            }

            public function info(string $key): mixed
            {
                return $this->metaStore[$key] ?? null;
            }
        };
    }

    // =========================================================================
    // Case R — Factual fields are extracted into listing context per role
    //
    // Each test verifies that the expected field key appears in the returned
    // listing context when the underlying stub property is populated.
    // =========================================================================

    public function test_case_R_seller_asking_price_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['starting_price' => '450000'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('asking_price', $result['listing'],
            "listing context must include 'asking_price' for seller");
        $this->assertSame('450000', $result['listing']['asking_price']);
    }

    public function test_case_R_seller_bedrooms_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['bedrooms' => '3'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('bedrooms', $result['listing'],
            "listing context must include 'bedrooms' for seller");
        $this->assertSame('3', $result['listing']['bedrooms']);
    }

    public function test_case_R_seller_year_built_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['year_built' => '2005'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('year_built', $result['listing'],
            "listing context must include 'year_built' for seller");
        $this->assertSame('2005', $result['listing']['year_built']);
    }

    public function test_case_R_seller_hoa_fee_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['hoa_fee' => '200'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('hoa_fee', $result['listing'],
            "listing context must include 'hoa_fee' for seller");
    }

    public function test_case_R_seller_pets_allowed_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['pets_allowed' => '1'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('pets_allowed', $result['listing'],
            "listing context must include 'pets_allowed' for seller");
    }

    public function test_case_R_seller_showing_instructions_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['showing_instructions' => 'Call agent at 555-0100'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('showing_instructions', $result['listing'],
            "listing context must include 'showing_instructions' (EAV) for seller");
        $this->assertSame('Call agent at 555-0100', $result['listing']['showing_instructions']);
    }

    public function test_case_R_buyer_max_price_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['max_price' => '500000'])
        );

        $result = $service->buildForListing('buyer', 1);

        $this->assertArrayHasKey('max_price', $result['listing'],
            "listing context must include 'max_price' for buyer");
        $this->assertSame('500000', $result['listing']['max_price']);
    }

    public function test_case_R_buyer_bedrooms_native_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['bedrooms' => '3'])
        );

        $result = $service->buildForListing('buyer', 1);

        $this->assertArrayHasKey('bedrooms', $result['listing'],
            "listing context must include 'bedrooms' for buyer");
        $this->assertSame('3', $result['listing']['bedrooms']);
    }

    public function test_case_R_landlord_rent_amount_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['maximum_budget' => '1800'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('rent_amount', $result['listing'],
            "listing context must include 'rent_amount' for landlord");
        $this->assertSame('1800', $result['listing']['rent_amount']);
    }

    public function test_case_R_landlord_pet_policy_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['pet_policy' => 'No pets'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('pet_policy', $result['listing'],
            "listing context must include 'pet_policy' for landlord");
        $this->assertSame('No pets', $result['listing']['pet_policy']);
    }

    public function test_case_R_landlord_appliances_json_decoded_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['appliances' => '["Washer","Dryer","Dishwasher"]'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('appliances', $result['listing'],
            "listing context must include 'appliances' for landlord");
        $this->assertStringContainsString('Washer', (string) $result['listing']['appliances'],
            "'appliances' must be decoded from JSON to a readable string");
        $this->assertStringContainsString('Dryer', (string) $result['listing']['appliances']);
    }

    public function test_case_R_tenant_max_rent_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['maximum_budget' => '1500'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('max_rent', $result['listing'],
            "listing context must include 'max_rent' for tenant");
        $this->assertSame('1500', $result['listing']['max_rent']);
    }

    public function test_case_R_tenant_desired_lease_length_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['tenant_desired_lease_length' => '12 months'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('desired_lease_length', $result['listing'],
            "listing context must include 'desired_lease_length' for tenant");
        $this->assertSame('12 months', $result['listing']['desired_lease_length']);
    }

    public function test_case_R_null_factual_fields_are_preserved_as_null_not_dropped(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields()
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('asking_price', $result['listing'],
            "listing context must always include 'asking_price' key even when value is null");
        $this->assertNull($result['listing']['asking_price'],
            "'asking_price' must be null, not absent, when not set on the listing");
    }

    // =========================================================================
    // Case S — faq_answers key is present and wired correctly
    // =========================================================================

    public function test_case_S_faq_answers_key_present_in_buildForListing_result(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('faq_answers', $result,
            "buildForListing must return a top-level 'faq_answers' key");
    }

    public function test_case_S_faq_answers_is_array(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());

        $result = $service->buildForListing('seller', 1);

        $this->assertIsArray($result['faq_answers'],
            "'faq_answers' must always be an array, never null");
    }

    public function test_case_S_faq_answers_empty_when_listing_has_no_faq_data(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());

        $result = $service->buildForListing('seller', 1);

        $this->assertIsArray($result['faq_answers']);
    }

    public function test_case_S_faq_answers_populated_from_seller_eav_json(): void
    {
        $faqData = ['roof_age' => 'Replaced in 2020', 'hvac' => 'Serviced in 2024'];

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => json_encode($faqData)])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertIsArray($result['faq_answers']);
        $this->assertArrayHasKey('roof_age', $result['faq_answers'],
            "faq_answers must include 'roof_age' from EAV listing_ai_faq JSON");
        $this->assertIsArray($result['faq_answers']['roof_age'],
            "Each faq_answers entry must be an enriched array, not a raw string");
        $this->assertSame('Replaced in 2020', $result['faq_answers']['roof_age']['answer_text'],
            "answer_text must contain the original answer string");
        $this->assertArrayHasKey('config_key', $result['faq_answers']['roof_age']);
        $this->assertArrayHasKey('question_label', $result['faq_answers']['roof_age']);
        $this->assertArrayHasKey('question_group', $result['faq_answers']['roof_age']);
        $this->assertArrayHasKey('intelligence_category', $result['faq_answers']['roof_age']);
    }

    public function test_case_S_faq_answers_populated_from_landlord_eav_json(): void
    {
        $faqData = ['parking_details' => 'One assigned spot in garage', 'laundry' => 'In-unit washer/dryer'];

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => json_encode($faqData)])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertIsArray($result['faq_answers']);
        $this->assertArrayHasKey('parking_details', $result['faq_answers']);
        $this->assertIsArray($result['faq_answers']['parking_details']);
        $this->assertSame('One assigned spot in garage', $result['faq_answers']['parking_details']['answer_text']);
    }

    public function test_case_S_faq_answers_populated_from_tenant_native_column(): void
    {
        $faqData = ['move_in_flexibility' => 'Can move in within 30 days'];

        $stub                   = $this->makeListingStub();
        $stub->listing_ai_faq   = json_encode($faqData);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($stub);

        $result = $service->buildForListing('tenant', 1);

        $this->assertIsArray($result['faq_answers']);
        $this->assertArrayHasKey('move_in_flexibility', $result['faq_answers']);
        $this->assertIsArray($result['faq_answers']['move_in_flexibility']);
        $this->assertSame('Can move in within 30 days', $result['faq_answers']['move_in_flexibility']['answer_text']);
    }

    public function test_case_S_faq_answers_skips_null_and_empty_values(): void
    {
        $faqData = ['good_key' => 'Has value', 'empty_key' => '', 'null_key' => null];

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => json_encode($faqData)])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('good_key', $result['faq_answers']);
        $this->assertIsArray($result['faq_answers']['good_key']);
        $this->assertSame('Has value', $result['faq_answers']['good_key']['answer_text']);
        $this->assertArrayNotHasKey('empty_key', $result['faq_answers'],
            "faq_answers must exclude keys with empty string values");
        $this->assertArrayNotHasKey('null_key', $result['faq_answers'],
            "faq_answers must exclude keys with null values");
    }

    public function test_case_S_faq_answers_enriched_shape_has_all_required_keys(): void
    {
        $faqData = ['roof_age_and_condition' => 'Replaced in 2020'];

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => json_encode($faqData)])
        );

        $result = $service->buildForListing('seller', 1);

        $entry = $result['faq_answers']['roof_age_and_condition'] ?? null;
        $this->assertIsArray($entry, "Known config key must produce an enriched array entry");
        $this->assertArrayHasKey('config_key',            $entry);
        $this->assertArrayHasKey('answer_text',           $entry);
        $this->assertArrayHasKey('question_label',        $entry);
        $this->assertArrayHasKey('question_group',        $entry);
        $this->assertArrayHasKey('intelligence_category', $entry);
        $this->assertSame('roof_age_and_condition',       $entry['config_key']);
        $this->assertSame('Replaced in 2020',             $entry['answer_text']);
        $this->assertNotNull($entry['question_label'],    "Known config key must resolve a non-null question_label");
        $this->assertNotNull($entry['question_group'],    "Known config key must resolve a non-null question_group");
        $this->assertNotNull($entry['intelligence_category'], "Known config key must resolve a non-null intelligence_category");
    }

    public function test_case_S_faq_answers_unknown_key_gets_null_metadata(): void
    {
        $faqData = ['some_unknown_key' => 'Answer text here'];

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => json_encode($faqData)])
        );

        $result = $service->buildForListing('seller', 1);

        $entry = $result['faq_answers']['some_unknown_key'] ?? null;
        $this->assertIsArray($entry, "Unknown keys must still produce an enriched array entry");
        $this->assertSame('some_unknown_key', $entry['config_key']);
        $this->assertSame('Answer text here', $entry['answer_text']);
        $this->assertNull($entry['question_label'],        "Unknown key must have null question_label");
        $this->assertNull($entry['question_group'],        "Unknown key must have null question_group");
        $this->assertNull($entry['intelligence_category'], "Unknown key must have null intelligence_category");
    }

    public function test_case_S_faq_answers_present_in_empty_payload_and_is_array(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('faq_answers', $result,
            "'faq_answers' must be present even in not_found payload");
        $this->assertIsArray($result['faq_answers'],
            "'faq_answers' must be an empty array in not_found payload");
    }

    public function test_case_S_faq_answers_survives_malformed_json_gracefully(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['listing_ai_faq' => 'this is not json {{{'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertIsArray($result['faq_answers'],
            "faq_answers must always be an array even when JSON is malformed");
    }
}
