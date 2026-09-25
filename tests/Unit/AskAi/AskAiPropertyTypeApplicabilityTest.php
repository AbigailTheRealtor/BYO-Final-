<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Support\AskAi\AskAiPropertyTypeResolver as PT;
use PHPUnit\Framework\TestCase;

/**
 * Property-type applicability for the public Ask AI question catalog.
 *
 * The catalog was role-scoped only, so "How many bedrooms are there?" was offered about a
 * Vacant Land listing whenever a stray `bedrooms` meta row existed. These tests pin the
 * rule that closed it, in both directions: a question must reach the property types it
 * describes, and must not reach the ones it does not.
 *
 * Extends PHPUnit's TestCase directly — the resolver and the catalog are pure, and the
 * service's `forListing()` needs no container for field-sourced questions. A test that
 * booted an application would prove less, not more: it could not tell a genuine rule from
 * a container binding that happened to be in place.
 */
class AskAiPropertyTypeApplicabilityTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    /** @return array<string,string> question id => answer */
    private function ask(string $role, string $propertyType, array $listing, array $meta = []): array
    {
        $listing['property_type'] = $propertyType;

        $out = [];
        foreach ($this->service->forListing($role, ['listing' => $listing, 'faq_answers' => []], $meta) as $q) {
            $out[$q['id']] = $q['answer'];
        }

        return $out;
    }

    // ── the resolver ────────────────────────────────────────────────────────

    public function test_the_resolver_is_exact_and_never_substring_matches(): void
    {
        // The defect this whole class exists to prevent. PropertyTypeVocabulary::forRole()
        // carries a substring fallback that tests `residential` before `income`, so both of
        // these collapse to Residential there. Here they must not.
        $this->assertSame([PT::INCOME], PT::tokensFor('seller', 'Residential Income'));
        $this->assertSame([PT::INCOME], PT::tokensFor('seller', 'ResidentialIncome'));

        // A lease record is still a residential BUILDING — the vocabulary says so — but it
        // is resolved by the exact table, not by a prefix match on "Residential".
        $this->assertSame([PT::RESIDENTIAL], PT::tokensFor('seller', 'Residential Lease'));
    }

    public function test_unknown_and_absent_property_types_resolve_to_nothing(): void
    {
        foreach ([null, '', '   ', 'Timeshare', 'Houseboat', 'residential-ish', 42, [], true] as $value) {
            $this->assertSame([], PT::tokensFor('seller', $value), var_export($value, true));
        }
    }

    public function test_a_role_without_a_category_for_a_type_resolves_to_nothing(): void
    {
        // A Business Opportunity is perfectly well understood; the Landlord form has nowhere
        // to put one. That is a withhold, not a guess at the nearest landlord category.
        $this->assertSame([PT::BUSINESS], PT::tokensFor('seller', 'Business Opportunity'));
        $this->assertSame([], PT::tokensFor('landlord', 'Business Opportunity'));
        $this->assertSame([], PT::tokensFor('landlord', 'Vacant Land'));
    }

    public function test_a_criteria_listing_may_name_several_types(): void
    {
        $this->assertSame([PT::RESIDENTIAL, PT::COMMERCIAL], PT::tokensFor('buyer', 'Residential, Commercial'));
        $this->assertSame([PT::RESIDENTIAL, PT::COMMERCIAL], PT::tokensFor('buyer', '["Residential","Commercial"]'));
        // An unnameable member is dropped; the nameable one survives.
        $this->assertSame([PT::RESIDENTIAL], PT::tokensFor('buyer', 'Residential, Timeshare'));
    }

    public function test_admits_is_universal_only_when_every_type_is_named(): void
    {
        $this->assertTrue(PT::admits(PT::ALL_TYPES, []));                       // universal ⊇ unknown
        $this->assertTrue(PT::admits(PT::ALL_TYPES, [PT::VACANT_LAND]));
        $this->assertFalse(PT::admits([PT::RESIDENTIAL], []));                  // narrower ∌ unknown
        $this->assertTrue(PT::admits([PT::RESIDENTIAL], [PT::RESIDENTIAL]));
        $this->assertFalse(PT::admits([PT::RESIDENTIAL], [PT::COMMERCIAL]));
        // Silence is refused, never treated as universal.
        $this->assertFalse(PT::admits(null, [PT::RESIDENTIAL]));
        $this->assertFalse(PT::admits([], [PT::RESIDENTIAL]));
        $this->assertFalse(PT::admits(['not_a_token'], [PT::RESIDENTIAL]));
    }

    // ── the leak tests ──────────────────────────────────────────────────────

    public function test_vacant_land_is_never_asked_about_bedrooms_or_bathrooms(): void
    {
        // Every residential value populated, on land. The values exist; the questions must not.
        $listing = ['bedrooms' => '3', 'bathrooms' => '2', 'square_feet' => '1800', 'pool' => 'Yes'];
        $meta    = ['bedrooms' => '3', 'bathrooms' => '2'];

        $land = $this->ask('seller', 'Vacant Land', $listing, $meta);

        foreach (['seller_bedrooms', 'seller_bathrooms', 'seller_heated_square_feet', 'seller_pool'] as $id) {
            $this->assertArrayNotHasKey($id, $land, "{$id} leaked onto a Vacant Land listing");
        }

        // Control: the identical data on a Residential listing DOES produce them, so the
        // suppression above is the property-type rule and not an unrelated guard.
        $residential = $this->ask('seller', 'Residential', $listing, $meta);
        $this->assertArrayHasKey('seller_bedrooms', $residential);
        $this->assertArrayHasKey('seller_bathrooms', $residential);
    }

    public function test_a_commercial_lease_is_never_asked_residential_only_questions(): void
    {
        $answers = $this->ask(
            'landlord',
            'Commercial Lease',
            ['bedrooms' => '2', 'bathrooms' => '1', 'association_fee_amount' => '300', 'appliances' => 'Range'],
            ['bedrooms' => '2', 'bathrooms' => '1', 'association_fee_frequency' => 'Monthly']
        );

        // What the Commercial form does NOT collect (LP property-preferences :388 bedrooms,
        // :1757 pets — both inside the Residential block) is never asked.
        foreach (['landlord_bedrooms', 'landlord_pets_allowed'] as $id) {
            $this->assertArrayNotHasKey($id, $answers, "{$id} leaked onto a Commercial Lease listing");
        }

        // What it DOES collect is answerable: bathrooms (:426, both types), appliances (:569,
        // no gate) and the HOA block (tax-legal-hoa-disclosures has no property-type gate).
        // These were withheld by a hand-written Residential-only declaration before
        // AskAiFieldApplicability tied the catalog to the form.
        foreach (['landlord_bathrooms', 'landlord_appliances'] as $id) {
            $this->assertArrayHasKey($id, $answers, "{$id} is collected by the Commercial form and must be answerable");
        }

        // The HOA fee is a child of has_hoa on every type; with the parent answered it is asked.
        $withHoa = $this->ask('landlord', 'Commercial Lease',
            ['has_hoa' => 'Yes', 'association_fee_amount' => '300'], ['association_fee_frequency' => 'Monthly']);
        $this->assertArrayHasKey('landlord_hoa_fee', $withHoa, 'The Commercial form collects the HOA fee.');
    }

    public function test_a_business_opportunity_is_never_asked_house_questions(): void
    {
        $answers = $this->ask(
            'seller',
            'Business Opportunity',
            ['bedrooms' => '4', 'bathrooms' => '3', 'pool' => 'Yes', 'garage' => 'Yes', 'hoa_fee' => '100'],
            ['bedrooms' => '4', 'bathrooms' => '3']
        );

        // Not collected for Business (SP property-preferences :808 bedrooms, :1274 pool, :1049
        // garage — Residential / Income blocks only): never asked.
        foreach (['seller_bedrooms', 'seller_pool', 'seller_garage'] as $id) {
            $this->assertArrayNotHasKey($id, $answers, "{$id} leaked onto a Business Opportunity listing");
        }

        // Collected for Business: bathrooms (:841 includes 'Business'); the HOA block has no
        // property-type gate. seller_hoa_fee itself yields to its coverage composite.
        $this->assertArrayHasKey('seller_bathrooms', $answers);

        $withHoa = $this->ask('seller', 'Business Opportunity',
            ['hoa_association' => 'Yes', 'hoa_fee' => '100', 'hoa_payment_schedule' => 'Monthly'], ['association_fee_frequency' => 'Monthly']);
        $this->assertArrayHasKey('seller_hoa_fee', $withHoa, 'The Business form collects the HOA fee.');
    }

    public function test_an_unknown_property_type_fails_closed_but_keeps_universal_questions(): void
    {
        $listing = ['asking_price' => '450000', 'bedrooms' => '3'];
        $meta    = ['bedrooms' => '3'];

        $answers = $this->ask('seller', 'Timeshare', $listing, $meta);

        // Type-specific: withheld, because an unresolved listing names no type.
        $this->assertArrayNotHasKey('seller_bedrooms', $answers);

        // Universal: still answered. Withholding the price of a listing whose type we cannot
        // name would break every listing the day a new feed spelling appeared.
        $this->assertArrayHasKey('seller_asking_price', $answers);
    }

    public function test_residential_income_reaches_its_own_questions_rather_than_being_withheld(): void
    {
        $answers = $this->ask(
            'seller',
            'Residential Income',
            ['bedrooms' => '8', 'year_built' => '1978', 'asking_price' => '1200000'],
            ['bedrooms' => '8']
        );

        // Income resolves to its OWN type rather than to "unknown": year_built is collected
        // only for typed listings (SP property-preferences :1920 inside the Residential/Income
        // block), so reaching it proves the type resolved.
        $this->assertArrayHasKey('seller_year_built', $answers);
        $this->assertArrayHasKey('seller_asking_price', $answers);

        // The Income form has no whole-property bedrooms input — it asks per unit
        // (unit_type_configurations, :1721) — so a stored bedrooms row is not asked about.
        $this->assertArrayNotHasKey('seller_bedrooms', $answers);
    }

    // ── registry integrity ──────────────────────────────────────────────────

    public function test_every_catalog_entry_declares_intentional_property_type_applicability(): void
    {
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $declared = $entry['property_types'] ?? null;

            if (!is_array($declared) || $declared === []) {
                $bad[] = "{$id}: no property_types declared";
                continue;
            }

            foreach ($declared as $token) {
                if (!PT::isToken($token)) {
                    $bad[] = "{$id}: '{$token}' is not a property-type token";
                }
            }

            if (count(array_unique($declared)) !== count($declared)) {
                $bad[] = "{$id}: duplicate tokens";
            }
        }

        $this->assertSame([], $bad, "Catalog entries with unusable property-type applicability:\n  - "
            . implode("\n  - ", $bad));
    }

    public function test_a_landlord_question_never_declares_a_type_the_landlord_role_cannot_have(): void
    {
        // Landlord has no Income, Business or Vacant Land category, so a landlord entry naming
        // one could never match a real listing — a declaration that reads as coverage and is
        // dead. Universal entries are exempt: naming every token is a documented designation.
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            if (($entry['role'] ?? null) !== 'landlord') {
                continue;
            }

            $declared = $entry['property_types'] ?? [];

            if (count($declared) === count(PT::ALL_TYPES)) {
                continue;
            }

            foreach (array_intersect($declared, [PT::INCOME, PT::BUSINESS, PT::VACANT_LAND]) as $token) {
                $bad[] = "{$id} declares '{$token}', which no landlord listing can be";
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }

    // ── zero LLM ────────────────────────────────────────────────────────────

    public function test_the_openai_adapter_is_hard_disabled_for_ask_ai(): void
    {
        $this->assertFalse(
            AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED,
            'Ask AI must not have a reachable runtime path to a language model.'
        );
    }

    public function test_a_prompt_ready_package_is_refused_without_reaching_a_client(): void
    {
        // The client double FAILS THE TEST if it is ever called. That is the assertion:
        // not "the result said blocked", but "the provider was never reached". A double that
        // returned a canned response would let a future regression pass silently.
        $client = new class extends OpenAiClientService {
            public function __construct() {}
            public function send(array $payload, array $callOptions = []): array
            {
                throw new \RuntimeException('OpenAiClientService::send() was reached — the Ask AI LLM gate did not hold.');
            }
        };

        $adapter = new AskAiOpenAiAdapterService($client);

        $result = $adapter->generate(['status' => 'prompt_ready', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
        $this->assertSame('llm_answering_not_approved', $result['error']);
        $this->assertNull($result['raw_response']);
        $this->assertSame(0, $result['total_tokens']);
    }

    public function test_the_public_question_service_contains_no_language_model_path(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/AskAi/AskAiPublicPropertyQuestionService.php'
        );

        foreach (['OpenAi', 'openai', 'Http::', 'GuzzleHttp', 'curl_', 'file_get_contents(\'http'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $source,
                "The deterministic public question service must not reference '{$needle}'."
            );
        }
    }

    public function test_the_browser_question_picker_makes_no_network_call(): void
    {
        // Selection-based Ask AI (2026-09-25) replaced the typed-question matcher with the
        // question picker: search only filters the listed questions, so it needs no request.
        $js = file_get_contents(dirname(__DIR__, 3) . '/public/js/ask-ai/question-picker.js');

        $this->assertNotSame('', trim((string) $js));

        foreach (['fetch(', 'XMLHttpRequest', 'axios', 'WebSocket', 'EventSource', 'navigator.sendBeacon'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $js,
                "The question picker must resolve in the browser without '{$needle}'."
            );
        }
    }
}
