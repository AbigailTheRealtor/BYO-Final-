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

}

/****
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

}
