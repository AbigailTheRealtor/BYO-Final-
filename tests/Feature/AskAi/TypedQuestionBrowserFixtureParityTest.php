<?php

namespace Tests\Feature\AskAi;

use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use Tests\TestCase;

/**
 * The browser fixture must keep saying what the application actually renders.
 *
 * tests/browser/ask-ai-typed-question.spec.js drives a static fixture rather than a booted
 * Laravel page, which is what lets it run with no database and no authentication. The price
 * of that is drift: a fixture that fell behind the catalog would let the spec go on passing
 * against a card the application no longer produces, and the zero-network proof would be
 * about a page nobody visits.
 *
 * So this test re-derives the vocabulary from the real service and asserts the fixture still
 * carries it — and, just as importantly, asserts the fixture carries the same STRUCTURE the
 * Blade partial emits, since the matcher selects on those attributes.
 *
 * Follows the pattern main established in VirtualDriveBrowserFixtureParityTest.
 */
class TypedQuestionBrowserFixtureParityTest extends TestCase
{
    private const FIXTURE = 'tests/browser/fixtures/ask-ai/typed-question.html';
    private const PARTIAL = 'resources/views/offer-listing/partials/_ask-ai-property-card.blade.php';
    private const MATCHER = 'public/js/ask-ai/deterministic-question-matcher.js';

    private function fixture(): string
    {
        $path = base_path(self::FIXTURE);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** id => aliases, as the fixture carries them. */
    private function fixtureVocabulary(string $html): array
    {
        preg_match_all(
            '/data-property-question="([a-z_0-9]+)"\s+data-question-aliases="([^"]*)"/',
            $html, $m, PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as [, $id, $aliases]) {
            $out[$id] = array_values(array_filter(explode('|', html_entity_decode($aliases, ENT_QUOTES))));
        }

        return $out;
    }

    /** @test */
    public function every_fixture_question_carries_the_services_real_aliases(): void
    {
        $service = new AskAiPublicPropertyQuestionService();
        $fixture = $this->fixtureVocabulary($this->fixture());

        $this->assertNotEmpty($fixture, 'The fixture carries no questions.');

        // Re-derive each question's vocabulary from the catalog through the real service.
        //
        // The meta is PER ROLE, not one shared array. A single array looked tidier and was
        // wrong: buyer's `maximum_budget` is one of the keys tenant's rent-divergence guard
        // compares against, so a shared 450000 made the tenant rent question hide and the
        // fixture look as though it had drifted.
        $scenarios = [
            'seller' => [
                ['hoa_association' => 'Yes', 'hoa_fee' => '250', 'hoa_payment_schedule' => 'Monthly',
                 'association_fee_includes' => 'Water', 'annual_property_taxes' => '4200',
                 'tax_year' => '2025', 'flood_zone_code' => 'AE', 'pool' => 'Yes', 'bedrooms' => '3'],
                ['has_hoa' => 'Yes', 'association_fee_amount' => '250', 'association_fee_frequency' => 'Monthly',
                 'association_fee_includes' => '["Water","Trash"]', 'bedrooms' => '3'],
            ],
            'landlord' => [
                ['hoa_association' => 'Yes', 'hoa_fee' => '175', 'hoa_payment_schedule' => 'Quarterly',
                 'flood_zone_code' => 'VE', 'pet_policy' => 'Yes', 'bedrooms' => '2'],
                ['has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Quarterly',
                 'bedrooms' => '2'],
            ],
            'buyer' => [
                ['max_price' => '450000', 'bedrooms' => '3', 'cities' => 'Seminole', 'counties' => 'Pinellas'],
                ['maximum_budget' => '450000', 'bedrooms' => '3'],
            ],
            'tenant' => [
                ['cities' => 'Seminole', 'bedrooms' => '2',
                 'move_in_date_earliest' => '2027-01-15', 'move_in_date_latest' => '2027-03-01'],
                ['budget' => '2500', 'bedrooms' => '2', 'pets' => 'Yes', 'tenant_require' => '["Furnished"]'],
            ],
        ];

        $real = [];
        foreach ($scenarios as $role => [$listing, $meta]) {
            foreach ($service->forListing($role, ['listing' => $listing + ['property_type' => 'Residential']], $meta) as $q) {
                $real[$q['id']] = $q['aliases'];
            }
        }

        foreach ($fixture as $id => $aliases) {
            $this->assertArrayHasKey($id, $real, "The fixture carries '{$id}', which the service no longer produces.");
            $this->assertSame($real[$id], $aliases,
                "The fixture's aliases for '{$id}' have drifted from the catalog. Regenerate the fixture.");
        }
    }

    /** @test */
    public function the_fixture_carries_every_hook_the_matcher_selects_on(): void
    {
        $fixture = $this->fixture();
        $partial = (string) file_get_contents(base_path(self::PARTIAL));
        $matcher = (string) file_get_contents(base_path(self::MATCHER));

        // Every attribute the matcher queries for must exist in BOTH the real partial and
        // the fixture — otherwise the spec exercises hooks the application does not emit.
        preg_match_all('/\[(data-[a-z-]+)\]/', $matcher, $m);
        $selectors = array_values(array_unique($m[1]));
        $this->assertNotEmpty($selectors);

        foreach ($selectors as $attribute) {
            $this->assertStringContainsString($attribute, $partial, "The partial does not emit {$attribute}.");
            $this->assertStringContainsString($attribute, $fixture, "The fixture does not carry {$attribute}.");
        }

        // And the element shapes the matcher walks.
        foreach (['details[data-property-question]' => 'data-property-question',
                  'summary'                          => '<summary'] as $needle) {
            $this->assertStringContainsString($needle, $fixture);
        }
    }

    /** @test */
    public function the_fixture_is_covered_by_all_four_roles(): void
    {
        $fixture = $this->fixture();

        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $this->assertStringContainsString('data-ask-ai-property-questions="' . $role . '"', $fixture,
                "The fixture has no {$role} card, so the spec cannot cover that role.");
        }
    }

    /** @test */
    public function the_fixture_contains_no_form_and_no_named_input(): void
    {
        // The same structural guarantee the real card carries — asserted on the fixture too,
        // so the browser spec is exercising a page with the production shape.
        $fixture = $this->fixture();

        $this->assertStringNotContainsString('<form', $fixture);
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $fixture);
        $this->assertStringContainsString('type="button"', $fixture);
    }
}
