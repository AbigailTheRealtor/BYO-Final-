<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use Tests\TestCase;

/**
 * Phase 4 · Wave 1 / C3 — CriteriaIntentDetector (F5).
 *
 * Sale → buyer, Rent → tenant, tenure-ambiguous → null.
 */
class CriteriaIntentDetectorTest extends TestCase
{
    private function detector(): CriteriaIntentDetector
    {
        return new CriteriaIntentDetector();
    }

    /**
     * @test
     * @dataProvider typeProvider
     */
    public function it_maps_property_type_to_intent(?string $type, ?string $expected): void
    {
        $this->assertSame($expected, $this->detector()->detectFromType($type));
    }

    public static function typeProvider(): array
    {
        return [
            'commercial lease → tenant'       => ['Commercial Lease', CriteriaIntentDetector::TENANT],
            'residential lease → tenant'      => ['Residential Lease', CriteriaIntentDetector::TENANT],
            'commercial sale → buyer'         => ['Commercial Sale', CriteriaIntentDetector::BUYER],
            'income → buyer'                  => ['Income', CriteriaIntentDetector::BUYER],
            'business opportunity → buyer'    => ['Business Opportunity', CriteriaIntentDetector::BUYER],
            'vacant land → buyer'             => ['Vacant Land', CriteriaIntentDetector::BUYER],
            'land → buyer'                    => ['Land', CriteriaIntentDetector::BUYER],
            'bare residential → null (ambiguous)' => ['Residential', null],
            'bare commercial → null (ambiguous)'  => ['Commercial', null],
            'case-insensitive lease'          => ['cOmMeRcIaL lEaSe', CriteriaIntentDetector::TENANT],
            'whitespace trimmed'              => ['  Commercial Sale  ', CriteriaIntentDetector::BUYER],
            'empty string → null'             => ['', null],
            'null → null'                     => [null, null],
        ];
    }

    /** @test */
    public function it_detects_from_a_bridge_property_model(): void
    {
        $rental = new BridgeProperty(['property_type' => 'Commercial Lease']);
        $sale   = new BridgeProperty(['property_type' => 'Commercial Sale']);
        $ambig  = new BridgeProperty(['property_type' => 'Residential']);

        $this->assertSame(CriteriaIntentDetector::TENANT, $this->detector()->detectFromModel($rental));
        $this->assertSame(CriteriaIntentDetector::BUYER, $this->detector()->detectFromModel($sale));
        $this->assertNull($this->detector()->detectFromModel($ambig));
    }
}
