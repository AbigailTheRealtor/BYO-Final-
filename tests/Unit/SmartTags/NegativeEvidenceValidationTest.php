<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagTaxonomy;
use Tests\TestCase;

/**
 * `bridge.negative_evidence` can only NARROW what a real Bridge rule already emits, and its
 * tokens cannot contradict the rule — each broken declaration below is refused by name.
 */
class NegativeEvidenceValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        SmartTagConfig::flush();
        SmartTagSourceRules::flush();
    }

    protected function tearDown(): void
    {
        SmartTagConfig::flush();
        SmartTagSourceRules::flush();
        parent::tearDown();
    }

    /** @return list<string> */
    private function errorsFor(array $negativeEvidence): array
    {
        config()->set('smart_tag_sources.bridge.negative_evidence', $negativeEvidence);
        SmartTagSourceRules::flush();

        return array_values(array_filter(
            SmartTagSourceRules::validationErrors(),
            static fn (string $e) => str_starts_with($e, 'negative_evidence.'),
        ));
    }

    /** @test */
    public function the_shipped_declarations_are_valid(): void
    {
        $this->assertSame([], $this->errorsFor(config('smart_tag_sources.bridge.negative_evidence')));
    }

    /** @test */
    public function each_broken_declaration_is_refused(): void
    {
        $cases = [
            'no such Bridge rule'               => ['bridge.nope' => ['tags' => ['wet_bar']]],
            'declares no tags'                  => ['bridge.appliances' => ['tags' => []]],
            'not a tag this rule can emit'      => ['bridge.appliances' => ['tags' => ['wet_bar']]],
            'apply only to rules that read values' => ['bridge.fireplace_yn' => ['tags' => ['fireplace'], 'uninformative' => ['Other']]],
            'is a value the rule reads'         => ['bridge.appliances' => ['tags' => ['wine_refrigerator'], 'uninformative' => ['Wine Refrigerator']]],
            'which is not in its tags'          => ['bridge.flooring' => ['tags' => ['tile_flooring'], 'masked_by' => ['luxury_vinyl_flooring' => ['Vinyl']]]],
        ];

        foreach ($cases as $expected => $declaration) {
            $errors = $this->errorsFor($declaration);
            $this->assertNotEmpty(preg_grep('/' . preg_quote($expected, '/') . '/', $errors), "expected \"{$expected}\" in: " . implode(' | ', $errors));
        }

        // A mask that the rule itself reads as the feature is a contradiction too.
        $this->assertNotEmpty(preg_grep('/mask "Luxury Vinyl" is a value the rule reads/', $this->errorsFor([
            'bridge.flooring' => ['tags' => ['luxury_vinyl_flooring'], 'masked_by' => ['luxury_vinyl_flooring' => ['Luxury Vinyl']]],
        ])));
    }
}
