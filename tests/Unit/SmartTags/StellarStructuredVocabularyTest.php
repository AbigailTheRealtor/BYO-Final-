<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Services\SmartTags\Seeker\BridgeSmartTagCheckability;
use App\Services\SmartTags\SmartTagResolver;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * Stellar's own structured spellings, as the live feed sends them (2026-09-25 vocabulary
 * audit, read-only aggregate counts).
 *
 * Under seeker matching a populated field without the selected value is a KNOWN miss, so
 * an unrecognised Stellar spelling is not harmless silence: it prints "Does not list: X"
 * on a listing that states X. Each case below is a string the feed actually sends, read
 * the way the repository already reads it elsewhere or as a member of a value family the
 * dictionary already maps.
 */
class StellarStructuredVocabularyTest extends TestCase
{
    use BuildsSmartTagRecords;

    private BridgeStructuredTagDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        SmartTagConfig::flush();
        SmartTagSourceRules::flush();
        $this->deriver = new BridgeStructuredTagDeriver();
    }

    /** @return array<string, string> tag => state */
    private function derived(BridgeRecordAccessor $record, SmartTagContext $context): array
    {
        return $this->states($this->deriver->derive($record, $context));
    }

    /**
     * What seeker matching would answer for $keys on this record, from the in-memory
     * derivation — the same classification the coverage audit and the matcher use.
     *
     * @param list<string> $keys
     * @return array<string, string>
     */
    private function checks(BridgeRecordAccessor $record, SmartTagContext $context, array $keys): array
    {
        $resolution = SmartTagResolver::resolve(array_values($this->deriver->derive($record, $context)), $context);
        $resolved = [];
        foreach ($resolution->assignments as $key => $assignment) {
            $resolved[$key] = $assignment->state;
        }

        return BridgeSmartTagCheckability::classify(
            $record, $context, $keys, $resolved, array_fill_keys($resolution->droppedForConflict, true), true,
        );
    }

    /** @test */
    public function a_turnkey_rental_is_furnished_and_never_reads_as_a_furnished_miss(): void
    {
        $record = $this->bridgeFixture('residential_lease', ['Furnished' => 'Turnkey']);

        $this->assertSame('present', $this->derived($record, SmartTagContext::ResidentialLease)['furnished'] ?? null);
        $this->assertSame(
            ['furnished' => BridgeSmartTagCheckability::PRESENT, 'unfurnished' => BridgeSmartTagCheckability::KNOWN_ABSENT],
            $this->checks($record, SmartTagContext::ResidentialLease, ['furnished', 'unfurnished']),
        );
    }

    /** @test */
    public function the_other_furnishing_values_are_unchanged(): void
    {
        foreach (['Furnished' => 'furnished', 'Unfurnished' => 'unfurnished'] as $value => $tag) {
            $states = $this->derived($this->bridgeFixture('residential_lease', ['Furnished' => $value]), SmartTagContext::ResidentialLease);
            $this->assertSame('present', $states[$tag] ?? null, $value);
        }

        // Negotiable and Partially are not "Furnished" and gain nothing here.
        foreach (['Negotiable', 'Partially'] as $value) {
            $states = $this->derived($this->bridgeFixture('residential_lease', ['Furnished' => $value]), SmartTagContext::ResidentialLease);
            $this->assertArrayNotHasKey('furnished', $states, $value);
            $this->assertArrayNotHasKey('unfurnished', $states, $value);
        }
    }

    /** @test */
    public function pre_construction_is_under_construction_as_the_native_dictionary_already_reads_it(): void
    {
        $record = $this->bridgeFixture('residential', ['PropertyCondition' => ['Pre-Construction'], 'NewConstructionYN' => true]);
        $this->assertSame('present', $this->derived($record, SmartTagContext::ResidentialSale)['under_construction'] ?? null);

        $completed = $this->bridgeFixture('residential', ['PropertyCondition' => ['Completed']]);
        $this->assertArrayNotHasKey('under_construction', $this->derived($completed, SmartTagContext::ResidentialSale));
    }

    /** @test */
    public function stellar_water_access_family_members_map_to_their_body_of_water(): void
    {
        $cases = [
            'Beach - Access Deeded'                       => 'beach_access',
            'Canal - Brackish'                            => 'canal_frontage',
            'Freshwater Canal w/Lift to Saltwater Canal'  => 'canal_frontage',
            'Lake - Chain of Lakes'                       => 'lake_access',
        ];

        foreach ($cases as $value => $tag) {
            $record = $this->bridgeFixture('residential_lease', ['STELLAR_WaterAccess' => [$value], 'WaterfrontFeatures' => []]);
            $this->assertSame('present', $this->derived($record, SmartTagContext::ResidentialLease)[$tag] ?? null, $value);
            $this->assertSame(BridgeSmartTagCheckability::PRESENT, $this->checks($record, SmartTagContext::ResidentialLease, [$tag])[$tag], $value);
        }
    }

    /** @test */
    public function values_that_are_not_a_stated_body_of_water_stay_unmapped(): void
    {
        // A marina, a lagoon, brackish water and "limited access" name no mapped tag;
        // a view string is never access.
        foreach (['Marina', 'Lagoon', 'Brackish Water', 'Limited Access'] as $value) {
            $record = $this->bridgeFixture('residential_lease', ['STELLAR_WaterAccess' => [$value], 'WaterfrontFeatures' => []]);
            $states = $this->derived($record, SmartTagContext::ResidentialLease);
            foreach (['beach_access', 'canal_frontage', 'lake_access', 'gulf_or_ocean_access', 'bay_or_harbor_access'] as $tag) {
                $this->assertArrayNotHasKey($tag, $states, "$value must not emit $tag");
            }
        }
    }

    /** @test */
    public function a_solar_heated_pool_is_a_heated_pool(): void
    {
        $record = $this->bridgeFixture('residential', ['PoolFeatures' => ['In Ground', 'Solar Heat']]);

        $this->assertSame('present', $this->derived($record, SmartTagContext::ResidentialSale)['heated_pool'] ?? null);
        $this->assertSame(BridgeSmartTagCheckability::PRESENT, $this->checks($record, SmartTagContext::ResidentialSale, ['heated_pool'])['heated_pool']);
    }

    /** @test */
    public function an_aerobic_septic_system_is_a_septic_system(): void
    {
        $record = $this->bridgeFixture('commercial_sale', ['Sewer' => ['Aerobic Septic']]);

        $this->assertSame('present', $this->derived($record, SmartTagContext::CommercialSale)['septic_system'] ?? null);

        // "Septic Needed" says there is none yet — still not a septic system.
        $needed = $this->bridgeFixture('commercial_sale', ['Sewer' => ['Septic Needed']]);
        $this->assertArrayNotHasKey('septic_system', $this->derived($needed, SmartTagContext::CommercialSale));
    }
}
