<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f-4 — the Tenant Offer pair, migrated to the canonical writer.
 *
 * WHAT MAKES THIS PAIR DIFFERENT FROM G1f-3's
 * -------------------------------------------
 * Two things. First, Tenant Offer manages a FOURTH legacy mirror, `zipCodes`, which the Buyer
 * family has never written — it reaches the writer through the surface-scoped opt-in added by the
 * G1f-4 prerequisite, so migrating it neither drops the key nor forces it on Buyer.
 *
 * Second, the edit copy's write path was spread across THREE sites in `update()`: `counties` /
 * `state`, then `zipCodes` ten lines later, then the canonical blob and `cities` more than six
 * hundred lines further down. They are consolidated at the first of those sites, which is inside
 * the transaction `update()` already opened — the transaction is not widened, and unrelated
 * metadata and file handling stay outside the call.
 *
 * Both copies are driven through their REAL save entry points.
 */
class G1f4TenantOfferMigrationTest extends TestCase
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

    /** Drive the real create-flow persistence entry point. */
    private function saveCreate(TenantAgentAuction $auction, ?string $payload): TenantAgentAuction
    {
        $component = new TenantOfferListing();

        if ($payload !== null) {
            $component->location_dna_preferences_json = $payload;
        }

        $method = new ReflectionMethod(TenantOfferListing::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        return $this->reread($auction);
    }

    /**
     * Drive the edit copy's Location DNA persistence.
     *
     * `update()` carries validation, file handling and several hundred unrelated writes, so the
     * consolidated seam is invoked directly — it is the entire Location DNA write path for that
     * method, which is exactly what the boundary guard pins.
     */
    private function saveEdit(TenantAgentAuction $auction, ?string $payload): TenantAgentAuction
    {
        $component = new TenantOfferListingEdit();

        if ($payload !== null) {
            $component->location_dna_preferences_json = $payload;
        }

        $method = new ReflectionMethod(TenantOfferListingEdit::class, 'persistLocationDna');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        return $this->reread($auction);
    }

    /** @return array<string, callable> both copies, so every rule is proven on each independently */
    private function flows(): array
    {
        return [
            'create' => fn (TenantAgentAuction $a, ?string $p) => $this->saveCreate($a, $p),
            'edit'   => fn (TenantAgentAuction $a, ?string $p) => $this->saveEdit($a, $p),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // THE FOUR MANAGED MIRRORS, THROUGH THE WRITER
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_both_flows_write_all_four_managed_mirrors_from_canonical_state(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save($this->auction(), json_encode([
                'cities'    => ['Tampa'],
                'counties'  => ['Hillsborough'],
                'state'     => 'FL',
                'zip_codes' => ['33708'],
            ]));

            $this->assertSame('["Tampa"]', $fresh->info('cities'), "{$name}: cities");
            $this->assertSame('["Hillsborough"]', $fresh->info('counties'), "{$name}: counties");
            $this->assertSame('FL', $fresh->info('state'), "{$name}: state (RAW, not JSON)");
            $this->assertSame('["33708"]', $fresh->info('zipCodes'), "{$name}: zipCodes");
        }
    }

    /** The canonical blob is written, once, by the writer. */
    public function test_both_flows_write_the_canonical_document(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save($this->auction(), json_encode(['cities' => ['Tampa']]));

            $decoded = json_decode((string) $fresh->info('location_dna_preferences'), true);

            $this->assertIsArray($decoded, "{$name}: canonical document present");
            $this->assertSame(['Tampa'], $decoded['cities'], "{$name}: canonical cities");
        }
    }

    /** Exactly one row per managed key — no duplicate mirror writes survive. */
    public function test_each_managed_key_is_written_exactly_once(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save($this->auction(), json_encode([
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'],
                'state'  => 'FL', 'zip_codes' => ['33708'],
            ]));

            foreach (['cities', 'counties', 'state', 'zipCodes', 'location_dna_preferences'] as $key) {
                $this->assertSame(
                    1,
                    $fresh->meta->where('meta_key', $key)->count(),
                    "{$name}: `{$key}` must be written exactly once"
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // PRESENT-VERSUS-CLEARED · the semantics Tenant Offer is the baseline for
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Present-but-empty is an explicit clear, for every managed dimension including ZIPs. */
    public function test_present_empty_dimensions_clear_their_mirrors(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save(
                $this->auction([
                    'cities'   => json_encode(['Tampa']),
                    'counties' => json_encode(['Hillsborough']),
                    'state'    => 'FL',
                    'zipCodes' => json_encode(['33708']),
                ]),
                json_encode(['cities' => [], 'counties' => [], 'state' => '', 'zip_codes' => []])
            );

            $this->assertSame('[]', $fresh->info('cities'), "{$name}: cleared cities");
            $this->assertSame('[]', $fresh->info('counties'), "{$name}: cleared counties");
            $this->assertSame('', $fresh->info('state'), "{$name}: cleared state");
            $this->assertSame('[]', $fresh->info('zipCodes'), "{$name}: cleared zipCodes");
        }
    }

    /** A stale mirror cannot resurrect a cleared value — the projection never reads mirrors. */
    public function test_stale_mirrors_do_not_resurrect_cleared_values(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save(
                $this->auction([
                    'cities'   => json_encode(['Tampa', 'Miami']),
                    'zipCodes' => json_encode(['33708', '33710']),
                ]),
                json_encode(['cities' => [], 'zip_codes' => []])
            );

            $this->assertSame('[]', $fresh->info('cities'), "{$name}: cities stayed cleared");
            $this->assertSame('[]', $fresh->info('zipCodes'), "{$name}: zipCodes stayed cleared");
        }
    }

    /** Absent is preserve: an unstated dimension leaves its legacy mirror untouched. */
    public function test_absent_dimensions_preserve_existing_legacy_mirrors(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save(
                $this->auction([
                    'counties' => json_encode(['Hillsborough']),
                    'zipCodes' => json_encode(['33708']),
                ]),
                json_encode(['cities' => ['Tampa']])
            );

            $this->assertSame('["Tampa"]', $fresh->info('cities'), "{$name}: stated dimension written");
            $this->assertSame(
                json_encode(['Hillsborough']),
                $fresh->info('counties'),
                "{$name}: absent counties preserved"
            );
            $this->assertSame(
                json_encode(['33708']),
                $fresh->info('zipCodes'),
                "{$name}: absent zipCodes preserved"
            );
        }
    }

    /** Present-empty and absent remain distinguishable — the whole point of the contract. */
    public function test_present_empty_and_absent_remain_distinct(): void
    {
        foreach ($this->flows() as $name => $save) {
            $cleared = $save(
                $this->auction(['zipCodes' => json_encode(['33708'])]),
                json_encode(['zip_codes' => []])
            );
            $absent = $save(
                $this->auction(['zipCodes' => json_encode(['33708'])]),
                json_encode(['cities' => ['Tampa']])
            );

            $this->assertSame('[]', $cleared->info('zipCodes'), "{$name}: present-empty clears");
            $this->assertSame(
                json_encode(['33708']),
                $absent->info('zipCodes'),
                "{$name}: absent preserves"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // NO-OP · a save that states nothing must not create or destroy anything
    // ─────────────────────────────────────────────────────────────────────────────────

    /** An unmounted editor states nothing, so a legacy-only record keeps its mirrors and gains no blob. */
    public function test_a_no_command_save_leaves_a_legacy_only_record_untouched(): void
    {
        foreach (['', 'null-ish' => null] as $payload) {
            foreach ($this->flows() as $name => $save) {
                $auction = $this->auction([
                    'cities'   => json_encode(['Tampa']),
                    'zipCodes' => json_encode(['33708']),
                ]);

                $fresh = $save($auction, is_string($payload) ? $payload : null);

                $this->assertSame(json_encode(['Tampa']), $fresh->info('cities'), "{$name}: cities kept");
                $this->assertSame(json_encode(['33708']), $fresh->info('zipCodes'), "{$name}: zips kept");
                $this->assertFalse(
                    $fresh->meta->contains(fn ($m) => $m->meta_key === 'location_dna_preferences'),
                    "{$name}: no canonical blob may be created by a no-command save"
                );
            }
        }
    }

    /** A semantic no-op writes nothing at all — identical values re-submitted. */
    public function test_a_semantic_no_op_preserves_bytes(): void
    {
        foreach ($this->flows() as $name => $save) {
            $auction = $this->auction();
            $payload = json_encode(['cities' => ['Tampa'], 'state' => 'FL']);

            $first = $save($auction, $payload);
            $bytes = $first->info('location_dna_preferences');

            $second = $save($auction, $payload);

            $this->assertSame(
                $bytes,
                $second->info('location_dna_preferences'),
                "{$name}: a semantic no-op must not rewrite the canonical bytes"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // NO PROMOTION · a legacy mirror is never promoted into canonical state
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_a_legacy_mirror_is_never_promoted_into_the_canonical_document(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save(
                $this->auction([
                    'cities'   => json_encode(['Tampa']),
                    'zipCodes' => json_encode(['33708']),
                ]),
                json_encode(['counties' => ['Hillsborough']])
            );

            $decoded = json_decode((string) $fresh->info('location_dna_preferences'), true);

            $this->assertArrayNotHasKey('cities', $decoded, "{$name}: legacy cities not promoted");
            $this->assertArrayNotHasKey('zip_codes', $decoded, "{$name}: legacy zips not promoted");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // SCHEMA
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_first_semantic_write_stamps_schema_version_2(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save($this->auction(), json_encode(['cities' => ['Tampa']]));

            $decoded = json_decode((string) $fresh->info('location_dna_preferences'), true);

            $this->assertSame(2, $decoded['schema_version'] ?? null, "{$name}: schema_version");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // BOUNDARIES
    // ─────────────────────────────────────────────────────────────────────────────────

    /** No mirror key outside the four managed ones is ever emitted. */
    public function test_no_unmanaged_mirror_key_is_introduced(): void
    {
        foreach ($this->flows() as $name => $save) {
            $fresh = $save($this->auction(), json_encode([
                'cities' => ['Tampa'], 'zip_codes' => ['33708'],
                'polygons' => [], 'location_notes' => 'x', 'flexible_location' => true,
            ]));

            foreach (['states', 'zip_codes', 'neighborhoods', 'polygons', 'radius_searches'] as $forbidden) {
                $this->assertFalse(
                    $fresh->meta->contains(fn ($m) => $m->meta_key === $forbidden),
                    "{$name}: `{$forbidden}` must never become a legacy mirror key"
                );
            }
        }
    }
}
