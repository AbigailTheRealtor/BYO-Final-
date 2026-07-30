<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\Concerns\HasSearchAreas;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * G1a — cross-dimension preservation, the unmounted editor, and save-with-no-changes.
 *
 * WHAT THIS COVERS
 * ----------------
 * Three §16.2 / §16.6 obligations that no existing suite asserts:
 *
 *   1. "changing `cities` alters nothing else, with the editor mounted AND
 *      unmounted" (§16.2, Merge and patch behaviour)
 *   2. "save-with-no-changes must change nothing" (§16.6, Persistence round trips)
 *   3. token stability across a no-op save (§16.6) — characterised as byte
 *      stability, since no revision token exists until G1c
 *
 * WHY THE UNMOUNTED CASE IS THE IMPORTANT ONE
 * -------------------------------------------
 * The unmounted editor is the G0 hazard: the commit that introduced the interim
 * guard (`387a971d8`) exists because an unhydrated editor caused geometry to be
 * serialised as empty and destroyed on the next save. That guard is JavaScript and
 * is covered only structurally, because this project has no JavaScript test runner.
 *
 * What is testable in PHP is the server's behaviour when the bridge delivers
 * nothing — and the answer, recorded below, is that the server has no defence of
 * its own. The guard is the only thing standing between an unmounted editor and
 * data loss, and it lives entirely on the client.
 *
 * That asymmetry is the strongest available argument for G1's server-authoritative
 * patch merging, and it belongs in the characterisation record rather than in prose.
 *
 * SCOPE BOUNDARY
 * --------------
 * PHP and database only. Nothing here executes `window.ldnaSerialize` or proves
 * the bridge syncs. These tests characterise the server's side of that bridge.
 */
class G1aCrossDimensionPreservationCharacterisationTest extends TestCase
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

    private function host(): G1aPreservationHost
    {
        return new G1aPreservationHost();
    }

    /** A blob populated across every Search Envelope dimension. */
    private function fullBlob(): array
    {
        return [
            'cities'            => ['Tampa'],
            'zip_codes'         => ['33602'],
            'counties'          => ['Hillsborough'],
            'state'             => 'FL',
            'polygons'          => [[
                'label' => 'Drawn area 1',
                'path'  => [
                    ['lat' => 27.9506, 'lng' => -82.4572],
                    ['lat' => 27.9606, 'lng' => -82.4472],
                    ['lat' => 27.9406, 'lng' => -82.4372],
                ],
            ]],
            'radius_searches'   => [
                ['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 3.5, 'address' => '400 N Ashley Dr'],
            ],
            'flexible_location' => true,
            'location_notes'    => 'Walkable to the river.',
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · cross-dimension preservation, editor MOUNTED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * With the editor mounted, changing `cities` leaves every other dimension
     * byte-identical.
     *
     * "Mounted" on the server means the bridge delivered a complete blob. Because
     * §6.2 has array dimensions submit the whole dimension, a mounted save is a
     * complete statement of intent and preservation is a property of the payload
     * rather than of any merge logic. That is exactly why this test passes today
     * and why the unmounted case below does not have the same protection.
     */
    public function test_mounted_editor_changing_cities_preserves_every_other_dimension(): void
    {
        $auction  = $this->auction();
        $original = $this->fullBlob();

        $host                                = $this->host();
        $host->location_dna_preferences_json = json_encode($original);
        $host->callSave($auction);

        // The user edits only cities; the bridge returns the whole blob.
        $edited           = $original;
        $edited['cities'] = ['Tampa', 'St. Petersburg'];

        $second                                = $this->host();
        $second->location_dna_preferences_json = json_encode($edited);
        $second->callSave($auction);

        $stored = json_decode((string) $this->reread($auction)->info('location_dna_preferences'), true);

        $this->assertSame(['Tampa', 'St. Petersburg'], $stored['cities'], 'the edited dimension changed');

        foreach (['zip_codes', 'counties', 'state', 'polygons', 'radius_searches', 'flexible_location', 'location_notes'] as $dimension) {
            $this->assertSame(
                $original[$dimension],
                $stored[$dimension],
                "Dimension `{$dimension}` must be untouched by a cities-only edit."
            );
        }
    }

    /** Geometry specifically survives a cities-only edit with full fidelity. */
    public function test_mounted_editor_preserves_polygon_vertices_exactly(): void
    {
        $auction  = $this->auction();
        $original = $this->fullBlob();

        $host                                = $this->host();
        $host->location_dna_preferences_json = json_encode($original);
        $host->callSave($auction);

        $edited           = $original;
        $edited['cities'] = ['Clearwater'];

        $second                                = $this->host();
        $second->location_dna_preferences_json = json_encode($edited);
        $second->callSave($auction);

        $stored = json_decode((string) $this->reread($auction)->info('location_dna_preferences'), true);

        $this->assertCount(3, $stored['polygons'][0]['path']);
        $this->assertSame(27.9506, $stored['polygons'][0]['path'][0]['lat']);
        $this->assertSame(3.5, $stored['radius_searches'][0]['radius_miles']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · the UNMOUNTED editor — the G0 hazard, server side
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · an unmounted editor destroys all saved geometry.
     *
     * When the editor never hydrates, the bridge delivers an empty string. The
     * server writes it straight over the authoritative blob: every polygon, radius
     * search, city, county and note is gone in one save, and the `cities` mirror is
     * emptied alongside.
     *
     * There is no server-side check that the incoming payload is parseable, no
     * comparison against what is already stored, and no distinction between "the
     * user cleared everything" and "the editor never loaded" — the two cases L5
     * says must never be conflated.
     *
     * The G0 guard prevents this from being reachable through the real UI. The
     * guard is client-side JavaScript. This test records what the server does when
     * the guard is bypassed, absent, or defeated by a browser that never ran it.
     */
    public function test_unmounted_editor_empty_payload_destroys_all_saved_geometry(): void
    {
        $auction = $this->auction();

        $host                                = $this->host();
        $host->location_dna_preferences_json = json_encode($this->fullBlob());
        $host->callSave($auction);

        $this->assertStringContainsString(
            'Drawn area 1',
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'precondition: geometry is stored'
        );

        // The editor never hydrated; the bridge carries an empty string.
        $unmounted                                = $this->host();
        $unmounted->location_dna_preferences_json = '';
        $unmounted->callSave($auction);

        $fresh = $this->reread($auction);

        $this->assertSame(
            '',
            (string) $fresh->info('location_dna_preferences'),
            'CHARACTERISATION: the authoritative blob was overwritten with an empty string. '
            .'All geometry is gone. The server has no defence of its own.'
        );

        $this->assertSame(
            '[]',
            $fresh->info('cities'),
            'CHARACTERISATION: the discrete mirror was emptied in the same save.'
        );
    }

    /**
     * CHARACTERISED DEFECT · the server cannot distinguish an unmounted editor
     * from a deliberate clear-everything.
     *
     * The two payloads differ — `''` versus a well-formed blob of canonical empties
     * — but the stored outcome is observationally the same for every consumer that
     * decodes the blob and reads a dimension. This is the ambiguity §6.2 removes by
     * making `clear` an explicit operation and refusing "absent from payload" as an
     * instruction.
     */
    public function test_unmounted_editor_and_deliberate_clear_are_indistinguishable_to_consumers(): void
    {
        $unmountedAuction = $this->auction();
        $clearedAuction   = $this->auction();

        foreach ([$unmountedAuction, $clearedAuction] as $auction) {
            $seed                                = $this->host();
            $seed->location_dna_preferences_json = json_encode($this->fullBlob());
            $seed->callSave($auction);
        }

        $unmounted                                = $this->host();
        $unmounted->location_dna_preferences_json = '';
        $unmounted->callSave($unmountedAuction);

        $cleared                                = $this->host();
        $cleared->location_dna_preferences_json = json_encode([
            'cities'          => [],
            'polygons'        => [],
            'radius_searches' => [],
        ]);
        $cleared->callSave($clearedAuction);

        $unmountedDecoded = json_decode((string) $this->reread($unmountedAuction)->info('location_dna_preferences'), true);
        $clearedDecoded   = json_decode((string) $this->reread($clearedAuction)->info('location_dna_preferences'), true);

        // Every consumer asking "what polygons does this record have" gets nothing
        // in both cases, despite one being data loss and the other user intent.
        $this->assertSame([], $unmountedDecoded['polygons'] ?? []);
        $this->assertSame([], $clearedDecoded['polygons'] ?? []);

        $this->assertSame(
            '[]',
            $this->reread($unmountedAuction)->info('cities'),
            'CHARACTERISATION: accidental loss and deliberate clear converge on one observable state.'
        );
        $this->assertSame('[]', $this->reread($clearedAuction)->info('cities'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · save-with-no-changes
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Save-with-no-changes leaves the blob byte-identical.
     *
     * §16.6 requires this and it holds for the blob, for the reason established in
     * the S3 suite: the raw stored string is what round-trips, not a re-encoding of
     * the decode. Byte stability here is therefore a property of pass-through, and
     * G1c's serializer must preserve it deliberately rather than inherit it.
     */
    public function test_save_with_no_changes_leaves_the_blob_byte_identical(): void
    {
        $encoded = json_encode($this->fullBlob());
        $auction = $this->auction(['location_dna_preferences' => $encoded]);

        $host = $this->host();
        $host->callLoad($auction);
        $host->callSave($auction);

        $this->assertSame(
            $encoded,
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'A no-op save must not alter the blob.'
        );
    }

    /**
     * CHARACTERISED DEFECT · a no-op save is NOT side-effect-free for the mirrors
     * when the record is legacy.
     *
     * Loading a mirror-only legacy record and saving it without any user edit
     * rewrites the discrete keys from a blob that never contained them. `counties`
     * and `state` are written from the host's props, and `cities` is written as
     * `[]` from the absent blob key — so the legacy `cities` mirror that was the
     * record's only source of truth is destroyed by a save the user never made a
     * change in.
     *
     * This is the sharpest available demonstration that the mirror contract has no
     * single owner today, and it is the case G1's server-authoritative merge has to
     * make impossible.
     */
    public function test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror(): void
    {
        $auction = $this->auction(['cities' => json_encode(['Seminole, FL', 'Largo, FL'])]);

        $host = $this->host();
        $host->callLoad($auction);

        // The load recovered the legacy cities into the prefill array.
        $this->assertSame(['Seminole, FL', 'Largo, FL'], $host->existingLocationDna['cities']);

        // No user edit occurs. The component simply saves.
        $host->callSave($auction);

        $this->assertSame(
            '[]',
            $this->reread($auction)->info('cities'),
            'CHARACTERISATION: a no-op save destroyed the legacy cities mirror. '
            .'The prefill held the values; the persisted payload never did.'
        );
    }
}

/**
 * Thin host exercising the real trait against a real model. Declares exactly the
 * three props the trait's host contract requires.
 */
class G1aPreservationHost
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
