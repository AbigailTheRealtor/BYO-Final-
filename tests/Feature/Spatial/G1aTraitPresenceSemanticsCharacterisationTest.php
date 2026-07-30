<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\Concerns\HasSearchAreas;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * G1a — behavioural characterisation of the four `HasSearchAreas` `empty()` sites.
 *
 * WHAT THIS IS, AND WHAT IT IS NOT
 * --------------------------------
 * This is CHARACTERISATION. Every assertion below records what the trait does
 * TODAY, including where today's behaviour contradicts v1.2 §5.2. Nothing here
 * asserts the desired end state, and nothing here is a bug fix. If a test in
 * this file starts failing, the trait's observable behaviour changed — which is
 * exactly the signal G1f needs, and the reason this file must exist before G1f
 * touches anything.
 *
 * WHY BEHAVIOURAL AND NOT STRUCTURAL
 * ----------------------------------
 * `TenantOfferCitiesMirrorTest::test_hire_trait_semantics_are_unchanged()`
 * already pins the trait structurally, by asserting the source contains
 * `empty(` and does not contain `array_key_exists`. A grep proves the code was
 * not edited; it does not prove what the code DOES. The parity evidence L11
 * requires for consolidation is behavioural, so each of the four measured sites
 * gets a test that exercises it through real EAV storage.
 *
 * THE SITES (v1.2 §4.2, measured line numbers confirmed at 73f32fe62)
 * ------------------------------------------------------------------
 *   line  48 — load:  legacy `cities` meta merged when `empty($ldna['cities'])`
 *   line  71 — load:  discrete `$state` seeded when `empty($ldna['state'])`
 *   line  77 — load:  discrete `$counties` seeded when `empty($ldna['counties'])`
 *   line 103 — save:  `$counties` refreshed only when `!empty($ldna['counties'])`
 *
 * A fifth site is characterised alongside them: line 100, the `state` analogue of
 * line 103, which uses `trim((string) …) !== ''` rather than `empty()`. §4.2 names
 * only four sites, but line 100 produces the identical class of defect for
 * `state`, and a consolidation that converted the four named sites while leaving
 * line 100 would leave the defect half-fixed. Recorded here so the count in the
 * governing document can be corrected to five.
 *
 * DELIBERATELY NOT CONVERTED — lines 58, 72, 78
 * --------------------------------------------
 * Those three `!empty()` calls guard LEGACY MIRROR INPUT and LOCAL COMPONENT
 * STATE, not blob presence. They ask "did the legacy row contain anything usable"
 * rather than "was this dimension authored". Converting them would change
 * unrelated behaviour, so no test here asserts anything about them, and G1f must
 * leave them alone.
 *
 * VEHICLE
 * -------
 * The thin-host pattern established by `SearchAreasPersistenceCharacterisationTest`:
 * a bare object declaring only the three props the trait's host contract requires,
 * exercised against a real `TenantAgentAuction` and its real meta table.
 * `TenantAgentAuction` is used because it is the only host model with a factory.
 * Booting a full Livewire component would characterise that component's
 * validation instead of the trait.
 *
 * SCOPE BOUNDARY
 * --------------
 * PHP and database only. The blob is produced in the browser by
 * `window.ldnaSerialize`; nothing here executes it.
 */
class G1aTraitPresenceSemanticsCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private function auction(array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create();

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function host(string $state = '', array $counties = [], array $cities = []): G1aTraitHost
    {
        $host           = new G1aTraitHost();
        $host->state    = $state;
        $host->counties = $counties;
        $host->cities   = $cities;

        return $host;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SITE 48 · load — legacy `cities` merge
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · a cleared `cities` is resurrected from the mirror.
     *
     * The blob says `"cities": []` — under §5.2 that is "explicitly cleared" and
     * no fallback may apply. `empty([])` is true, so the trait consults the legacy
     * mirror anyway and the deleted cities come back.
     *
     * This is the exact behaviour the two Tenant Offer components diverge from the
     * trait to avoid. Recorded, not fixed.
     */
    public function test_site48_cleared_cities_are_resurrected_from_the_legacy_mirror(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(
            ['Tampa', 'Miami'],
            $host->existingLocationDna['cities'],
            'CHARACTERISATION: the trait resurrects an intentionally cleared cities list. '
            .'Contradicts §5.2. G1f must change this; today it is the behaviour.'
        );
    }

    /** CONTROL · a populated blob `cities` is authoritative; the mirror is ignored. */
    public function test_site48_populated_blob_cities_win_over_the_mirror(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(['Orlando'], $host->existingLocationDna['cities']);
    }

    /**
     * CONTROL · an ABSENT `cities` key legitimately falls back to the mirror.
     *
     * This is the case the fallback exists for, and it must keep working after
     * G1f. Asserted next to the defect so the boundary is visible as a boundary:
     * absent and present-but-empty are two states that today produce one outcome.
     */
    public function test_site48_absent_cities_key_legitimately_falls_back(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(['Tampa'], $host->existingLocationDna['cities']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SITE 71 · load — discrete `state` seeding
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · a cleared `state` is overwritten by the discrete prop.
     *
     * `empty("")` is true, so a blob that explicitly cleared `state` has the
     * component's discrete `$state` written over it during load.
     */
    public function test_site71_cleared_state_is_overwritten_by_the_discrete_prop(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['state' => '']),
        ]);

        $host = $this->host(state: 'Georgia');
        $host->callLoad($auction);

        $this->assertSame(
            'Georgia',
            $host->existingLocationDna['state'],
            'CHARACTERISATION: a cleared state is replaced by the discrete prop. Contradicts §5.2.'
        );
    }

    /** CONTROL · a populated blob `state` is not overwritten. */
    public function test_site71_populated_blob_state_is_preserved(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
        ]);

        $host = $this->host(state: 'Georgia');
        $host->callLoad($auction);

        $this->assertSame('FL', $host->existingLocationDna['state']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SITE 77 · load — discrete `counties` seeding
    // ═════════════════════════════════════════════════════════════════════════

    /** CHARACTERISED DEFECT · a cleared `counties` is overwritten by the discrete prop. */
    public function test_site77_cleared_counties_are_overwritten_by_the_discrete_prop(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['counties' => []]),
        ]);

        $host = $this->host(counties: ['Cobb County, GA']);
        $host->callLoad($auction);

        $this->assertSame(
            ['Cobb County, GA'],
            $host->existingLocationDna['counties'],
            'CHARACTERISATION: cleared counties are replaced by the discrete prop. Contradicts §5.2.'
        );
    }

    /** CONTROL · a populated blob `counties` is not overwritten. */
    public function test_site77_populated_blob_counties_are_preserved(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['counties' => ['Pinellas']]),
        ]);

        $host = $this->host(counties: ['Cobb County, GA']);
        $host->callLoad($auction);

        $this->assertSame(['Pinellas'], $host->existingLocationDna['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SITE 103 · save — `counties` write-back
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · clearing `counties` leaves a stale discrete mirror.
     *
     * `hydrateDiscreteLocationFromBlob()` refreshes `$counties` only when the blob
     * value is non-empty. A cleared blob therefore leaves the previous `$counties`
     * in place, and `saveSearchAreas()` writes that stale value straight into the
     * `counties` meta key — the key Ask AI, matching, filtering and public display
     * read. The user's clear never reaches any consumer.
     */
    public function test_site103_clearing_counties_writes_a_stale_discrete_mirror(): void
    {
        $auction = $this->auction();

        $host                                 = $this->host(counties: ['Pinellas']);
        $host->location_dna_preferences_json  = json_encode(['counties' => []]);
        $host->callSave($auction);

        $this->assertSame(
            '["Pinellas"]',
            $this->reread($auction)->info('counties'),
            'CHARACTERISATION: a cleared counties list mirrors as the previous value, not as []. '
            .'The clear is invisible to every consumer of the discrete key.'
        );
    }

    /** CONTROL · a populated `counties` does refresh the discrete mirror. */
    public function test_site103_populated_counties_refresh_the_discrete_mirror(): void
    {
        $auction = $this->auction();

        $host                                = $this->host(counties: ['Stale']);
        $host->location_dna_preferences_json = json_encode(['counties' => ['Pinellas', 'Hillsborough']]);
        $host->callSave($auction);

        $this->assertSame(
            '["Pinellas","Hillsborough"]',
            $this->reread($auction)->info('counties')
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SITE 100 · save — the `state` analogue, unnamed in §4.2
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · clearing `state` leaves a stale discrete mirror.
     *
     * Line 100 guards on `trim((string) ($ldna['state'] ?? '')) !== ''` rather
     * than `empty()`, so §4.2's list of four sites does not name it. The effect is
     * identical to site 103: a cleared `state` never reaches the discrete key.
     *
     * G1f must convert five sites, not four.
     */
    public function test_site100_clearing_state_writes_a_stale_discrete_mirror(): void
    {
        $auction = $this->auction();

        $host                                = $this->host(state: 'Georgia');
        $host->location_dna_preferences_json = json_encode(['state' => '']);
        $host->callSave($auction);

        $this->assertSame(
            'Georgia',
            $this->reread($auction)->info('state'),
            'CHARACTERISATION: a cleared state mirrors as the previous value. '
            .'Same defect class as site 103; not named in §4.2.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE INCONSISTENCY THE SITES PRODUCE TOGETHER
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · within ONE save, a cleared `cities` mirrors as `[]`
     * while a cleared `counties` and `state` keep stale values.
     *
     * `cities` is mirrored at line 130 by a direct `$ldnaDecoded['cities'] ?? []`,
     * with no non-empty guard, so it honours a clear. `counties` and `state` go
     * through the guarded hydration and do not. The same user action — clearing
     * every dimension in the widget — is therefore recorded three different ways
     * in one write.
     *
     * This is the single most useful fact in this file for G1f: consolidation
     * cannot be a mechanical `empty()` → `array_key_exists()` substitution,
     * because the three dimensions do not currently share one code path.
     */
    public function test_one_save_records_cleared_dimensions_three_different_ways(): void
    {
        $auction = $this->auction();

        $host                                = $this->host(state: 'Georgia', counties: ['Pinellas']);
        $host->location_dna_preferences_json = json_encode([
            'cities'   => [],
            'counties' => [],
            'state'    => '',
        ]);
        $host->callSave($auction);

        $fresh = $this->reread($auction);

        $this->assertSame('[]', $fresh->info('cities'), 'cities honours the clear');
        $this->assertSame('["Pinellas"]', $fresh->info('counties'), 'counties ignores the clear');
        $this->assertSame('Georgia', $fresh->info('state'), 'state ignores the clear');
    }

    /**
     * CHARACTERISED · why the site-48 defect is LATENT rather than constant.
     *
     * Running the whole cycle through the trait shows the clear surviving — and
     * the reason matters more than the result. `cities` is the one dimension whose
     * mirror write honours a clear (line 130, no non-empty guard), so step 3 sets
     * the mirror to `[]` and step 4's `empty()` branch finds nothing to resurrect.
     * The defect is masked by a correct write on an adjacent line.
     *
     * So site 48 only bites when the mirror is STALE — when something wrote the
     * mirror without going through this save path, or wrote it before the clear.
     * `test_site48_cleared_cities_are_resurrected_from_the_legacy_mirror()`
     * constructs exactly that state and shows the resurrection.
     *
     * Recorded because a consolidation that "fixes" site 48 while regressing the
     * line-130 mirror write would turn a latent defect into a constant one, and
     * this cycle test is what would catch it.
     */
    public function test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared(): void
    {
        // 1 · legacy record: mirror only, no blob.
        $auction = $this->auction(['cities' => json_encode(['Tampa'])]);

        // 2 · load recovers the legacy cities.
        $first = $this->host();
        $first->callLoad($auction);
        $this->assertSame(['Tampa'], $first->existingLocationDna['cities']);

        // 3 · the user clears every city; the bridge carries `[]` back. The mirror
        //     is cleared too — this is the step that masks the load-side defect.
        $saver                                = $this->host();
        $saver->location_dna_preferences_json = json_encode(['cities' => []]);
        $saver->callSave($auction);
        $this->assertSame('[]', $this->reread($auction)->info('cities'));

        // 4 · reload — the clear holds, because step 3 left the mirror empty.
        $second = $this->host();
        $second->callLoad($this->reread($auction));

        $this->assertSame(
            [],
            $second->existingLocationDna['cities'],
            'The clear holds ONLY because the cities mirror was cleared in step 3. '
            .'With a stale mirror the same load resurrects — see the site-48 test.'
        );
    }
}

/**
 * Thin host exercising the real trait against a real model.
 *
 * Declares exactly the three props the trait's documented host contract requires
 * and nothing else, so the characterisation covers the trait rather than any one
 * component's surrounding behaviour.
 */
class G1aTraitHost
{
    use HasSearchAreas;

    public $state    = '';
    public $counties = [];
    public $cities   = [];

    public function callLoad($auction): void
    {
        $this->loadSearchAreas($auction);
    }

    public function callSave($auction): void
    {
        $this->saveSearchAreas($auction);
    }
}
