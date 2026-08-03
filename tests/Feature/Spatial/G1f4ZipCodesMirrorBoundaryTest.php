<?php

namespace Tests\Feature\Spatial;

use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use App\Services\LocationDna\Persistence\LocationDnaWritableRecord;
use App\Services\LocationDna\Persistence\OwnerPrivateLocationDnaWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * G1f-4 prerequisite — the opt-in `zipCodes` mirror at the REAL persistence boundary.
 *
 * The pure projection is proven by {@see \Tests\Unit\Services\LocationDna\Persistence\G1f4ZipCodesMirrorProjectionTest}.
 * This suite proves the two things a pure function cannot: that the meta row actually lands (or
 * actually does not), and that a failing ZIP mirror write takes the canonical write down with it.
 *
 * No Livewire component is touched by this increment — the writer seam is driven directly.
 */
class G1f4ZipCodesMirrorBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private const OPTED_IN = ['cities', 'counties', 'state', 'zipCodes'];

    private function tenantAuction(User $owner, array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create(['user_id' => $owner->id]);

        foreach (array_merge(['user_type' => 'tenant'], $meta) as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // COMPATIBILITY · the default seam must behave exactly as G1f-1 / G1f-2 / G1f-3 expect
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * The Buyer regression this design exists to prevent, proven at the save path.
     *
     * The payload carries `zip_codes` PRESENT and empty, exactly as every real Buyer blob does.
     * The default writer must still write no `zipCodes` meta whatsoever.
     */
    public function test_the_default_writer_never_writes_a_zipcodes_meta(): void
    {
        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'buyer-compat',
            'is_draft' => true, 'is_approved' => true, 'is_sold' => false,
        ]);
        $auction->save();

        (new OwnerPrivateLocationDnaWriter())->persistFromEditorPayload(
            $auction,
            json_encode(['cities' => ['Tampa'], 'zip_codes' => [], 'state' => 'FL']),
        );

        $fresh = BuyerAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertFalse(
            $fresh->meta->contains(fn ($m) => $m->meta_key === 'zipCodes'),
            'a default-seam workflow must not begin emitting a zipCodes legacy mirror'
        );
        // …while the managed three are written as before.
        $this->assertSame('["Tampa"]', $fresh->info('cities'));
        $this->assertSame('FL', $fresh->info('state'));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // OPT-IN · the new capability G1f-4 consumes
    // ─────────────────────────────────────────────────────────────────────────────────

    /** An opted-in surface writes `zipCodes` through the boundary, in the existing format. */
    public function test_an_opted_in_surface_writes_zipcodes_through_the_boundary(): void
    {
        $owner   = User::factory()->create(['user_type' => 'tenant']);
        $auction = $this->tenantAuction($owner);

        OwnerPrivateLocationDnaWriter::managingMirrors(self::OPTED_IN)
            ->persistFromEditorPayload($auction, json_encode([
                'cities'    => ['Tampa'],
                'zip_codes' => ['33708', '33710'],
            ]));

        $fresh = TenantAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertSame('["33708","33710"]', $fresh->info('zipCodes'));
        // The format existing readers already parse.
        $this->assertSame(['33708', '33710'], json_decode($fresh->info('zipCodes'), true));
    }

    /** A cleared canonical ZIP set clears the mirror rather than leaving it stale. */
    public function test_an_opted_in_surface_clears_a_stale_zipcodes_mirror(): void
    {
        $owner   = User::factory()->create(['user_type' => 'tenant']);
        $auction = $this->tenantAuction($owner, ['zipCodes' => '["33708"]']);

        OwnerPrivateLocationDnaWriter::managingMirrors(self::OPTED_IN)
            ->persistFromEditorPayload($auction, json_encode([
                'cities'    => ['Tampa'],
                'zip_codes' => [],
            ]));

        $this->assertSame(
            '[]',
            TenantAgentAuction::with('meta')->findOrFail($auction->id)->info('zipCodes')
        );
    }

    /**
     * Losslessness: a save that never mentions ZIPs must leave an existing legacy value alone.
     *
     * This is why migrating cannot discard data — absent states nothing, so nothing is written.
     */
    public function test_absent_zip_codes_leaves_an_existing_legacy_value_untouched(): void
    {
        $owner   = User::factory()->create(['user_type' => 'tenant']);
        $auction = $this->tenantAuction($owner, ['zipCodes' => '["33708"]']);

        OwnerPrivateLocationDnaWriter::managingMirrors(self::OPTED_IN)
            ->persistFromEditorPayload($auction, json_encode(['cities' => ['Tampa']]));

        $this->assertSame(
            '["33708"]',
            TenantAgentAuction::with('meta')->findOrFail($auction->id)->info('zipCodes'),
            'an absent dimension must never clear a legacy-only mirror'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // TRANSACTION · a failing ZIP mirror takes the whole write down
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_a_failing_zipcodes_mirror_write_rolls_back_canonical_and_earlier_mirrors(): void
    {
        $owner   = User::factory()->create(['user_type' => 'tenant']);
        $auction = $this->tenantAuction($owner);

        $record = new FailingZipRecord($auction);

        try {
            (new \App\Services\LocationDna\Persistence\LocationDnaPersistenceService(
                mirrors: new LegacyMirrorProjection(self::OPTED_IN),
            ))->apply(
                $record,
                (new \App\Services\LocationDna\Persistence\LocationDnaCommandBuilder())
                    ->fromEditorPayload(json_encode([
                        'cities'    => ['Tampa'],
                        'zip_codes' => ['33708'],
                    ])),
                (new \App\Services\LocationDna\Capability\LocationDnaCapabilityResolver())->resolve(
                    \App\Services\LocationDna\Capability\LocationDnaAccessContext::of(
                        \App\Services\LocationDna\Capability\LocationDnaSurface::OwnerPrivateEdit,
                        \App\Services\LocationDna\Capability\LocationDnaViewerRelationship::Owner,
                        \App\Services\LocationDna\Capability\LocationDnaPurpose::Edit,
                        authenticated: true,
                    )
                ),
                \App\Services\LocationDna\Provenance\ProvenanceActor::ExplicitOwner,
            );
            $this->fail('the induced zipCodes mirror failure must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('induced zipCodes mirror failure', $e->getMessage());
        }

        // The canonical write and the cities mirror were both already ISSUED when zipCodes threw.
        $fresh = TenantAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertFalse(
            $fresh->meta->contains(fn ($m) => $m->meta_key === 'location_dna_preferences'),
            'the canonical write must have rolled back'
        );
        $this->assertFalse(
            $fresh->meta->contains(fn ($m) => $m->meta_key === 'cities'),
            'the earlier cities mirror must have rolled back too — no partial Location DNA state'
        );
    }
}

/**
 * Writes for real, but throws when the `zipCodes` mirror is reached.
 *
 * `zipCodes` is emitted LAST, so canonical and `cities` have already been issued when it throws —
 * which is exactly what makes the rollback assertion meaningful rather than vacuous.
 */
class FailingZipRecord implements LocationDnaWritableRecord
{
    public function __construct(private readonly object $model)
    {
    }

    public function readCanonical(): mixed
    {
        return $this->model->info('location_dna_preferences');
    }

    public function writeCanonical(string $json): void
    {
        $this->model->saveMeta('location_dna_preferences', $json);
    }

    public function writeMirror(string $key, string $value): void
    {
        if ($key === 'zipCodes') {
            throw new RuntimeException('induced zipCodes mirror failure');
        }

        $this->model->saveMeta($key, $value);
    }
}
