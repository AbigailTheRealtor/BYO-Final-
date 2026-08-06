<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\Rules\GeographyRule;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionResolver;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionValidator;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1b — the rules against the real schema rather than the fake.
 *
 * WHAT ONLY THIS SUITE CAN PROVE
 * ------------------------------
 * The unit suites prove the rules against a fake that returns whatever it was handed, so a ZIP is
 * associated with two counties because the fixture said so. Here the association has to survive the
 * actual join Phase 1a performs — bare county name plus state abbreviation, with the class suffix
 * stripped — which is the only place the cascade can be broken by data rather than by logic.
 *
 * Rows are inserted directly, following the Phase 1a suite: these are reference tables, not domain
 * models, and several have no factory.
 */
class CriteriaGeographyRulesTest extends TestCase
{
    use DatabaseTransactions;

    private EloquentCriteriaGeographyRepository $repo;

    private GeographySelectionResolver $resolver;

    private GeographySelectionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo      = new EloquentCriteriaGeographyRepository();
        $this->resolver  = new GeographySelectionResolver($this->repo);
        $this->validator = new GeographySelectionValidator($this->repo);
    }

    private function state(string $name, string $abbrev, ?string $fips = null): int
    {
        return (int) DB::table('us_states')->insertGetId([
            'name'         => $name,
            'abbreviation' => $abbrev,
            'fips_code'    => $fips,
        ]);
    }

    private function county(string $name, int $stateId, ?string $fips = null): int
    {
        return (int) DB::table('us_counties')->insertGetId([
            'name'      => $name,
            'state_id'  => $stateId,
            'fips_code' => $fips,
        ]);
    }

    private function city(string $name, int $stateId, int $countyId): int
    {
        return (int) DB::table('us_cities')->insertGetId([
            'name'      => $name,
            'state_id'  => $stateId,
            'county_id' => $countyId,
        ]);
    }

    private function zip(string $zip, string $city, string $abbrev, ?string $county): void
    {
        DB::table('us_zip_codes')->insert([
            'zip_code'     => $zip,
            'city'         => $city,
            'state_abbrev' => $abbrev,
            'state_name'   => $abbrev,
            'county'       => $county,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE CROSS-COUNTY ZIP, AND WHAT THE SCHEMA ACTUALLY PERMITS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * DISCOVERY, PINNED: `us_zip_codes.zip_code` is UNIQUE, so a ZIP cannot be stored under two
     * counties at all.
     *
     * Phase 1a's interface documents ZIP→county as a many-to-many — "a ZIP may be returned under
     * more than one county" — and Phase 1b's association rule was designed around that. The claim
     * is not reachable in this schema: `2025_12_07_231024_create_us_zip_codes_table.php` declares
     * `$table->unique('zip_code')`, so every ZIP carries exactly one county name and
     * `zipsInCounties()` can only ever emit it once.
     *
     * This test exists so that fact is recorded in the suite rather than rediscovered. If a later
     * phase relaxes the constraint — or the spatial corpus introduces real ZCTA geometry, where a
     * ZIP genuinely does cross county lines — this test fails and points at the rules that were
     * built for that case.
     *
     * The association rule is kept regardless, and costs nothing to keep: while ZIPs are
     * single-parent, "survives if ANY selected county is associated" and "survives if its one
     * county is selected" are the same rule. Only one of them is still correct if the data ever
     * becomes many-to-many, and it is the one implemented.
     */
    public function test_the_schema_currently_forbids_a_zip_in_two_counties(): void
    {
        $ny = $this->state('New York', 'NY', '36');
        $this->county('Suffolk County', $ny, '36103');
        $this->county('Nassau County', $ny, '36059');

        $this->zip('11001', 'Floral Park', 'NY', 'Suffolk');

        $this->expectException(\Illuminate\Database\QueryException::class);

        // The second association is what the many-to-many would require, and the schema rejects it.
        $this->zip('11001', 'Floral Park', 'NY', 'Nassau');
    }

    /**
     * With single-parent ZIPs, the association rule still clears exactly the orphans.
     *
     * This is the end-to-end shape the real data can produce: two counties, a ZIP under each. The
     * ZIP whose county survives is kept; the other is cleared. It also proves the name join —
     * both county rows have to strip their "County" suffix to match `us_zip_codes.county` before
     * the rules layer sees anything at all.
     */
    public function test_zips_are_cleared_with_their_county_over_real_data(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $nassau  = $this->county('Nassau County', $ny, '36059');

        $this->zip('11701', 'Amityville', 'NY', 'Suffolk');
        $this->zip('11550', 'Hempstead', 'NY', 'Nassau');

        // Control: both counties selected, both ZIPs justified.
        $both = $this->resolver->resolve(GeographySelection::of(
            (string) $ny,
            [(string) $suffolk, (string) $nassau],
            [],
            ['11701', '11550'],
        ));

        $this->assertSame(['11701', '11550'], $both->selection->zipCodes);
        $this->assertFalse($both->changed());

        // Drop Suffolk: its ZIP is orphaned, Nassau's is untouched.
        $afterDrop = $this->resolver->resolve(GeographySelection::of(
            (string) $ny,
            [(string) $nassau],
            [],
            ['11701', '11550'],
        ));

        $this->assertSame(['11550'], $afterDrop->selection->zipCodes);
        $this->assertSame(['11701'], $afterDrop->clearedIdsFor(GeographyTier::ZipCodes));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COUNTY-CLASS SUFFIXES THE CASCADE MUST RESOLVE
    // ═════════════════════════════════════════════════════════════════════════

    /** Louisiana Parishes, Alaska Boroughs and Census Areas all have to reach the rules layer. */
    public function test_non_county_class_suffixes_resolve_through_the_rules(): void
    {
        $la      = $this->state('Louisiana', 'LA', '22');
        $orleans = $this->county('Orleans Parish', $la, '22071');
        $this->zip('70112', 'New Orleans', 'LA', 'Orleans');

        $ak     = $this->state('Alaska', 'AK', '02');
        $nome   = $this->county('Nome Census Area', $ak, '02180');
        $juneau = $this->county('Juneau City and Borough', $ak, '02110');
        $this->zip('99762', 'Nome', 'AK', 'Nome');
        $this->zip('99801', 'Juneau', 'AK', 'Juneau');

        foreach ([
            [$la, $orleans, '70112'],
            [$ak, $nome, '99762'],
            [$ak, $juneau, '99801'],
        ] as [$stateId, $countyId, $zip]) {
            $result = $this->validator->validate(GeographySelection::of(
                (string) $stateId,
                [(string) $countyId],
                [],
                [$zip],
            ));

            $this->assertTrue(
                $result->isComplete(),
                "ZIP {$zip} was not justified under county {$countyId}: ".json_encode($result->toArray())
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE UNPADDED-ZIP REALITY
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A ZIP stored unpadded is enumerated padded, so the padded form is what validates.
     *
     * Both halves matter: "00501" must be accepted, and the raw stored "501" must not — otherwise
     * the layer would quietly accept a value that matches nothing a user can type.
     */
    public function test_an_unpadded_stored_zip_is_justified_only_in_its_padded_form(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $this->zip('501', 'Holtsville', 'NY', 'Suffolk');

        $padded = $this->validator->validate(GeographySelection::of(
            (string) $ny,
            [(string) $suffolk],
            [],
            ['00501'],
        ));

        $this->assertTrue($padded->isComplete(), 'the padded form is the canonical one');

        $raw = $this->validator->validate(GeographySelection::of(
            (string) $ny,
            [(string) $suffolk],
            [],
            ['501'],
        ));

        $this->assertTrue($raw->hasRule(GeographyRule::MalformedZip));
        $this->assertFalse($raw->isValid());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CROSS-STATE AND CONTAINMENT, OVER REAL SQL
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_county_from_another_state_is_rejected_over_real_data(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $la      = $this->state('Louisiana', 'LA', '22');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $orleans = $this->county('Orleans Parish', $la, '22071');

        $result = $this->validator->validate(GeographySelection::of(
            (string) $ny,
            [(string) $suffolk, (string) $orleans],
        ));

        $this->assertTrue($result->hasRule(GeographyRule::CountyNotInState));
        $this->assertSame(
            [(string) $orleans],
            array_map(fn ($v) => $v->offendingId, $result->violationsFor(GeographyTier::Counties))
        );
    }

    public function test_a_city_is_cleared_with_its_county_over_real_data(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $nassau  = $this->county('Nassau County', $ny, '36059');
        $babylon = $this->city('Babylon', $ny, $suffolk);
        $hemp    = $this->city('Hempstead', $ny, $nassau);

        $resolution = $this->resolver->resolve(GeographySelection::of(
            (string) $ny,
            [(string) $nassau],
            [(string) $babylon, (string) $hemp],
        ));

        $this->assertSame([(string) $hemp], $resolution->selection->cityIds);
        $this->assertSame([(string) $babylon], $resolution->clearedIdsFor(GeographyTier::Cities));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE RULES LAYER STILL WRITES NOTHING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Resolution and validation over real SQL issue no write statement.
     *
     * The static guard proves no write APPEARS in the source. This proves none is REACHED at
     * runtime, including through the repository the rules layer delegates to.
     */
    public function test_resolving_and_validating_issue_no_write_sql(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $this->city('Babylon', $ny, $suffolk);
        $this->zip('11701', 'Amityville', 'NY', 'Suffolk');

        $selection = GeographySelection::of((string) $ny, [(string) $suffolk], [], ['11701']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->resolver->resolve($selection);
        $this->validator->validate($selection);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($queries, 'the rules layer must actually read');

        foreach ($queries as $query) {
            $sql = strtolower((string) ($query['query'] ?? ''));

            foreach (['insert', 'update', 'delete', 'alter', 'drop', 'create'] as $write) {
                $this->assertStringNotContainsString(
                    $write.' ',
                    $sql,
                    "the rules layer issued a `{$write}` statement: {$sql}"
                );
            }
        }
    }

    /** And the resolver's output validates clean against real data too. */
    public function test_a_resolved_selection_validates_clean_over_real_data(): void
    {
        $ny      = $this->state('New York', 'NY', '36');
        $la      = $this->state('Louisiana', 'LA', '22');
        $suffolk = $this->county('Suffolk County', $ny, '36103');
        $orleans = $this->county('Orleans Parish', $la, '22071');
        $babylon = $this->city('Babylon', $ny, $suffolk);
        $this->zip('11701', 'Amityville', 'NY', 'Suffolk');
        $this->zip('70112', 'New Orleans', 'LA', 'Orleans');

        $resolution = $this->resolver->resolve(GeographySelection::of(
            (string) $ny,
            [(string) $suffolk, (string) $orleans, (string) $suffolk],
            [(string) $babylon],
            ['11701', '70112', '501'],
        ));

        $result = $this->validator->validate($resolution->selection);

        $this->assertTrue($result->isValid(), json_encode($result->toArray()));
        $this->assertTrue($result->isComplete());
        $this->assertSame(['11701'], $resolution->selection->zipCodes);
    }
}
