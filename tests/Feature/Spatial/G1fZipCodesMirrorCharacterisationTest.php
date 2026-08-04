<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as BuyerOfferCreate;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as TenantOfferCreate;
use App\Http\Livewire\TenantAgentAuction as HireTenantCreate;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f GAP 4 — the `zipCodes` mirror.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * The G1f pre-implementation report records F-G1F-5: `zipCodes` is a FOURTH discrete
 * mirror key, written by all four Tenant-family components —
 *
 *   TenantOfferListing.php:4378        TenantOfferListingEdit.php:3302
 *   TenantAgentAuction.php:4284        TenantAgentAuctionEdit.php:3466
 *
 * — always from the component property `$this->zipCodes`, and NEVER derived from the
 * canonical blob. The contract's corresponding dimension is `zip_codes`
 * (`Dimension::ZipCodes`), and `hydrateDiscreteLocationFromBlob()` handles only `state`
 * and `counties`. Nothing connects the two.
 *
 * No G1 document named this key before the G1f audit, and no test covers it. It is a
 * standing divergence channel: a user editing ZIP codes in the map widget updates the
 * blob while the mirror keeps its previous value indefinitely.
 *
 * WHY SOURCE INSPECTION IS NOT SUFFICIENT
 * ---------------------------------------
 * The absence of a derivation is visible in the source. The observable CONSEQUENCE is
 * not: whether the mirror drifts, holds, or is destroyed depends on what the component
 * property happens to contain at save time, which in turn depends on the load path. Only
 * a round trip shows which of those three happens, and the answer differs between the
 * "user edited zips" case and the "legacy record, no edit" case.
 *
 * WHAT THIS SUITE DOES NOT DO
 * ---------------------------
 * It does not repair anything. Whether `zipCodes` should join the managed mirror set is
 * owner decision D-G1F-4, which is open. The report recommends leaving it out of scope
 * for G1f-1 precisely so that a defect fix is not smuggled into a refactor — and that
 * recommendation is only safe if the current behaviour is pinned first, which is what
 * this suite does.
 *
 * COVERAGE BOUNDARY
 * -----------------
 * Two of the four writers expose an invocable `saveAllMetadata()` and are exercised
 * behaviourally. `TenantOfferListingEdit` and `TenantAgentAuctionEdit` carry their write
 * inside `update()` and are asserted STRUCTURALLY — the KNOWN WEAKER ASSERTION boundary
 * the G1a suites established, taken for the same reason.
 */
class G1fZipCodesMirrorCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    /** What the component property carries. */
    private const PROP_ZIPS = ['33701', '33702'];

    /** What the canonical blob carries — deliberately different. */
    private const BLOB_ZIPS = ['90210', '90211'];

    /**
     * The two Tenant-family writers whose save path is invocable.
     *
     * @return array<string, array<string, mixed>>
     */
    private function behaviouralWriters(): array
    {
        // NARROWED BY G1f-4. `Tenant Offer · create` left this list when it migrated: its
        // `zipCodes` mirror is now DERIVED from the canonical `zip_codes` dimension, so the
        // property-sourced characterisations below no longer describe it. What it does instead is
        // asserted positively by test_migrated_tenant_offer_derives_zipcodes_from_canonical_state()
        // and by G1f4TenantOfferMigrationTest.
        //
        // CLARIFIED BY G1f-6: the Hire writer that remains here is MIGRATED (G1f-2) — it is not
        // listed because it is unmigrated, but because migrating it did not change `zipCodes`. The
        // Hire family deliberately keeps the mirror property-sourced and unmanaged, so the
        // property-sourced characterisations below still describe it exactly. That is the family
        // split recorded in test_the_hire_family_keeps_the_property_sourced_write_after_migrating().
        return [
            'Hire Tenant · create'  => ['class' => HireTenantCreate::class,  'user_type' => 'tenant'],
        ];
    }

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
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
            'title'       => 'G1f zipCodes',
            'is_draft'    => true,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $rows = [];
        foreach (array_merge([
            'user_type'                    => 'buyer',
            'property_items'               => '[]',
            'condition_prop_buyer'         => '[]',
            'garage_parking_spaces_option' => '[]',
            'assets'                       => '[]',
        ], $meta) as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function rereadTenant(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function invokeSave(string $class, object $component, $auction): void
    {
        $method = new ReflectionMethod($class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    private function makeComponent(array $cfg, ?array $propZips, array $blob): object
    {
        $component            = new $cfg['class']();
        $component->user_type = $cfg['user_type'];

        if ($propZips !== null) {
            $component->zipCodes = $propZips;
        }

        $component->location_dna_preferences_json = json_encode($blob);

        return $component;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SOURCE OF THE VALUE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · `zipCodes` is written from the component property, never from the
     * blob's `zip_codes` dimension.
     *
     * The core GAP 4 assertion. With the two deliberately divergent, the property value
     * wins — the opposite of what happens to `cities`, `counties` and `state`, where the
     * blob-derived write overwrites the property-derived one (GAP 2).
     */
    public function test_zipcodes_is_written_from_the_component_property_not_the_blob(): void
    {
        $covered = 0;

        foreach ($this->behaviouralWriters() as $label => $cfg) {
            $owner   = $this->owner();
            $auction = $this->tenantRecord($owner);

            $component = $this->makeComponent($cfg, self::PROP_ZIPS, ['zip_codes' => self::BLOB_ZIPS]);
            $this->invokeSave($cfg['class'], $component, $auction);

            $stored = $this->rereadTenant($auction);

            $this->assertSame(
                json_encode(self::PROP_ZIPS),
                (string) $stored->info('zipCodes'),
                "{$label}: the `zipCodes` mirror must hold the COMPONENT PROPERTY value."
            );
            $this->assertStringNotContainsString(
                self::BLOB_ZIPS[0],
                (string) $stored->info('zipCodes'),
                "{$label}: the blob's zip_codes must not reach the mirror — there is no derivation."
            );

            $covered++;
        }

        $this->assertSame(1, $covered, 'One UNMIGRATED Tenant-family writer exposes an invocable save path.');
    }

    /**
     * CHARACTERISED · the blob's `zip_codes` dimension survives the save untouched,
     * confirming the two stores move independently rather than one feeding the other.
     */
    public function test_the_blob_zip_codes_dimension_is_untouched_by_the_mirror_write(): void
    {
        foreach ($this->behaviouralWriters() as $label => $cfg) {
            $owner   = $this->owner();
            $auction = $this->tenantRecord($owner);

            $blob      = ['zip_codes' => self::BLOB_ZIPS];
            $component = $this->makeComponent($cfg, self::PROP_ZIPS, $blob);
            $this->invokeSave($cfg['class'], $component, $auction);

            $stored  = $this->rereadTenant($auction);
            $decoded = json_decode((string) $stored->info('location_dna_preferences'), true);

            $this->assertSame(
                self::BLOB_ZIPS,
                $decoded['zip_codes'] ?? null,
                "{$label}: the canonical zip_codes dimension must be unchanged by the mirror write."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CLEARING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · clearing the canonical `zip_codes` does NOT clear the mirror.
     *
     * The blob records an intentional clear as `"zip_codes": []`; the mirror keeps
     * whatever the component property holds. A consumer reading the discrete key
     * therefore continues to see ZIP codes the user has deleted.
     *
     * This is the same class of defect as the cleared-`cities` resurrection G1a proved,
     * but with no compensating mirror write anywhere — for `cities`,
     * `HasSearchAreas.php:130` at least mirrors the clear. `zipCodes` has no equivalent.
     */
    public function test_clearing_canonical_zip_codes_does_not_clear_the_mirror(): void
    {
        foreach ($this->behaviouralWriters() as $label => $cfg) {
            $owner   = $this->owner();
            $auction = $this->tenantRecord($owner, ['zipCodes' => json_encode(self::PROP_ZIPS)]);

            // The user cleared zips in the widget: the blob says explicitly-empty.
            $component = $this->makeComponent($cfg, self::PROP_ZIPS, ['zip_codes' => []]);
            $this->invokeSave($cfg['class'], $component, $auction);

            $stored = $this->rereadTenant($auction);

            $this->assertSame(
                [],
                json_decode((string) $stored->info('location_dna_preferences'), true)['zip_codes'] ?? null,
                "{$label}: precondition — the blob must record the clear."
            );
            $this->assertSame(
                json_encode(self::PROP_ZIPS),
                (string) $stored->info('zipCodes'),
                "{$label}: the mirror still holds the pre-clear value. Blob and mirror now disagree, "
                .'and nothing surfaced an error.'
            );
        }
    }

    /**
     * CHARACTERISED DEFECT · a legacy-only `zipCodes` mirror does NOT survive a save in
     * which the component property is empty — it is overwritten with `[]`.
     *
     * The `zipCodes` counterpart of `test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`.
     * Because the write is unconditional and sourced from a property that no load path
     * populates from the blob, a record whose ZIP codes live only in the legacy mirror
     * loses them on the next save of any kind.
     */
    public function test_a_legacy_only_zipcodes_mirror_is_destroyed_by_a_save_with_an_empty_property(): void
    {
        foreach ($this->behaviouralWriters() as $label => $cfg) {
            $owner   = $this->owner();
            $auction = $this->tenantRecord($owner, ['zipCodes' => json_encode(['34698', '34677'])]);

            // Legacy record: the blob knows nothing about zips and the property is empty.
            $component = $this->makeComponent($cfg, [], ['cities' => ['Tampa']]);
            $this->invokeSave($cfg['class'], $component, $auction);

            $stored = $this->rereadTenant($auction);

            $this->assertSame(
                json_encode([]),
                (string) $stored->info('zipCodes'),
                "{$label}: the legacy mirror value is destroyed — the write is unconditional and the "
                .'property was empty.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PER-WORKFLOW VARIANCE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the Buyer-family components write NO `zipCodes` mirror at all.
     *
     * The behaviour differs by workflow, which is why the mirror set cannot be described
     * as a single list. G1f's mirror projection contract has to be per-record-family, not
     * global.
     */
    public function test_buyer_family_components_write_no_zipcodes_mirror(): void
    {
        foreach ([
            'Hire Buyer · create'  => HireBuyerCreate::class,
            'Buyer Offer · create' => BuyerOfferCreate::class,
        ] as $label => $class) {
            $owner   = $this->owner();
            $auction = $this->buyerRecord($owner);

            $component = new $class();
            $component->location_dna_preferences_json = json_encode(['zip_codes' => self::BLOB_ZIPS]);

            if (property_exists($component, 'zipCodes')) {
                $component->zipCodes = self::PROP_ZIPS;
            }

            $this->invokeSave($class, $component, $auction);

            $stored = BuyerAgentAuction::with('meta')->findOrFail($auction->id);

            $this->assertFalse(
                $stored->info('zipCodes'),
                "{$label}: the Buyer family must write no `zipCodes` mirror. `info()` returns false "
                .'for an unwritten meta key.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STRUCTURAL · the two edit writers
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * THE FAMILY SPLIT, RECORDED · the Hire family keeps `zipCodes` property-sourced even
     * after migrating; the Offer family manages it.
     *
     * RETITLED BY G1f-6, because "unmigrated" stopped being the reason. `TenantAgentAuctionEdit`
     * is now migrated, and it STILL writes `zipCodes` from the component property — that is a
     * decision, not a leftover, and it is the decision this assertion now records.
     *
     * The two families diverge deliberately:
     *
     *   Hire  (`TenantAgentAuction`, `TenantAgentAuctionEdit`)  → property-sourced, UNMANAGED,
     *          written for every supported role, never derived from canonical `zip_codes`.
     *   Offer (`TenantOfferListing`, `TenantOfferListingEdit`)  → managed via the surface-scoped
     *          opt-in G1f-4 added, derived from canonical state.
     *
     * G1f-6 was explicitly scoped NOT to adopt the Offer behaviour, so the Hire pair matches its
     * own create sibling rather than the newer Offer treatment. The §17.4 checkpoint governs
     * whether the family ever converges; until then this asymmetry is intentional and asserting it
     * is what stops it being "tidied up" without that authorization.
     */
    public function test_the_hire_family_keeps_the_property_sourced_write_after_migrating(): void
    {
        $relative = 'app/Http/Livewire/TenantAgentAuctionEdit.php';
        $source   = file_get_contents(base_path($relative));

        $this->assertStringContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            $source,
            "{$relative}: must still write the zipCodes mirror from the component property."
        );

        // Migrated, yet deliberately not managing the mirror — the two facts together are the point.
        $this->assertStringContainsString(
            '$this->persistLocationDna($auction);',
            $source,
            "{$relative}: is migrated as of G1f-6 …"
        );
        $this->assertStringNotContainsString(
            'managingMirrors(',
            $source,
            "{$relative}: … and must NOT adopt the Offer family's managed zipCodes opt-in."
        );

        $this->assertStringNotContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            file_get_contents(base_path('app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php')),
            'the migrated Tenant Offer edit copy must no longer write zipCodes inline'
        );
    }

    /**
     * MIGRATED (G1f-4) · the Tenant Offer pair derives `zipCodes` from canonical state.
     *
     * The positive counterpart to the characterisations above, and the reason `Tenant Offer ·
     * create` left `behaviouralWriters()`. The three defects this suite pinned for that workflow —
     * the mirror ignoring the blob, a clear not reaching the mirror, and a legacy-only value being
     * destroyed by an unconditional write — are all closed by the migration, so each is asserted
     * here in its corrected form.
     */
    public function test_migrated_tenant_offer_derives_zipcodes_from_canonical_state(): void
    {
        $owner = $this->owner();

        // 1 · the blob wins over the component property — the derivation now exists.
        $auction   = $this->tenantRecord($owner);
        $component = $this->makeComponent(
            ['class' => TenantOfferCreate::class, 'user_type' => 'tenant'],
            self::PROP_ZIPS,
            ['zip_codes' => self::BLOB_ZIPS]
        );
        $this->invokeSave(TenantOfferCreate::class, $component, $auction);

        $this->assertSame(
            json_encode(self::BLOB_ZIPS),
            (string) $this->rereadTenant($auction)->info('zipCodes'),
            'the migrated writer derives the mirror from canonical zip_codes, not the property'
        );

        // 2 · a canonical clear now reaches the mirror.
        $cleared   = $this->tenantRecord($owner, ['zipCodes' => json_encode(self::PROP_ZIPS)]);
        $component = $this->makeComponent(
            ['class' => TenantOfferCreate::class, 'user_type' => 'tenant'],
            self::PROP_ZIPS,
            ['zip_codes' => []]
        );
        $this->invokeSave(TenantOfferCreate::class, $component, $auction = $cleared);

        $this->assertSame(
            '[]',
            (string) $this->rereadTenant($cleared)->info('zipCodes'),
            'a cleared canonical ZIP set now clears the mirror instead of leaving it stale'
        );

        // 3 · a legacy-only mirror survives a save that states no ZIPs.
        $legacy    = $this->tenantRecord($owner, ['zipCodes' => json_encode(['34698', '34677'])]);
        $component = $this->makeComponent(
            ['class' => TenantOfferCreate::class, 'user_type' => 'tenant'],
            [],
            ['cities' => ['Tampa']]
        );
        $this->invokeSave(TenantOfferCreate::class, $component, $legacy);

        $this->assertSame(
            json_encode(['34698', '34677']),
            (string) $this->rereadTenant($legacy)->info('zipCodes'),
            'an absent zip_codes dimension no longer destroys a legacy-only mirror'
        );
    }

    /**
     * CHARACTERISED STRUCTURALLY · no component derives `zipCodes` from the blob.
     *
     * The negative that makes the whole finding coherent: if any writer ever started
     * deriving the mirror from `zip_codes`, this assertion fails and the behavioural
     * expectations above would need revisiting.
     */
    public function test_no_writer_derives_the_zipcodes_mirror_from_the_blob(): void
    {
        // NARROWED BY G1f-4: the migrated Tenant Offer pair derives the mirror through the
        // canonical writer by design. These are the writers that still must not.
        foreach ([
            'app/Http/Livewire/Concerns/HasSearchAreas.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = file_get_contents(base_path($relative));

            $this->assertStringNotContainsString(
                "\$ldnaDecoded['zip_codes']",
                $source,
                "{$relative}: no writer derives the zipCodes mirror from the decoded blob."
            );
            $this->assertStringNotContainsString(
                "\$ldna['zip_codes']",
                $source,
                "{$relative}: no hydration path reads zip_codes out of the blob."
            );
        }
    }
}
