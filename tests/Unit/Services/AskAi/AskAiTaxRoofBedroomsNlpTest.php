<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiTaxRoofBedroomsNlpTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no HTTP calls.
 *
 * Regression coverage for three separate fix streams:
 *
 *   TAX  — annual_property_taxes surfaced through all pipeline layers:
 *     Tax-A. Tax phrase variants all classify as listing_facts.
 *     Tax-B. Context builder PHP code includes annual_property_taxes for seller + landlord (code grep).
 *     Tax-C. AskAiResponseContractService listing_facts allowed_context contains listing.annual_property_taxes.
 *     Tax-D. AskAiFieldQuestionRegistryService::listingFieldRegistry() contains the listing.annual_property_taxes entry.
 *
 *   ROOF — typed-variant phrases route correctly through classifier and FAQ_KEY_KEYWORD_MAP:
 *     Roof-A. Newly-added typed roof phrase variants classify as listing_facts (classifier).
 *     Roof-B. Typed variants are present in FAQ_KEY_KEYWORD_MAP in the runner service (code grep).
 *
 *   BEDROOMS — pipeline carries bedrooms to the prompt:
 *     Bed-A. "How many bedrooms" and related phrases classify as listing_facts.
 *
 *   SOURCE_ATTRIBUTION — JS-visible API response carries structured object, not flat array:
 *     Src-A. Prompt builder builds source_attribution as { sources, required_sources, versions } (code grep).
 *     Src-B. Prompt builder sources items contain key+label fields (code grep).
 *     Src-C. No view blade renders source_attribution via Array.isArray(data.source_attribution) (JS code grep).
 */
class AskAiTaxRoofBedroomsNlpTest extends TestCase
{
    private function makeClassifier(): AskAiQuestionClassifierService
    {
        return new AskAiQuestionClassifierService();
    }

    private function classifierFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiQuestionClassifierService.php';
    }

    private function contextBuilderFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
    }

    private function runnerFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiRunnerV2Service.php';
    }

    private function promptBuilderFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiPromptBuilderService.php';
    }

    private function sellerViewFilePath(): string
    {
        return dirname(__DIR__, 4) . '/resources/views/offer-listing/seller/view.blade.php';
    }

    private function buyerViewFilePath(): string
    {
        return dirname(__DIR__, 4) . '/resources/views/offer-listing/buyer/view.blade.php';
    }

    private function landlordViewFilePath(): string
    {
        return dirname(__DIR__, 4) . '/resources/views/offer-listing/landlord/view.blade.php';
    }

    private function tenantViewFilePath(): string
    {
        return dirname(__DIR__, 4) . '/resources/views/offer-listing/tenant/view.blade.php';
    }

    private function fileContents(string $path): string
    {
        $this->assertFileExists($path, "Expected file not found: {$path}");
        return file_get_contents($path);
    }

    // =========================================================================
    // Case Tax-A — tax phrase variants → listing_facts
    // =========================================================================

    /**
     * @dataProvider taxPhrasesProvider
     */
    public function test_case_TaxA_tax_phrases_classify_as_listing_facts(string $phrase): void
    {
        $result = $this->makeClassifier()->classify($phrase);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Tax phrase \"{$phrase}\" should classify as listing_facts."
        );
    }

    public static function taxPhrasesProvider(): array
    {
        return [
            'property tax'                        => ['property tax'],
            'property taxes'                      => ['property taxes'],
            'annual taxes'                        => ['annual taxes'],
            'annual tax'                          => ['annual tax'],
            'tax amount'                          => ['tax amount'],
            'annual property tax'                 => ['annual property tax'],
            'real estate tax'                     => ['real estate tax'],
            'real estate taxes'                   => ['real estate taxes'],
            'what are the taxes — phrase'         => ['What are the taxes on this property?'],
            'how much are the taxes — phrase'     => ['How much are the taxes?'],
            'how much are property taxes — phrase'=> ['How much are property taxes for this home?'],
            'how much are the property taxes'     => ['How much are the property taxes per year?'],
        ];
    }

    // =========================================================================
    // Case Tax-B — context builder code contains annual_property_taxes for
    //              both seller and landlord extraction blocks
    // =========================================================================

    public function test_case_TaxB_context_builder_includes_annual_property_taxes_for_seller_and_landlord(): void
    {
        $content = $this->fileContents($this->contextBuilderFilePath());

        $occurrences = substr_count($content, "'annual_property_taxes'");
        $this->assertGreaterThanOrEqual(
            2,
            $occurrences,
            "AskAiContextBuilderService must extract 'annual_property_taxes' in both the seller and landlord sections (expected >= 2 occurrences, found {$occurrences})."
        );
    }

    // =========================================================================
    // Case Tax-C — listing_facts contract allowed_context contains
    //              'listing.annual_property_taxes'
    // =========================================================================

    public function test_case_TaxC_contract_allowed_context_includes_annual_property_taxes(): void
    {
        $service = new AskAiResponseContractService();
        $context = [
            'listing' => [
                'listing_id'    => 1,
                'listing_type'  => 'seller',
                'property_type' => 'Single Family',
                'bedrooms'      => '3',
            ],
            'faq_answers' => [],
        ];

        $contract = $service->buildContract('listing_facts', $context);

        $this->assertSame(
            'contract_ready',
            $contract['status'],
            'listing_facts contract must be contract_ready when listing context is present.'
        );
        $this->assertContains(
            'listing.annual_property_taxes',
            $contract['allowed_context'],
            'listing.annual_property_taxes must appear in listing_facts allowed_context.'
        );
    }

    // =========================================================================
    // Case Tax-D — field registry contains listing.annual_property_taxes entry
    // =========================================================================

    public function test_case_TaxD_field_registry_contains_annual_property_taxes_entry(): void
    {
        $registry = AskAiFieldQuestionRegistryService::listingFieldRegistry();

        $this->assertArrayHasKey(
            'listing.annual_property_taxes',
            $registry,
            'listingFieldRegistry() must contain a listing.annual_property_taxes entry.'
        );

        $entry = $registry['listing.annual_property_taxes'];
        $this->assertContains('seller', $entry['roles'], 'annual_property_taxes must apply to seller role.');
        $this->assertContains('landlord', $entry['roles'], 'annual_property_taxes must apply to landlord role.');
        $this->assertNotEmpty($entry['sample_question'], 'annual_property_taxes must have a sample_question.');
    }

    // =========================================================================
    // Case Roof-A — newly-added typed roof phrase variants → listing_facts
    // =========================================================================

    /**
     * @dataProvider typedRoofPhrasesProvider
     */
    public function test_case_RoofA_typed_roof_variants_classify_as_listing_facts(string $phrase): void
    {
        $result = $this->makeClassifier()->classify($phrase);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Typed roof phrase \"{$phrase}\" should classify as listing_facts (new variant)."
        );
    }

    public static function typedRoofPhrasesProvider(): array
    {
        return [
            'age of the roof'              => ['What is the age of the roof?'],
            'condition of the roof'        => ['What is the condition of the roof?'],
            'condition is the roof — phrasing' => ['What condition is the roof in?'],
            'what is the age of the roof'  => ['What is the age of the roof?'],
        ];
    }

    // =========================================================================
    // Case Roof-B — FAQ_KEY_KEYWORD_MAP in runner service contains typed variants
    // =========================================================================

    /**
     * @dataProvider typedRoofFaqKeywordsProvider
     */
    public function test_case_RoofB_runner_faq_keyword_map_contains_typed_roof_variant(string $keyword): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            "'{$keyword}'",
            $content,
            "FAQ_KEY_KEYWORD_MAP in AskAiRunnerV2Service must include '{$keyword}' as a typed roof variant."
        );
    }

    public static function typedRoofFaqKeywordsProvider(): array
    {
        return [
            'age of the roof'         => ['age of the roof'],
            'condition of the roof'   => ['condition of the roof'],
            'condition is the roof'   => ['condition is the roof'],
            'what condition is the roof' => ['what condition is the roof'],
            'what is the age of the roof' => ['what is the age of the roof'],
        ];
    }

    // =========================================================================
    // Case Bed-A — bedrooms phrases → listing_facts
    // =========================================================================

    /**
     * @dataProvider bedroomPhrasesProvider
     */
    public function test_case_BedA_bedroom_phrases_classify_as_listing_facts(string $phrase): void
    {
        $result = $this->makeClassifier()->classify($phrase);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Bedroom phrase \"{$phrase}\" should classify as listing_facts."
        );
    }

    public static function bedroomPhrasesProvider(): array
    {
        return [
            'how many bedrooms'          => ['How many bedrooms does this property have?'],
            'number of bedrooms'         => ['What is the number of bedrooms?'],
            'bedrooms in this home'      => ['How many bedrooms are in this home?'],
        ];
    }

    // =========================================================================
    // Case Bed-B — listing_facts contract allows listing.bedrooms
    // =========================================================================

    public function test_case_BedB_contract_allowed_context_includes_bedrooms(): void
    {
        $service = new AskAiResponseContractService();
        $context = [
            'listing' => ['listing_id' => 1, 'listing_type' => 'seller', 'property_type' => 'Single Family'],
            'faq_answers' => [],
        ];
        $contract = $service->buildContract('listing_facts', $context);

        $this->assertContains(
            'listing.bedrooms',
            $contract['allowed_context'],
            'listing.bedrooms must appear in listing_facts allowed_context so bedroom questions reach the prompt.'
        );
    }

    // =========================================================================
    // Case Src-A — prompt builder returns structured source_attribution object
    // =========================================================================

    public function test_case_SrcA_prompt_builder_builds_source_attribution_as_structured_object(): void
    {
        $content = $this->fileContents($this->promptBuilderFilePath());

        $this->assertStringContainsString(
            "'sources'",
            $content,
            "AskAiPromptBuilderService must build a source_attribution array with a 'sources' key."
        );
        $this->assertStringContainsString(
            "'required_sources'",
            $content,
            "AskAiPromptBuilderService must build a source_attribution array with a 'required_sources' key."
        );
        $this->assertStringContainsString(
            "'versions'",
            $content,
            "AskAiPromptBuilderService must build a source_attribution array with a 'versions' key."
        );
    }

    // =========================================================================
    // Case Src-B — prompt builder sources items have key and label fields
    // =========================================================================

    public function test_case_SrcB_prompt_builder_sources_items_contain_key_and_label(): void
    {
        $content = $this->fileContents($this->promptBuilderFilePath());

        $this->assertStringContainsString(
            "'key'",
            $content,
            "Prompt builder sources items must include a 'key' field."
        );
        $this->assertStringContainsString(
            "'label'",
            $content,
            "Prompt builder sources items must include a 'label' field."
        );
    }

    // =========================================================================
    // Case Src-C — no view blade renders source_attribution via the broken
    //              Array.isArray(data.source_attribution) flat-array check
    // =========================================================================

    /**
     * @dataProvider viewFilePathsProvider
     */
    public function test_case_SrcC_blade_view_does_not_use_broken_source_attribution_array_check(string $role, string $filePath): void
    {
        $content = $this->fileContents($filePath);

        $this->assertStringNotContainsString(
            'Array.isArray(data.source_attribution)',
            $content,
            "The {$role} view blade still uses the broken Array.isArray(data.source_attribution) flat-array check. "
            . "It must instead read data.source_attribution.sources (the structured object)."
        );
    }

    /**
     * @dataProvider viewFilePathsProvider
     */
    public function test_case_SrcC_blade_view_reads_source_attribution_sources_array(string $role, string $filePath): void
    {
        $content = $this->fileContents($filePath);

        $this->assertStringContainsString(
            'data.source_attribution.sources',
            $content,
            "The {$role} view blade must read data.source_attribution.sources (structured object) to display source labels."
        );
    }

    public static function viewFilePathsProvider(): array
    {
        $base = dirname(__DIR__, 4) . '/resources/views/offer-listing';
        return [
            'seller'   => ['seller',   "{$base}/seller/view.blade.php"],
            'buyer'    => ['buyer',    "{$base}/buyer/view.blade.php"],
            'landlord' => ['landlord', "{$base}/landlord/view.blade.php"],
            'tenant'   => ['tenant',   "{$base}/tenant/view.blade.php"],
        ];
    }

    // =========================================================================
    // Case Sys-A — system instructions contain "json" for OpenAI json_object mode
    //
    // OpenAI requires the literal word "json" in at least one message when
    // response_format: json_object is set. This test guards against regression.
    // =========================================================================

    public function test_case_SysA_system_instructions_contain_word_json(): void
    {
        $content = $this->fileContents($this->promptBuilderFilePath());

        $this->assertMatchesRegularExpression(
            '/SYSTEM_INSTRUCTIONS.*?json/si',
            $content,
            'SYSTEM_INSTRUCTIONS in AskAiPromptBuilderService must contain the word "json" '
            . 'so OpenAI response_format:json_object requests are accepted.'
        );
    }

    // =========================================================================
    // Case List-A — LISTING_KEY_KEYWORD_MAP constant exists in the runner
    // =========================================================================

    public function test_case_ListA_runner_contains_listing_key_keyword_map_constant(): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            'LISTING_KEY_KEYWORD_MAP',
            $content,
            'AskAiRunnerV2Service must declare a LISTING_KEY_KEYWORD_MAP constant for listing.* field detection.'
        );
    }

    // =========================================================================
    // Case List-B — LISTING_KEY_KEYWORD_MAP contains tax keywords
    // =========================================================================

    /**
     * @dataProvider listingTaxKeywordsProvider
     */
    public function test_case_ListB_listing_key_keyword_map_contains_tax_keyword(string $keyword): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            "'{$keyword}'",
            $content,
            "LISTING_KEY_KEYWORD_MAP must include '{$keyword}' so tax questions trigger the listing null-field guard."
        );
    }

    public static function listingTaxKeywordsProvider(): array
    {
        return [
            'property tax'         => ['property tax'],
            'property taxes'       => ['property taxes'],
            'annual taxes'         => ['annual taxes'],
            'what are the taxes'   => ['what are the taxes'],
            'real estate taxes'    => ['real estate taxes'],
        ];
    }

    // =========================================================================
    // Case List-C — LISTING_KEY_KEYWORD_MAP contains bedroom keywords
    // =========================================================================

    /**
     * @dataProvider listingBedroomKeywordsProvider
     */
    public function test_case_ListC_listing_key_keyword_map_contains_bedroom_keyword(string $keyword): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            "'{$keyword}'",
            $content,
            "LISTING_KEY_KEYWORD_MAP must include '{$keyword}' so bedroom questions trigger the listing null-field guard."
        );
    }

    public static function listingBedroomKeywordsProvider(): array
    {
        return [
            'how many bedrooms' => ['how many bedrooms'],
            'number of bedrooms' => ['number of bedrooms'],
            'bedroom count'     => ['bedroom count'],
        ];
    }

    // =========================================================================
    // Case List-D — runner contains listing.* null-field guard condition
    // =========================================================================

    public function test_case_ListD_runner_contains_listing_null_field_guard(): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            "str_starts_with(\$normalizedFieldKey, 'listing.')",
            $content,
            'AskAiRunnerV2Service must contain a listing.* null-field guard so questions about '
            . 'unset native/EAV fields return a grounded insufficient_context message, not a generic failed.'
        );
    }

    // =========================================================================
    // Case List-E — deriveFieldLabel maps listing.bedrooms to a human label
    // =========================================================================

    public function test_case_ListE_derive_field_label_maps_listing_bedrooms(): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            "'listing.bedrooms'",
            $content,
            "deriveFieldLabel in AskAiRunnerV2Service must include a 'listing.bedrooms' entry "
            . 'so the missing-data message says "Bedroom information has not been provided..." '
            . 'rather than the generic fallback.'
        );
    }

    // =========================================================================
    // Case List-F — detectListingFieldKey method exists in the runner
    // =========================================================================

    public function test_case_ListF_runner_contains_detect_listing_field_key_method(): void
    {
        $content = $this->fileContents($this->runnerFilePath());

        $this->assertStringContainsString(
            'detectListingFieldKey',
            $content,
            'AskAiRunnerV2Service must declare a detectListingFieldKey() method for listing.* field detection.'
        );
    }
}
