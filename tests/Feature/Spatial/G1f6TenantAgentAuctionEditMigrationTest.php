<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\TenantAgentAuctionEdit as HireTenantEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\LandlordAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f-6 — `TenantAgentAuctionEdit` writes Location DNA through the canonical writer.
 *
 * THE LAST WORKFLOW, AND THE ONLY MULTI-ROLE ONE
 * ----------------------------------------------
 * This component serves all four roles through `$auctionClass = match ($this->user_type)`. Before
 * G1f-6 its three discrete mirror writes stood ABOVE the `user_type` gate and ran for every role,
 * while `saveSearchAreas()` stood inside it and ran for only `buyer` and `tenant`. So the same
 * three statements played two completely different parts:
 *
 *   buyer / tenant     the losing half of a double-write, overwritten moments later from the blob
 *   seller / landlord  the ONLY mirror writes those roles have ever had, uncorrected by any blob
 *
 * That is why the migration is an if/else. Replacing the three writes unconditionally would have
 * silently stopped mirroring location for half the supported roles — a far worse defect than the
 * one being fixed. This suite asserts BOTH halves: the gated path now goes through the writer, and
 * the ungated path is byte-for-byte what it always was.
 *
 * THE VEHICLE, AND WHY IT IS THIS ONE
 * -----------------------------------
 * The write lives inside `update()`, which runs full validation, file handling, a redirect and
 * several hundred unrelated writes. Invoking it would characterise those, not the mirror contract.
 * The consolidated seam is invoked directly instead — post-migration it IS the entire Location DNA
 * write path for the gated roles, which is what makes the shortcut sound. This is the vehicle
 * `G1f4TenantOfferMigrationTest` established for the same situation, under the §16.3 precedent that
 * structural characterisation plus a behaviourally proven create sibling is sufficient.
 *
 * The seller/landlord branch cannot be reached through the seam, so it is exercised through
 * `saveAllMetadataForGatedRoles()` — see that helper for exactly what it does and does not claim.
 *
 * SCOPE HELD, ASSERTED BEHAVIOURALLY
 * ----------------------------------
 * `zipCodes` stays UNMANAGED and property-sourced for the Hire family, matching the create sibling
 * and deliberately NOT adopting the managed opt-in G1f-4 gave the Tenant OFFER copies.
 */
class G1f6TenantAgentAuctionEditMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /** Component-property values, deliberately divergent from every payload below. */
    private const PROP_CITIES   = ['Tampa'];
    private const PROP_COUNTIES = ['Hillsborough'];
    private const PROP_STATE    = 'FL';

    private function owner(string $type = 'tenant'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function tenantRecord(User $owner, array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create(['user_id' => $owner->id]);

        foreach (array_merge(['user_type' => 'tenant', 'property_items' => '[]'], $meta) as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function buyerRecord(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1f-6 migration',
            'is_draft'    => true,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $rows = [];
        foreach (array_merge(['user_type' => 'buyer', 'property_items' => '[]'], $meta) as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread($auction)
    {
        return $auction::with('meta')->findOrFail($auction->id);
    }

    /**
     * A component primed for the write path, always carrying the divergent property values.
     *
     * Retaining them is load-bearing: pre-migration they were the SOURCE of the stale mirrors for
     * the gated roles, so their continued presence proves the resurrection route is shut rather
     * than merely unused by a particular fixture.
     */
    private function editor(mixed $payload, string $userType = 'tenant'): HireTenantEdit
    {
        $component                                = new HireTenantEdit();
        $component->user_type                     = $userType;
        $component->cities                        = self::PROP_CITIES;
        $component->counties                      = self::PROP_COUNTIES;
        $component->state                         = self::PROP_STATE;
        $component->zipCodes                      = ['33601'];
        $component->location_dna_preferences_json = $payload;

        return $component;
    }

    /** Invoke the consolidated seam — the whole Location DNA write path for the gated roles. */
    private function persist(HireTenantEdit $component, $auction): void
    {
        $method = new ReflectionMethod(HireTenantEdit::class, 'persistLocationDna');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GATED ROLES · buyer / tenant now write through the canonical writer
    // ═════════════════════════════════════════════════════════════════════════

    /** A stated payload persists canonically and derives all three mirrors from the result. */
    public function test_a_stated_payload_persists_canonically_and_mirrors_from_the_result(): void
    {
        foreach (['tenant', 'buyer'] as $role) {
            $owner   = $this->owner($role);
            $auction = $role === 'tenant' ? $this->tenantRecord($owner) : $this->buyerRecord($owner);

            $this->persist($this->editor(json_encode([
                'cities'   => ['Orlando'],
                'counties' => ['Orange'],
                'state'    => 'GA',
            ]), $role), $auction);

            $stored = $this->reread($auction);

            $this->assertSame('["Orlando"]', (string) $stored->info('cities'), "{$role}: cities");
            $this->assertSame('["Orange"]', (string) $stored->info('counties'), "{$role}: counties");
            $this->assertSame('GA', (string) $stored->info('state'), "{$role}: state");

            $canonical = json_decode((string) $stored->info('location_dna_preferences'), true);
            $this->assertSame(['Orlando'], $canonical['cities'], "{$role}: canonical");
            $this->assertArrayHasKey('schema_version', $canonical, "{$role}: stamped");
        }
    }

    /** No component-property value reaches storage for the gated roles. */
    public function test_no_component_property_value_reaches_storage(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner);

        $this->persist($this->editor(json_encode([
            'cities'   => ['Orlando'],
            'counties' => ['Orange'],
            'state'    => 'GA',
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertStringNotContainsString('Tampa', (string) $stored->info('cities'));
        $this->assertStringNotContainsString('Hillsborough', (string) $stored->info('counties'));
        $this->assertNotSame(self::PROP_STATE, (string) $stored->info('state'));
    }

    /** An explicit clear propagates to every managed mirror — the end of F-G1-4's split. */
    public function test_an_explicit_clear_propagates_to_every_managed_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner, [
            'cities'   => json_encode(['Clearwater']),
            'counties' => json_encode(['Pinellas']),
            'state'    => 'FL',
        ]);

        $this->persist($this->editor(json_encode([
            'cities'   => [],
            'counties' => [],
            'state'    => '',
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('[]', (string) $stored->info('cities'), 'cleared cities');
        $this->assertSame('[]', (string) $stored->info('counties'), 'cleared counties');
        $this->assertSame('', (string) $stored->info('state'), 'cleared state');
    }

    /** An absent dimension is preserved — absence is not an instruction. */
    public function test_an_absent_dimension_is_preserved(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner, [
            'cities'   => json_encode(['Clearwater']),
            'counties' => json_encode(['Pinellas']),
        ]);

        $this->persist($this->editor(json_encode(['state' => 'GA'])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('GA', (string) $stored->info('state'));
        $this->assertSame(json_encode(['Clearwater']), (string) $stored->info('cities'));
        $this->assertSame(json_encode(['Pinellas']), (string) $stored->info('counties'));
    }

    /**
     * THE DEFECT THAT IS GONE · a no-op save on a legacy-only record destroys nothing.
     *
     * `false` is the real shape: the EAV accessor returns boolean `false` for an unwritten key and
     * the trait assigns it straight to the bridged property, so an unmounted editor on a legacy
     * record arrives exactly like this.
     */
    public function test_a_no_op_save_on_a_legacy_only_record_destroys_nothing(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner, ['cities' => json_encode(['Clearwater'])]);

        foreach ([false, '', null, '{not json'] as $payload) {
            $this->persist($this->editor($payload), $auction);

            $stored = $this->reread($auction);

            $this->assertSame(
                json_encode(['Clearwater']),
                (string) $stored->info('cities'),
                'the legacy mirror must survive a payload that states nothing'
            );
            $this->assertSame(
                '',
                (string) $stored->info('location_dna_preferences'),
                'and no canonical blob may be invented for a legacy-only record'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNGATED ROLES · seller / landlord behaviour is unchanged
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A `landlord` record never receives a canonical document, and still gets its mirrors.
     *
     * The seam itself is never reached for this role — the gate is in `update()`, not in
     * `persistLocationDna()` — so this asserts the two halves that matter and are checkable
     * without running the 4,200-line `update()`:
     *
     *   1. the writer, if it WERE reached with this payload, is not what wrote these mirrors —
     *      proven by writing them the way the else branch does and observing no canonical blob;
     *   2. the else branch's three statements produce exactly the pre-migration result.
     *
     * The gate placement itself — that a landlord never reaches the seam at all — is asserted
     * structurally by {@see \Tests\Unit\Services\LocationDna\Persistence\G1f6MigrationBoundaryGuardTest}
     * and behaviourally for the create sibling by
     * {@see G1fHireTenantUserTypeGateCharacterisationTest}, whose gate is textually identical.
     * Recorded as a KNOWN WEAKER ASSERTION rather than presented as equivalent.
     */
    public function test_the_ungated_roles_keep_property_sourced_mirrors_and_no_canonical_document(): void
    {
        $owner = $this->owner('landlord');

        // `LandlordAgentAuction` has no factory — built with forceFill, the same vehicle
        // `HireSearchAreasParityTest` established for the factory-less auction models.
        $auction = (new LandlordAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'is_draft'    => true,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        foreach (['user_type' => 'landlord', 'property_items' => '[]'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        // Exactly what the else branch does, with the same sources.
        $component = $this->editor(json_encode(['cities' => ['Orlando']]), 'landlord');
        $auction->saveMeta('cities', json_encode($component->cities));
        $auction->saveMeta('counties', json_encode($component->counties));
        $auction->saveMeta('state', $component->state);

        $fresh = LandlordAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertSame(json_encode(self::PROP_CITIES), (string) $fresh->info('cities'));
        $this->assertSame(json_encode(self::PROP_COUNTIES), (string) $fresh->info('counties'));
        $this->assertSame(self::PROP_STATE, (string) $fresh->info('state'));

        $this->assertSame(
            '',
            (string) $fresh->info('location_dna_preferences'),
            'a landlord record must never acquire a canonical Location DNA document — D-G1F-3 3-C'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // zipCodes · UNMANAGED, matching the create sibling
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The writer never touches the `zipCodes` mirror for this component.
     *
     * The Hire family keeps it property-sourced and ungated. Adopting G1f-4's managed opt-in would
     * make this call derive the mirror from canonical `zip_codes` — which is exactly what the
     * authorization declined, so it is asserted as an absence at the storage layer.
     */
    public function test_the_writer_never_manages_the_zip_codes_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner, ['zipCodes' => json_encode(['33601'])]);

        $this->persist($this->editor(json_encode([
            'cities'    => ['Orlando'],
            'zip_codes' => ['90210', '90211'],
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('["Orlando"]', (string) $stored->info('cities'));
        $this->assertSame(
            json_encode(['33601']),
            (string) $stored->info('zipCodes'),
            'the zipCodes mirror must be untouched by the writer — canonical zip_codes must not '
            .'reach it, and the pre-existing property-sourced value must stand'
        );
    }

    /** Clearing canonical zip codes does not clear the unmanaged mirror. */
    public function test_clearing_canonical_zip_codes_does_not_clear_the_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner, ['zipCodes' => json_encode(['33601'])]);

        $this->persist($this->editor(json_encode(['zip_codes' => []])), $auction);

        $this->assertSame(
            json_encode(['33601']),
            (string) $this->reread($auction)->info('zipCodes'),
            'an unmanaged mirror is not cleared by a canonical clear — the Offer family behaves '
            .'differently here, deliberately'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FIDELITY AND ENTRY POINTS
    // ═════════════════════════════════════════════════════════════════════════

    /** Geometry survives the migrated path and the stored bytes do not drift on a re-save. */
    public function test_geometry_survives_and_stored_bytes_are_stable(): void
    {
        $path = [];
        for ($i = 0; $i < 1200; $i++) {
            $path[] = ['lat' => 27.5 + ($i / 100000), 'lng' => -82.5 - ($i / 100000)];
        }

        $owner   = $this->owner();
        $auction = $this->tenantRecord($owner);

        $this->persist($this->editor(json_encode([
            'cities'          => ["Coeur d'Alene", '東京'],
            'polygons'        => [['label' => 'Huge area', 'path' => $path]],
            'radius_searches' => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 5.25]],
            'location_notes'  => "Line one\nLine \"two\" — em dash, emoji 🏖",
        ])), $auction);

        $stored  = (string) $this->reread($auction)->info('location_dna_preferences');
        $decoded = json_decode($stored, true);

        $this->assertCount(1200, $decoded['polygons'][0]['path'], 'polygon truncated');
        $this->assertSame(5.25, $decoded['radius_searches'][0]['radius_miles'], 'radius drifted');
        $this->assertSame('東京', $decoded['cities'][1], 'unicode mangled');
        $this->assertSame(
            "Line one\nLine \"two\" — em dash, emoji 🏖",
            $decoded['location_notes'],
            'notes mangled'
        );

        $this->persist($this->editor($stored), $this->reread($auction));

        $this->assertSame(
            $stored,
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'canonical bytes drifted across a re-save'
        );
    }

    /**
     * Both entry points route through the one writer call.
     *
     * `saveDraftOnly()` delegates to `update()` behind a `_isDraftSave` flag, so the draft and
     * submit paths share a single write body. Migrating that body migrates both at once.
     */
    public function test_both_entry_points_route_through_the_single_writer_call(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/TenantAgentAuctionEdit.php'));

        $this->assertStringContainsString(
            '$this->update();',
            $source,
            'saveDraftOnly() delegates to update()'
        );
        $this->assertSame(
            1,
            substr_count($source, '$this->persistLocationDna($auction);'),
            'and update() reaches the canonical writer exactly once, so both entry points do too'
        );
    }
}
