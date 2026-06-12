<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AskAiIncomeKeywordRoutingTest
 *
 * Verifies that income / multifamily keyword routing in AskAiRunnerV2Service
 * is correct both structurally (map presence) and behaviourally (the private
 * detector methods return the expected keys for real acceptance questions).
 *
 * Cases A–D are structural map checks (no I/O).
 * Case E is behavioural: calls detectFaqFieldKey() and detectListingFieldKey()
 * via reflection with the 10 canonical income acceptance questions and asserts
 * the correct canonical path is resolved.
 * Cases F & G are collision-regression checks that previously caused the wrong
 * key to win.
 */
class AskAiIncomeKeywordRoutingTest extends TestCase
{
    /** Income listing.* keys added for income/multifamily support. */
    private const INCOME_LISTING_KEYS = [
        'listing.gross_annual_income',
        'listing.annual_net_income',
        'listing.cap_rate',
        'listing.annual_operating_expenses',
        'listing.total_units',
        'listing.unit_mix_summary',
        'listing.rent_roll_available',
        'listing.operating_statement_available',
        'listing.property_items',
        'listing.occupancy_requirement',
    ];

    /**
     * The 10 canonical income acceptance questions and the listing.* key each
     * must resolve to via detectListingFieldKey().
     *
     * These must NOT match any FAQ entry first (detectFaqFieldKey must return
     * null for each of them).
     */
    private const ACCEPTANCE_ROUTING = [
        'What is the gross annual income?'                          => 'listing.gross_annual_income',
        'What are the annual operating expenses?'                   => 'listing.annual_operating_expenses',
        'What is the cap rate?'                                     => 'listing.cap_rate',
        'What is the annual net income?'                            => 'listing.annual_net_income',
        'How many units does this property have?'                   => 'listing.total_units',
        'What is the unit mix?'                                     => 'listing.unit_mix_summary',
        'Is a rent roll available?'                                 => 'listing.rent_roll_available',
        'Is an operating statement available?'                      => 'listing.operating_statement_available',
        'What property type mix exists (duplex/triplex/etc.)?'      => 'listing.property_items',
        'What occupancy requirements exist?'                        => 'listing.occupancy_requirement',
    ];

    /**
     * Expected phrase → listing.* key for structural map checks (Case B).
     * Every phrase here must be present verbatim (case-insensitive) in the
     * named key's phrase list.
     */
    private const PHRASE_TO_KEY = [
        'gross annual income'                   => 'listing.gross_annual_income',
        'total annual rent collected'           => 'listing.gross_annual_income',
        'annual net income'                     => 'listing.annual_net_income',
        'net operating income amount'           => 'listing.annual_net_income',
        'cap rate'                              => 'listing.cap_rate',
        'capitalization rate for this property' => 'listing.cap_rate',
        'annual operating expenses'             => 'listing.annual_operating_expenses',
        'total annual expenses'                 => 'listing.annual_operating_expenses',
        'how many units'                        => 'listing.total_units',
        'unit count'                            => 'listing.total_units',
        'unit mix'                              => 'listing.unit_mix_summary',
        'bedroom mix'                           => 'listing.unit_mix_summary',
        'rent roll available'                   => 'listing.rent_roll_available',
        'is a rent roll available'              => 'listing.rent_roll_available',
        'operating statement available'         => 'listing.operating_statement_available',
        'income statement available'            => 'listing.operating_statement_available',
        'duplex'                                => 'listing.property_items',
        'triplex'                               => 'listing.property_items',
        'property type mix'                     => 'listing.property_items',
        'occupancy requirement'                 => 'listing.occupancy_requirement',
        'what occupancy is required'            => 'listing.occupancy_requirement',
        'minimum occupancy'                     => 'listing.occupancy_requirement',
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getListingKeyKeywordMap(): array
    {
        $rc  = new ReflectionClass(AskAiRunnerV2Service::class);
        $map = $rc->getConstant('LISTING_KEY_KEYWORD_MAP');
        $this->assertIsArray($map, 'LISTING_KEY_KEYWORD_MAP constant must be an array');
        return $map;
    }

    private function getFaqKeyKeywordMap(): array
    {
        $rc  = new ReflectionClass(AskAiRunnerV2Service::class);
        $map = $rc->getConstant('FAQ_KEY_KEYWORD_MAP');
        if ($map === false) {
            $this->fail('FAQ_KEY_KEYWORD_MAP constant not found on AskAiRunnerV2Service');
        }
        $this->assertIsArray($map);
        return $map;
    }

    /**
     * Call the private detectFaqFieldKey() method via reflection.
     */
    private function callDetectFaqFieldKey(string $question): ?string
    {
        $rc     = new ReflectionClass(AskAiRunnerV2Service::class);
        $method = $rc->getMethod('detectFaqFieldKey');
        $method->setAccessible(true);

        // Build a minimal stub instance without constructor dependencies.
        $instance = $rc->newInstanceWithoutConstructor();
        return $method->invoke($instance, $question);
    }

    /**
     * Call the private detectListingFieldKey() method via reflection.
     */
    private function callDetectListingFieldKey(string $question): ?string
    {
        $rc     = new ReflectionClass(AskAiRunnerV2Service::class);
        $method = $rc->getMethod('detectListingFieldKey');
        $method->setAccessible(true);

        $instance = $rc->newInstanceWithoutConstructor();
        return $method->invoke($instance, $question);
    }

    // =========================================================================
    // Case A — Every income listing.* key exists in LISTING_KEY_KEYWORD_MAP
    // =========================================================================

    public function test_case_A_every_income_key_is_registered_in_listing_key_keyword_map(): void
    {
        $map = $this->getListingKeyKeywordMap();

        foreach (self::INCOME_LISTING_KEYS as $key) {
            $this->assertArrayHasKey($key, $map,
                "Income key '{$key}' is missing from LISTING_KEY_KEYWORD_MAP");
        }
    }

    public function test_case_A_each_income_key_has_at_least_one_phrase(): void
    {
        $map = $this->getListingKeyKeywordMap();

        foreach (self::INCOME_LISTING_KEYS as $key) {
            if (!isset($map[$key])) {
                continue;
            }
            $this->assertNotEmpty($map[$key],
                "Income key '{$key}' must have at least one keyword phrase in LISTING_KEY_KEYWORD_MAP");
        }
    }

    // =========================================================================
    // Case B — Specific phrase → key mappings are correct
    // =========================================================================

    /**
     * @dataProvider phraseToKeyProvider
     */
    public function test_case_B_phrase_maps_to_expected_key(string $phrase, string $expectedKey): void
    {
        $map = $this->getListingKeyKeywordMap();

        $this->assertArrayHasKey($expectedKey, $map,
            "Expected income key '{$expectedKey}' is not in LISTING_KEY_KEYWORD_MAP");

        $phrases = array_map('strtolower', $map[$expectedKey]);

        $this->assertContains(
            strtolower($phrase),
            $phrases,
            "Phrase '{$phrase}' is not in the keyword list for key '{$expectedKey}'"
        );
    }

    public static function phraseToKeyProvider(): array
    {
        return array_map(
            static fn(string $phrase, string $key) => [$phrase, $key],
            array_keys(self::PHRASE_TO_KEY),
            array_values(self::PHRASE_TO_KEY)
        );
    }

    // =========================================================================
    // Case C — Income phrases do not duplicate phrases from non-income entries
    // =========================================================================

    public function test_case_C_income_phrases_are_not_duplicated_in_non_income_entries(): void
    {
        $map      = $this->getListingKeyKeywordMap();
        $incomeSet = array_flip(self::INCOME_LISTING_KEYS);

        foreach (self::PHRASE_TO_KEY as $phrase => $expectedKey) {
            foreach ($map as $key => $phrases) {
                if (isset($incomeSet[$key])) {
                    continue;
                }
                $this->assertNotContains(
                    strtolower($phrase),
                    array_map('strtolower', $phrases),
                    "Income phrase '{$phrase}' (for '{$expectedKey}') is duplicated in non-income key '{$key}'"
                );
            }
        }
    }

    // =========================================================================
    // Case D — All income keys carry the "listing." prefix (not "faq_answers.")
    // =========================================================================

    public function test_case_D_all_income_keys_have_listing_prefix(): void
    {
        foreach (self::INCOME_LISTING_KEYS as $key) {
            $this->assertStringStartsWith('listing.', $key,
                "Income key '{$key}' must use the 'listing.' prefix");
            $this->assertStringNotContainsString('faq_answers.', $key,
                "Income key '{$key}' must not use the 'faq_answers.' prefix");
        }
    }

    // =========================================================================
    // Case E — Behavioural: acceptance questions route to the correct
    //           listing.* key and do NOT match any FAQ entry first.
    //
    // The production routing pipeline calls detectFaqFieldKey() first; if it
    // returns a result the listing detector is never reached.  These tests
    // verify the full two-step resolution by calling both private methods in
    // order, mirroring the real pipeline.
    // =========================================================================

    /**
     * @dataProvider acceptanceRoutingProvider
     */
    public function test_case_E_acceptance_question_routes_to_correct_listing_key(
        string $question,
        string $expectedListingKey
    ): void {
        // Step 1: FAQ detector must return null (no FAQ collision).
        $faqKey = $this->callDetectFaqFieldKey($question);
        $this->assertNull(
            $faqKey,
            "FAQ detector fired first for question '{$question}' → '{$faqKey}'; " .
            "this would prevent listing key '{$expectedListingKey}' from being reached."
        );

        // Step 2: Listing detector must return the expected key.
        $listingKey = $this->callDetectListingFieldKey($question);
        $this->assertSame(
            $expectedListingKey,
            $listingKey,
            "Question '{$question}' expected to route to '{$expectedListingKey}' " .
            "but detectListingFieldKey() returned " . var_export($listingKey, true)
        );
    }

    public static function acceptanceRoutingProvider(): array
    {
        return array_map(
            static fn(string $q, string $k) => [$q, $k],
            array_keys(self::ACCEPTANCE_ROUTING),
            array_values(self::ACCEPTANCE_ROUTING)
        );
    }

    // =========================================================================
    // Case F — Collision regression: "annual operating expenses" must NOT fire
    //           the FAQ detector (faq_answers.annual_operating_expenses_detail).
    //           Prior to the fix this phrase was in both maps and FAQ won first.
    // =========================================================================

    public function test_case_F_annual_operating_expenses_does_not_fire_faq_detector(): void
    {
        $phrases = [
            'What are the annual operating expenses?',
            'annual operating expenses',
            'total annual expenses',
        ];

        foreach ($phrases as $phrase) {
            $faqKey = $this->callDetectFaqFieldKey($phrase);
            $this->assertNull(
                $faqKey,
                "FAQ detector must not match '{$phrase}' (collision with listing.annual_operating_expenses). " .
                "Got: " . var_export($faqKey, true)
            );
        }
    }

    // =========================================================================
    // Case G — Collision regression: duplex/triplex and "property type mix"
    //           questions must route to listing.property_items, not the more
    //           generic listing.property_type which would have matched first if
    //           property_items were not ordered before property_type in the map.
    // =========================================================================

    public function test_case_G_duplex_triplex_routes_to_property_items_not_property_type(): void
    {
        $questions = [
            'Is this a duplex or triplex?'                          => 'listing.property_items',
            'What property type mix exists (duplex/triplex/etc.)?'  => 'listing.property_items',
            'Is this building a quadplex?'                          => 'listing.property_items',
            'What multifamily type is this?'                        => 'listing.property_items',
        ];

        foreach ($questions as $question => $expectedKey) {
            $listingKey = $this->callDetectListingFieldKey($question);
            $this->assertSame(
                $expectedKey,
                $listingKey,
                "Question '{$question}' must route to '{$expectedKey}', " .
                "got " . var_export($listingKey, true)
            );
        }
    }

    // =========================================================================
    // Regression — income keys must not appear in FAQ_KEY_KEYWORD_MAP
    // =========================================================================

    public function test_income_keys_are_absent_from_faq_key_keyword_map(): void
    {
        $faqMap = $this->getFaqKeyKeywordMap();

        foreach (self::INCOME_LISTING_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $faqMap,
                "Income key '{$key}' must not appear in FAQ_KEY_KEYWORD_MAP (it belongs in LISTING_KEY_KEYWORD_MAP)");
        }
    }
}
