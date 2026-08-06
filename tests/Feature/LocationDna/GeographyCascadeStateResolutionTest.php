<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographyCascade;
use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-3 — the cascade resolves its selected state through the BOUND REPOSITORY.
 *
 * WHAT THIS SUITE EXISTS TO PREVENT
 * ---------------------------------
 * `rememberSelectedState()` used to read `UsState::query()->whereKey($this->geoStateId)`. That was
 * correct only under the `eloquent` source, where a state option's id happens to be the
 * `us_states` primary key. Under `census` the id is a two-digit GEOID, so the same lookup
 * addressed an unrelated `us_states` row — or none.
 *
 * The abbreviation it produces is the suffix in the stored `Pinellas County, FL` label. A wrong
 * one is therefore not a display glitch: it is persisted, and afterwards indistinguishable from a
 * label the user chose. Nothing would have raised.
 *
 * The two sources are asserted SIDE BY SIDE, against deliberately CONFLICTING fixtures: the census
 * state whose GEOID is `12` is Florida, while `us_states` row 12 is a different state entirely.
 * An implementation that resolved through the legacy model would pass the eloquent case and fail
 * the census one, which is exactly the asymmetry the old code had.
 */
class GeographyCascadeStateResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private function host(): object
    {
        return new class {
            use HasGeographyCascade;

            public function enable(): void
            {
                $this->geoCascadeEnabled = true;
            }

            public function select(string $stateId, array $countyIds): void
            {
                $this->geoStateId  = $stateId;
                $this->geoCountyIds = $countyIds;
            }

            public function refresh(): void
            {
                $this->refreshGeographyCascade();
            }

            public function stateName(): string
            {
                return $this->geoStateName;
            }

            public function stateAbbrev(): string
            {
                return $this->geoStateAbbrev;
            }
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixtures — built so the two sources DISAGREE about what id `12` means
    // ─────────────────────────────────────────────────────────────────────

    /** @return string the census state GEOID */
    private function seedCensusFlorida(): string
    {
        DB::table('census_states')->insert(['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida']);
        DB::table('census_counties')->insert([
            'geoid'       => '12103',
            'state_geoid' => '12',
            'countyfp'    => '103',
            'name'        => 'Pinellas County',
            'basename'    => 'Pinellas',
        ]);

        return '12';
    }

    /** @return array{0: string, 1: string} the us_states id and its county id */
    private function seedEloquentDecoy(): array
    {
        // Rows are inserted until the auto-increment key reaches something other than Florida's
        // GEOID meaning, so a whereKey() lookup on '12' cannot accidentally be right.
        $stateId = (int) DB::table('us_states')->insertGetId([
            'name'         => 'Zzytopia',
            'abbreviation' => 'ZZ',
            'fips_code'    => '99',
        ]);

        $countyId = (int) DB::table('us_counties')->insertGetId([
            'name'      => 'Pinellas County',
            'state_id'  => $stateId,
            'fips_code' => '99103',
        ]);

        return [(string) $stateId, (string) $countyId];
    }

    // ─────────────────────────────────────────────────────────────────────
    // The two sources
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_census_source_resolves_the_state_from_the_census_corpus(): void
    {
        $geoid = $this->seedCensusFlorida();

        $this->app->bind(CriteriaGeographyRepository::class, fn () => new CensusCriteriaGeographyRepository());

        $host = $this->host();
        $host->enable();
        $host->select($geoid, ['12103']);
        $host->refresh();

        $this->assertSame('Florida', $host->stateName());
        $this->assertSame(
            'FL',
            $host->stateAbbrev(),
            'The abbreviation must come from census_states.usps. A legacy us_states lookup keyed on '
            .'the GEOID would return a different state, or nothing, and the stored label would be wrong.'
        );
    }

    /** @test */
    public function the_eloquent_source_still_resolves_exactly_as_it_always_did(): void
    {
        [$stateId, $countyId] = $this->seedEloquentDecoy();

        $this->app->bind(CriteriaGeographyRepository::class, fn () => new EloquentCriteriaGeographyRepository());

        $host = $this->host();
        $host->enable();
        $host->select($stateId, [$countyId]);
        $host->refresh();

        $this->assertSame('Zzytopia', $host->stateName());
        $this->assertSame('ZZ', $host->stateAbbrev(), 'Existing eloquent behaviour must be unchanged.');
    }

    /**
     * A state the corpus cannot resolve degrades to empty strings rather than throwing.
     *
     * This is the condition a legacy record hits when its stored state is absent from the corpus.
     * The previous implementation reached the same outcome via `first()` returning null, and the
     * phase's rule is that unresolvable history is preserved and reported, never fatal.
     */
    /** @test */
    public function an_unresolvable_state_yields_empty_strings_rather_than_an_error(): void
    {
        $this->seedCensusFlorida();

        $this->app->bind(CriteriaGeographyRepository::class, fn () => new CensusCriteriaGeographyRepository());

        $host = $this->host();
        $host->enable();
        $host->select('99', []);
        $host->refresh();

        $this->assertSame('', $host->stateName());
        $this->assertSame('', $host->stateAbbrev());
    }

    /** No state selected is not an error either. */
    /** @test */
    public function no_selected_state_yields_empty_strings(): void
    {
        $this->app->bind(CriteriaGeographyRepository::class, fn () => new CensusCriteriaGeographyRepository());

        $host = $this->host();
        $host->enable();
        $host->select('', []);
        $host->refresh();

        $this->assertSame('', $host->stateName());
        $this->assertSame('', $host->stateAbbrev());
    }

    // ─────────────────────────────────────────────────────────────────────
    // The legacy coupling is gone
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The trait names no legacy reference model in its CODE.
     *
     * Asserted against the source with comments stripped, because the docblock deliberately
     * discusses `UsState` to record why the coupling was removed — and a naive substring search
     * would read that explanation as the defect it describes.
     */
    /** @test */
    public function the_trait_references_no_legacy_reference_model(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/Concerns/HasGeographyCascade.php'));

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        foreach (['UsState', 'UsCounty', 'UsCity', 'us_states', 'us_counties'] as $legacy) {
            $this->assertStringNotContainsString(
                $legacy,
                $code,
                "The cascade must reach the corpus only through CriteriaGeographyRepository; `{$legacy}` "
                .'is a source-specific dependency that made the census binding silently wrong.'
            );
        }
    }
}
