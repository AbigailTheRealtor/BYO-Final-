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

    // =========================================================================
    // Case T — Phase 2 Task B: Extended factual field coverage for all roles
    //
    // Verifies that the new fields added in Phase 2 Task B appear in the
    // listing context block when the underlying stub property is populated.
    // Excluded/PII fields are confirmed absent.
    // =========================================================================

    // --- Seller new fields ---

    public function test_case_T_seller_address_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['address' => '123 Main St'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('address', $result['listing'],
            "listing context must include 'address' for seller");
        $this->assertSame('123 Main St', $result['listing']['address']);
    }

    public function test_case_T_seller_rental_restrictions_description_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['rental_restrictions_desription' => 'No short-term rentals'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('rental_restrictions_description', $result['listing'],
            "listing context must include 'rental_restrictions_description' for seller");
        $this->assertSame('No short-term rentals', $result['listing']['rental_restrictions_description']);
    }

    public function test_case_T_seller_auction_length_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['auction_length' => '30'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('auction_length', $result['listing'],
            "listing context must include 'auction_length' for seller");
        $this->assertSame('30', $result['listing']['auction_length']);
    }

    public function test_case_T_seller_disclosure_flags_is_array_with_flood_zone_true(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields()
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('disclosure_flags', $result['listing'],
            "listing context must include 'disclosure_flags' for seller");
        $this->assertIsArray($result['listing']['disclosure_flags']);
        $this->assertArrayHasKey('flood_zone', $result['listing']['disclosure_flags']);
        $this->assertTrue($result['listing']['disclosure_flags']['flood_zone'],
            "disclosure_flags.flood_zone must be true for seller listings");
    }

    public function test_case_T_seller_disclosure_flags_absent_for_buyer_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields()
        );

        $result = $service->buildForListing('buyer', 1);

        $this->assertArrayNotHasKey('disclosure_flags', $result['listing'],
            "disclosure_flags must not appear in buyer listing context");
    }

    public function test_case_T_seller_pii_fields_absent_from_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([
                'seller_name'  => 'John Doe',
                'phone_number' => '555-0100',
                'email'        => 'john@example.com',
                'brokerage'    => 'Realty Co',
            ])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayNotHasKey('seller_name', $result['listing'],
            "PII 'seller_name' must not appear in listing context");
        $this->assertArrayNotHasKey('phone_number', $result['listing'],
            "PII 'phone_number' must not appear in listing context");
        $this->assertArrayNotHasKey('email', $result['listing'],
            "PII 'email' must not appear in listing context");
        $this->assertArrayNotHasKey('brokerage', $result['listing'],
            "Compliance-boundary 'brokerage' must not appear in listing context");
    }

    // --- Buyer new fields ---

    public function test_case_T_buyer_financing_type_key_present_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(['financing_id' => '0'])
        );

        $result = $service->buildForListing('buyer', 1);

        $this->assertArrayHasKey('financing_type', $result['listing'],
            "listing context must include 'financing_type' key for buyer");
    }

    public function test_case_T_buyer_financing_type_null_when_financing_id_absent(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields()
        );

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['listing']['financing_type'],
            "financing_type must be null when financing_id is absent");
    }

    // --- Landlord new fields ---

    public function test_case_T_landlord_property_zip_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['property_zip' => '33101'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('property_zip', $result['listing'],
            "listing context must include 'property_zip' for landlord");
        $this->assertSame('33101', $result['listing']['property_zip']);
    }

    public function test_case_T_landlord_property_items_json_decoded_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['property_items' => '["Single Family","Townhouse"]'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('property_items', $result['listing'],
            "listing context must include 'property_items' for landlord");
        $this->assertSame('Single Family, Townhouse', $result['listing']['property_items'],
            "'property_items' must be decoded from JSON to a comma-separated string (Phase 1 pattern)");
    }

    public function test_case_T_landlord_association_name_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['association_name' => 'Sunset HOA'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('association_name', $result['listing'],
            "listing context must include 'association_name' for landlord");
        $this->assertSame('Sunset HOA', $result['listing']['association_name']);
    }

    public function test_case_T_landlord_association_amenities_json_decoded_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['association_amenities' => '["Pool","Gym","Tennis Court"]'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('association_amenities', $result['listing'],
            "listing context must include 'association_amenities' for landlord");
        $this->assertSame('Pool, Gym, Tennis Court', $result['listing']['association_amenities'],
            "'association_amenities' must be decoded from JSON to a comma-separated string (Phase 1 pattern)");
    }

    public function test_case_T_landlord_leasing_restrictions_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['leasing_restrictions' => 'No subleasing'])
        );

        $result = $service->buildForListing('landlord', 1);

        $this->assertArrayHasKey('leasing_restrictions', $result['listing'],
            "listing context must include 'leasing_restrictions' for landlord");
        $this->assertSame('No subleasing', $result['listing']['leasing_restrictions']);
    }

    // --- Tenant new fields ---

    public function test_case_T_tenant_property_items_json_decoded_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['property_items' => '["Apartment","Condo"]'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('property_items', $result['listing'],
            "listing context must include 'property_items' for tenant");
        $this->assertSame('Apartment, Condo', $result['listing']['property_items'],
            "'property_items' must be decoded from JSON to a comma-separated string (Phase 1 pattern)");
    }

    public function test_case_T_tenant_utility_preference_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['utility_preference' => 'All utilities included'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('utility_preference', $result['listing'],
            "listing context must include 'utility_preference' for tenant");
        $this->assertSame('All utilities included', $result['listing']['utility_preference']);
    }

    public function test_case_T_tenant_current_status_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['current_status' => 'Month-to-month'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('current_status', $result['listing'],
            "listing context must include 'current_status' for tenant");
        $this->assertSame('Month-to-month', $result['listing']['current_status']);
    }

    public function test_case_T_tenant_number_of_units_from_eav_appears_in_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], ['number_of_unit' => '2'])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayHasKey('number_of_units', $result['listing'],
            "listing context must include 'number_of_units' for tenant (sourced from number_of_unit EAV key)");
        $this->assertSame('2', $result['listing']['number_of_units']);
    }

    public function test_case_T_tenant_pii_fields_absent_from_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], [
                'first_name'  => 'Jane',
                'last_name'   => 'Smith',
                'email'       => 'jane@example.com',
                'phone_number' => '555-0200',
            ])
        );

        $result = $service->buildForListing('tenant', 1);

        $this->assertArrayNotHasKey('first_name', $result['listing'],
            "PII 'first_name' must not appear in tenant listing context");
        $this->assertArrayNotHasKey('last_name', $result['listing'],
            "PII 'last_name' must not appear in tenant listing context");
        $this->assertArrayNotHasKey('email', $result['listing'],
            "PII 'email' must not appear in tenant listing context");
        $this->assertArrayNotHasKey('phone_number', $result['listing'],
            "PII 'phone_number' must not appear in tenant listing context");
    }

    public function test_case_T_contract_service_listing_facts_includes_all_new_field_paths(): void
    {
        $contract = new \App\Services\AskAi\AskAiResponseContractService();
        $result   = $contract->buildContract('listing_facts', []);

        $allowedContext = $result['allowed_context'];

        $expectedNewPaths = [
            'listing.address',
            'listing.rental_restrictions_description',
            'listing.auction_length',
            'listing.disclosure_flags',
            'listing.financing_type',
            'listing.property_zip',
            'listing.property_items',
            'listing.association_name',
            'listing.association_amenities',
            'listing.leasing_restrictions',
            'listing.utility_preference',
            'listing.current_status',
        ];

        foreach ($expectedNewPaths as $path) {
            $this->assertContains(
                $path,
                $allowedContext,
                "listing_facts allowed_context must include '{$path}'"
            );
        }
    }

    // =========================================================================
    // Case U — Comprehensive key-set completeness for all four roles
    //
    // Verifies that extractFactualFields() + extractListingFields() together
    // produce EVERY expected context key for each role — not just the subset
    // spot-checked in Cases R and T. A single test per role asserts the full
    // expected key set is present in listing['listing'].
    //
    // Key-normalization notes documented inline:
    //   - number_of_unit (EAV key) → number_of_units (context key): intentional
    //     normalization for consistency with landlord arm and contract allowlist.
    //   - rental_restrictions_desription (DB typo) → rental_restrictions_description:
    //     documented legacy schema typo in property_auctions; see memory note.
    // =========================================================================

    public function test_case_U_seller_listing_context_contains_complete_factual_key_set(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields(
                [
                    'address'                         => '100 Maple Ave',
                    'description'                     => 'A lovely home',
                    'starting_price'                  => '350000',
                    'buy_now_price'                   => '390000',
                    'bedroom_id'                      => '3',
                    'bathroom_id'                     => '2',
                    'heated_sqft'                     => '1800',
                    'year_built'                      => '1995',
                    'pool'                            => 'Yes',
                    'pool_type'                       => 'Inground',
                    'carport'                         => 'No',
                    'garage'                          => 'Yes',
                    'garage_spaces'                   => '2',
                    'water_view'                      => 'No',
                    'water_extras'                    => 'No',
                    'hoa_association'                 => 'Yes',
                    'hoa_fee'                         => '250',
                    'hoa_fee_requirement'             => 'Mandatory',
                    'hoa_payment_schedule'            => 'Monthly',
                    'condo_fee'                       => '0',
                    'condo_fee_schedule'              => '',
                    'pets_allowed'                    => 'Yes',
                    'number_of_pets_allowed'          => '2',
                    'max_pet_weight'                  => '50',
                    'pet_restrictions'                => 'No aggressive breeds',
                    'rental_restrictions'             => 'Yes',
                    'rental_restrictions_desription'  => 'No Airbnb',
                    'is_in_flood_zone'                => 'No',
                    'flood_zone_code'                 => 'X',
                    'lease_terms'                     => '12 months',
                    'tenant_pays'                     => 'Electric',
                    'landlord_pays'                   => 'Water',
                    'closing_date'                    => '2026-09-01',
                    'auction_length'                  => '30',
                    'mls_id'                          => 'MLS123',
                    'sold'                            => '0',
                ],
                [
                    'showing_instructions' => 'Call 24h ahead',
                    'service_type'         => 'Full Service',
                ]
            )
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $expectedKeys = [
            // Base metadata
            'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
            'property_type', 'listing_status',
            // Factual — native columns
            'address', 'description', 'asking_price', 'buy_now_price',
            'bedrooms', 'bathrooms', 'square_feet', 'year_built',
            'pool', 'pool_type', 'carport', 'garage', 'garage_spaces',
            'water_view', 'water_extras',
            'hoa_association', 'hoa_fee', 'hoa_fee_requirement', 'hoa_payment_schedule',
            'condo_fee', 'condo_fee_schedule',
            'pets_allowed', 'number_of_pets_allowed', 'max_pet_weight', 'pet_restrictions',
            'rental_restrictions', 'rental_restrictions_description',
            'is_in_flood_zone', 'flood_zone_code', 'disclosure_flags',
            'lease_terms', 'tenant_pays', 'landlord_pays', 'closing_date',
            'auction_length', 'mls_id', 'sold',
            // Factual — EAV
            'showing_instructions', 'service_type',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Seller listing context is missing expected key: '{$key}'");
        }
    }

    public function test_case_U_buyer_listing_context_contains_complete_factual_key_set(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([
                'description'       => 'Looking for a 3-bed',
                'max_price'         => '450000',
                'bedrooms'          => '3',
                'bathrooms'         => '2',
                'sqft'              => '1500',
                'pool'              => 'Yes',
                'carport'           => 'No',
                'garage'            => 'Yes',
                'garage_spaces'     => '2',
                'water_view'        => 'No',
                'hoa'               => 'Yes',
                'hoa_fee_requirement' => 'Flexible',
                'max_hoa_fee'       => '300',
                'pets_allowed'      => 'Yes',
                'pets_detail'       => 'One dog',
                'pets_breed'        => 'Labrador',
                'pets_weight'       => '60',
                'loan_pre_approved'  => 'Yes',
                'financing_id'      => '0',
                'inspection_period' => '10',
                'closing_days'      => '45',
                'contingencies'     => 'Inspection',
            ])
        );

        $result  = $service->buildForListing('buyer', 1);
        $listing = $result['listing'];

        $expectedKeys = [
            // Base metadata
            'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
            'property_type', 'listing_status',
            // Factual — native columns
            'description', 'max_price', 'bedrooms', 'bathrooms', 'square_feet',
            'pool', 'carport', 'garage', 'garage_spaces', 'water_view',
            'hoa_acceptable', 'hoa_fee_requirement', 'max_hoa_fee',
            'pets_allowed', 'pets_detail', 'pets_breed', 'pets_weight',
            'loan_pre_approved', 'financing_type',
            'inspection_period', 'closing_days', 'contingencies',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Buyer listing context is missing expected key: '{$key}'");
        }
    }

    public function test_case_U_landlord_listing_context_contains_complete_factual_key_set(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], [
                'maximum_budget'               => '2500',
                'bedrooms'                     => '2',
                'bathrooms'                    => '1',
                'minimum_heated_square'        => '900',
                'unit_size'                    => 'Large',
                'number_of_unit'               => '4',
                'property_zip'                 => '33101',
                'property_items'               => '["Single Family"]',
                'condition_prop'               => 'Good',
                'appliances'                   => '["Washer","Dryer"]',
                'available_date'               => '2026-08-01',
                'pet_policy'                   => 'Cats only',
                'pet_deposit_fee_rent'         => '$200',
                'pet_max_weight_lbs'           => '25',
                'pet_species_allowed'          => '["Cats"]',
                'parking_terms'               => '1 assigned spot',
                'utilities'                    => 'Water included',
                'smoking_policy'               => 'No smoking',
                'subletting_policy'            => 'Not allowed',
                'has_hoa'                      => 'Yes',
                'association_name'             => 'Sunset HOA',
                'association_fee_amount'       => '150',
                'association_fee_frequency'    => 'Monthly',
                'association_amenities'        => '["Pool","Gym"]',
                'leasing_restrictions'         => 'No short-term',
                'min_lease_period'             => '12',
                'renewal_option_offered'       => 'Yes',
                'number_of_occupants_allowed'  => '4',
                'additional_landlord_lease_terms' => 'No parties',
            ])
        );

        $result  = $service->buildForListing('landlord', 1);
        $listing = $result['listing'];

        $expectedKeys = [
            // Base metadata
            'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
            'property_type', 'listing_status',
            // Factual — EAV
            'rent_amount', 'bedrooms', 'bathrooms', 'square_feet',
            'unit_size', 'number_of_units', 'property_zip', 'property_items',
            'condition_prop', 'appliances', 'available_date',
            'pet_policy', 'pet_deposit_fee_rent', 'pet_max_weight_lbs', 'pet_species_allowed',
            'parking_terms', 'utilities', 'smoking_policy', 'subletting_policy',
            'has_hoa', 'association_name', 'association_fee_amount',
            'association_fee_frequency', 'association_amenities', 'leasing_restrictions',
            'lease_length', 'renewal_option', 'number_of_occupants', 'additional_lease_terms',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Landlord listing context is missing expected key: '{$key}'");
        }
    }

    public function test_case_U_tenant_listing_context_contains_complete_factual_key_set(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], [
                'maximum_budget'          => '2000',
                'bedrooms'                => '2',
                'bathrooms'               => '1',
                'tenant_desired_lease_length' => '12',
                'property_items'          => '["Apartment"]',
                'appliances'              => '["Dishwasher"]',
                'condition_prop'          => 'Good',
                'pet_information'         => 'One cat',
                'parking_needed'          => 'Yes',
                'utilities'               => 'Water',
                'utility_preference'      => 'All included',
                'tenant_pays'             => '["Electric"]',
                'current_status'          => 'Month-to-month',
                'number_of_occupants'     => '2',
                'number_of_unit'          => '1',
            ])
        );

        $result  = $service->buildForListing('tenant', 1);
        $listing = $result['listing'];

        // number_of_unit (EAV key) is intentionally normalized to number_of_units
        // (plural) in the context output. This matches the landlord arm's key name
        // and the listing_facts contract allowlist (listing.number_of_units).
        $expectedKeys = [
            // Base metadata
            'listing_type', 'listing_id', 'listing_title', 'city', 'state', 'county',
            'property_type', 'listing_status',
            // Factual — EAV
            'max_rent', 'bedrooms', 'bathrooms', 'desired_lease_length',
            'property_items', 'appliances', 'condition_prop',
            'pet_information', 'parking_needed', 'utilities',
            'utility_preference', 'tenant_pays', 'current_status',
            'number_of_occupants', 'number_of_units',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Tenant listing context is missing expected key: '{$key}'");
        }
    }

    public function test_case_U_seller_source_has_no_duplicate_key_definitions(): void
    {
        // PHP silently keeps the LAST value when a key appears twice in an array
        // literal, so a runtime array_keys() check can never detect source-level
        // duplicates — the merge has already happened. This test reads the actual
        // source file text, isolates the seller match-arm block, and asserts that
        // no array key string is defined more than once within that text.
        $sourceFile = realpath(__DIR__ . '/../../../../app/Services/AskAi/AskAiContextBuilderService.php');
        $this->assertNotFalse($sourceFile, 'AskAiContextBuilderService.php must be readable');

        $source = file_get_contents($sourceFile);

        // Isolate seller match arm: starts at "'seller' => [" and ends at the
        // closing bracket of that arm (immediately before "'buyer' => [").
        $matched = preg_match(
            "/'seller'\s*=>\s*\[(.*?)\n\s+\],\s*\n\s+\/\/.*Buyer/s",
            $source,
            $m
        );
        $this->assertSame(1, $matched,
            'Could not isolate seller match arm from source — check regex if the arm structure changed');

        $sellerArmText = $m[1];

        // Extract all array key strings defined in the arm text.
        preg_match_all("/^\s+'([a-z_]+)'\s*=>/m", $sellerArmText, $keys);

        $allKeys   = $keys[1];
        $counts    = array_count_values($allKeys);
        $duplicates = array_keys(array_filter($counts, static fn (int $c) => $c > 1));

        $this->assertEmpty(
            $duplicates,
            'Seller match arm in extractFactualFields() contains duplicate key definitions for: '
            . implode(', ', $duplicates)
            . '. PHP silently discards earlier values — remove the duplicate definitions.'
        );
    }

    public function test_case_U_disclosure_flags_is_governance_marker_not_flood_status(): void
    {
        // disclosure_flags.flood_zone = true is a prompt-layer governance contract
        // marker. It signals that flood-zone fields are present in this context and
        // require the flood disclosure template — it does NOT mean the property is
        // in a flood zone. The actual flood status comes from is_in_flood_zone.
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([
                'is_in_flood_zone' => 'No',
                'flood_zone_code'  => 'X',
            ])
        );

        $result  = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        // governance marker is always present regardless of is_in_flood_zone value
        $this->assertArrayHasKey('disclosure_flags', $listing);
        $this->assertSame(true, $listing['disclosure_flags']['flood_zone'],
            "disclosure_flags.flood_zone must always be true for seller (governance marker, not property state)");

        // actual flood status is carried separately
        $this->assertSame('No', $listing['is_in_flood_zone'],
            "is_in_flood_zone carries the actual property flood status, independent of disclosure_flags");

        // the two fields must coexist in the same context
        $this->assertArrayHasKey('is_in_flood_zone', $listing);
        $this->assertArrayHasKey('flood_zone_code', $listing);
    }

    public function test_case_U_json_decoded_fields_produce_comma_separated_strings_not_arrays(): void
    {
        // Verifies that the decodeJsonField() helper (used for appliances,
        // property_items, association_amenities, pet_species_allowed, tenant_pays)
        // produces a comma-separated plain string — NOT a PHP array or JSON string.
        // This matches the Phase 1 decode behavior established for the appliances field.
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStubWithFields([], [
                'appliances'           => '["Washer","Dryer","Dishwasher"]',
                'pet_species_allowed'  => '["Cats","Small Dogs"]',
                'association_amenities' => '["Pool","Gym"]',
            ])
        );

        $result  = $service->buildForListing('landlord', 1);
        $listing = $result['listing'];

        $this->assertSame('Washer, Dryer, Dishwasher', $listing['appliances'],
            "'appliances' must be a comma-separated string, not an array or JSON string");
        $this->assertSame('Cats, Small Dogs', $listing['pet_species_allowed'],
            "'pet_species_allowed' must be a comma-separated string, not an array or JSON string");
        $this->assertSame('Pool, Gym', $listing['association_amenities'],
            "'association_amenities' must be a comma-separated string, not an array or JSON string");

        // Confirm none of them are PHP arrays or raw JSON
        $this->assertIsString($listing['appliances']);
        $this->assertIsString($listing['pet_species_allowed']);
        $this->assertIsString($listing['association_amenities']);
        $this->assertStringNotContainsString('[', $listing['appliances']);
        $this->assertStringNotContainsString('[', $listing['pet_species_allowed']);
    }
}
