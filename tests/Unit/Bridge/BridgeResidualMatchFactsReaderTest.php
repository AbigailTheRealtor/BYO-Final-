<?php

namespace Tests\Unit\Bridge;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\Bridge\BridgeResidualMatchFactsReader;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B — the Bridge residual reader hands over exactly what the legacy builder reads,
 * on every committed fixture and on the edge rows the legacy expressions tolerate.
 */
class BridgeResidualMatchFactsReaderTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    public function test_it_equals_the_legacy_builder_on_every_committed_fixture(): void
    {
        foreach ($this->storeAllBaselineFixtures() as $slug => $row) {
            $this->assertMatchesLegacy($row, $slug);
        }
    }

    public function test_it_equals_the_legacy_builder_on_edge_rows(): void
    {
        $nested = $this->storeBaselineFixture('residential_lease', [
            'CommunityFeatures'    => [['Pool', 'Gym']],
            'AssociationAmenities' => [['Clubhouse']],
            'LeaseTerm'            => ['12 Months'],
            'BuildingAreaTotal'    => null,
        ], 'reader_nested');
        $this->assertMatchesLegacy($nested, 'nested arrays');

        $null = $this->storeBaselineFixture('residential', [], 'reader_null');
        $null->raw_json = null;
        $this->assertMatchesLegacy($null, 'null raw_json');
        $this->assertFalse(BridgeResidualMatchFactsReader::read($null)->floodZoneStated);

        $malformed = $this->storeBaselineFixture('residential', [], 'reader_malformed');
        $malformed->raw_json = '{not json';
        $this->assertMatchesLegacy($malformed, 'malformed raw_json');

        $scalar = $this->storeBaselineFixture('residential', [], 'reader_scalar');
        $scalar->raw_json = '"a string"';
        $this->assertMatchesLegacy($scalar, 'scalar raw_json');
    }

    public function test_it_never_writes_the_row(): void
    {
        $row = $this->storeBaselineFixture('residential', [], 'reader_clean');

        BridgeResidualMatchFactsReader::read($row);

        $this->assertFalse($row->isDirty());
    }

    private function assertMatchesLegacy(BridgeProperty $row, string $label): void
    {
        $legacy   = BridgeListingMatchFactsBuilder::build($row);
        $residual = BridgeResidualMatchFactsReader::read($row);

        foreach ((new ReflectionClass(ListingMatchResidualFacts::class))->getConstructor()->getParameters() as $p) {
            $field = $p->getName();
            $this->assertSame($legacy->{$field}, $residual->{$field}, "{$label}: {$field}");
        }
    }
}
