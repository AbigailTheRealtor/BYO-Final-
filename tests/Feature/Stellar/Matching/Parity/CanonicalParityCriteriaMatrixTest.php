<?php

namespace Tests\Feature\Stellar\Matching\Parity;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner as Runner;
use App\Services\Stellar\Matching\Parity\CanonicalParityCriteriaMatrix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\Feature\Stellar\Matching\Baseline\PreConvergenceScoringBaselineTest;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B2 — the synthetic criteria matrix.
 *
 * The A0 families are re-derived from each listing's own facts. On the seven committed
 * fixtures that derivation must equal P1-A0's own cases exactly, so the offline runner
 * exercises what the golden baseline pins rather than a lookalike that drifted.
 */
class CanonicalParityCriteriaMatrixTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    public function test_the_a0_families_equal_the_p1a0_cases_on_every_committed_fixture(): void
    {
        $matrix   = new CanonicalParityCriteriaMatrix();
        $baseline = new PreConvergenceScoringBaselineTest('matrix_drift_probe');
        $a0Cases  = new ReflectionMethod($baseline, 'allCasesFor');
        $a0Cases->setAccessible(true);

        foreach (self::$CATEGORIES as $slug => $type) {
            $row   = $this->storeBaselineFixture($slug);
            $facts = Runner::legacyFacts($row);

            $expected = $a0Cases->invoke($baseline, $slug, $slug);
            $actual   = $matrix->a0Families($facts, $row, Runner::stratumFor($facts->propertyType), (string) $facts->propertyType);

            $this->assertSame(array_keys($expected), array_keys($actual), "{$slug}: the A0 case names, in A0's order");
            $this->assertEqualsWithDelta($expected, $actual, 1e-6, "{$slug}: every A0 payload, value for value");
        }
    }

    public function test_every_stratum_and_other_type_gets_a0_and_edge_cases_that_construct(): void
    {
        $matrix = new CanonicalParityCriteriaMatrix();
        $rows   = $this->storeAllBaselineFixtures();
        $rows['farm'] = $this->storeBaselineFixture('residential', [], 'farm', ['property_type' => 'Farm']);

        $seen = [];
        foreach ($rows as $label => $row) {
            $facts   = Runner::legacyFacts($row);
            $stratum = Runner::stratumFor($facts->propertyType);
            $cases   = $matrix->casesFor($facts, $row, $stratum);

            $this->assertArrayHasKey('baseline_city_only', $cases, $label);
            foreach (['edge_minimal_type_only', 'edge_amenities_true', 'edge_amenities_false', 'edge_type_other'] as $edge) {
                $this->assertArrayHasKey($edge, $cases, "{$label}: {$edge}");
            }
            foreach ($cases as $name => $overrides) {
                // Every case is buildable through the ordinary payload constructor.
                new BuyerCriteriaPayload(array_merge(['is_55_plus_eligible' => false], $overrides));
                $this->assertNotSame('', $name);
            }
            $seen[$stratum] = true;
        }

        $this->assertEqualsCanonicalizing(array_merge(Runner::STRATA, [Runner::OTHER_TYPE]), array_keys($seen));
    }

    public function test_rental_strata_carry_the_rent_period_edge_pair(): void
    {
        $matrix = new CanonicalParityCriteriaMatrix();
        $row    = $this->storeBaselineFixture('residential_lease');
        $facts  = Runner::legacyFacts($row);

        $cases = $matrix->edgeCases($facts, 'Residential Lease', 'Residential Lease');

        $this->assertArrayHasKey('edge_rent_budget_raw_price', $cases);
        $this->assertArrayHasKey('edge_rent_budget_monthly_equivalent', $cases);
        $this->assertSame(['Residential'], $cases['edge_type_other']['property_types'], 'a rental is also asked as a sale');
    }

    /**
     * No rent-derived (or price-derived) case for a rental without a usable rent. A missing,
     * zero or negative rent must never become a budget — and never a substituted 0.
     *
     * @dataProvider unusableRents
     */
    public function test_an_unusable_rent_produces_no_price_derived_case(string $slug, ?float $rent): void
    {
        $matrix = new CanonicalParityCriteriaMatrix();
        $row    = $this->storeBaselineFixture($slug, [], 'rent_' . ($rent === null ? 'null' : (string) $rent), ['list_price' => $rent]);
        $facts  = Runner::legacyFacts($row);
        $cases  = $matrix->casesFor($facts, $row, Runner::stratumFor($facts->propertyType));

        $this->assertNotSame([], $cases, 'the listing still gets its non-price cases');
        $this->assertArrayHasKey('lease_terms_1_year', $cases, 'lease-term cases do not depend on the rent');
        $this->assertArrayNotHasKey('lease_rent_with_terms', $cases);
        foreach ($cases as $name => $overrides) {
            foreach (['ideal_price', 'max_price', 'min_price'] as $key) {
                $this->assertArrayNotHasKey($key, $overrides, "{$slug} rent " . var_export($rent, true) . ": {$name} carries {$key}");
            }
        }
    }

    public static function unusableRents(): array
    {
        return [
            'residential lease, null rent'     => ['residential_lease', null],
            'residential lease, zero rent'     => ['residential_lease', 0.0],
            'residential lease, negative rent' => ['residential_lease', -1500.0],
            'residential lease, sub-dollar rent (rounds to 0)' => ['residential_lease', 0.4],
            'commercial lease, null rent'      => ['commercial_lease', null],
            'commercial lease, zero rent'      => ['commercial_lease', 0.0],
            'commercial lease, negative rent'  => ['commercial_lease', -12.5],
        ];
    }

    /** @dataProvider rentalSlugs */
    public function test_a_valid_rent_keeps_the_rent_derived_cases(string $slug): void
    {
        $matrix = new CanonicalParityCriteriaMatrix();
        $row    = $this->storeBaselineFixture($slug, [], 'rent_valid', ['list_price' => 2400]);
        $facts  = Runner::legacyFacts($row);
        $cases  = $matrix->casesFor($facts, $row, Runner::stratumFor($facts->propertyType));

        $this->assertSame((int) ceil(2400 * 1.05), $cases['lease_rent_with_terms']['max_price']);
        $this->assertSame(['1 Year'], $cases['lease_rent_with_terms']['preferred_lease_terms']);
        $this->assertSame((int) round(2400), $cases['price_ideal_at_list']['ideal_price']);
        $this->assertArrayHasKey('edge_rent_budget_raw_price', $cases);
    }

    public static function rentalSlugs(): array
    {
        return ['residential lease' => ['residential_lease'], 'commercial lease' => ['commercial_lease']];
    }

    public function test_no_ideal_price_of_zero_is_ever_synthesised(): void
    {
        $matrix = new CanonicalParityCriteriaMatrix();
        $rows   = $this->storeAllBaselineFixtures();
        foreach ([null, 0, -5, 0.4] as $i => $price) {
            $rows["p{$i}"] = $this->storeBaselineFixture('residential', [], "ideal_{$i}", ['list_price' => $price]);
            $rows["l{$i}"] = $this->storeBaselineFixture('residential_lease', [], "ideal_{$i}", ['list_price' => $price]);
        }

        $facts = [];
        foreach ($rows as $label => $row) {
            $f = Runner::legacyFacts($row);
            $facts[$f->propertyType][] = $f;
            foreach ($matrix->casesFor($f, $row, Runner::stratumFor($f->propertyType)) as $name => $o) {
                if (array_key_exists('ideal_price', $o)) {
                    $this->assertGreaterThan(0, $o['ideal_price'], "{$label}: {$name}");
                }
            }
        }
        // A cohort whose only prices are below a dollar gets no price band at all.
        $tiny = array_values(array_filter($facts['Residential'], static fn ($f) => $f->listPrice !== null && (float) $f->listPrice > 0 && (float) $f->listPrice < 1));
        $this->assertArrayNotHasKey('cohort_type_price_band', $matrix->cohortsFor('Residential', $tiny));
        foreach ($facts as $type => $list) {
            foreach ($matrix->cohortsFor((string) $type, $list) as $name => $o) {
                if (array_key_exists('ideal_price', $o)) {
                    $this->assertGreaterThan(0, $o['ideal_price'], "{$type}: {$name}");
                }
            }
        }
    }

    public function test_no_persisted_criteria_is_read(): void
    {
        $source = (string) file_get_contents(app_path('Services/Stellar/Matching/Parity/CanonicalParityCriteriaMatrix.php'));

        foreach (['CriteriaLoader', 'BuyerAgentAuction', 'TenantAgentAuction', 'DB::', '::query(', 'Auth::', 'auth()'] as $needle) {
            $this->assertStringNotContainsString($needle, $source, "the matrix must build criteria from the listing alone ({$needle})");
        }
    }

    public function test_cohorts_are_deterministic_and_tie_break_by_value(): void
    {
        $this->assertSame('B', CanonicalParityCriteriaMatrix::mostCommon(['C', 'B', 'B', 'A', null, '  ']), 'the most frequent wins');
        $this->assertSame('A', CanonicalParityCriteriaMatrix::mostCommon(['C', 'B', 'A', 'A', 'B', 'C']), 'a tie goes to the smallest value');
        $this->assertNull(CanonicalParityCriteriaMatrix::mostCommon([null, '', ' ']));
    }
}
