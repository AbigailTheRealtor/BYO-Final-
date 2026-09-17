<?php

namespace Tests\Feature\Bridge;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Support\Listing\MlsProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P0-1 — the provider dimension exists, is populated, and changes nothing else.
 *
 * Two halves, and the second matters as much as the first: the identity must be
 * recorded on every row, AND every behaviour that already worked must still
 * work identically. This foundation is only safe if it is inert.
 */
class BridgeProviderIdentityTest extends TestCase
{
    use DatabaseTransactions;

    private const SCHEMA_MIGRATION   = '2026_09_17_000002_add_provider_to_bridge_properties_table.php';
    private const BACKFILL_MIGRATION = '2026_09_17_000003_backfill_provider_on_bridge_properties_table.php';

    private function migration(string $file): object
    {
        return require database_path('migrations/' . $file);
    }

    private function normalizer(): BridgePropertyNormalizer
    {
        return new BridgePropertyNormalizer();
    }

    /** A minimal but realistic Bridge Property record. */
    private function apiRecord(string $listingKey, array $overrides = []): array
    {
        return array_merge([
            'ListingKey'             => $listingKey,
            'ListingId'              => 'MLS-' . $listingKey,
            'StandardStatus'         => 'Active',
            'PropertyType'           => 'Residential',
            'ListPrice'              => 450000,
            'UnparsedAddress'        => '123 Main Street, St. Petersburg, FL 33701',
            'City'                   => 'St. Petersburg',
            'StateOrProvince'        => 'FL',
            'PostalCode'             => '33701',
            'BedroomsTotal'          => 3,
            'BathroomsTotalInteger'  => 2,
            'LivingArea'             => 1800,
            'ModificationTimestamp'  => '2026-09-01T12:00:00Z',
        ], $overrides);
    }

    // ─── the column exists ───────────────────────────────────────────────────

    /** @test */
    public function the_provider_column_exists_on_bridge_properties(): void
    {
        $this->assertTrue(Schema::hasColumn('bridge_properties', 'provider'));
    }

    // ─── requirement 2: new ingested records receive the identity ────────────

    /** @test */
    public function a_newly_upserted_record_receives_the_provider_identity(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('PROV-NEW-1'));

        $this->assertNotNull($result);
        $this->assertTrue($result->isNew);
        $this->assertSame('stellar_bridge', $result->model->provider);
    }

    /** @test */
    public function re_upserting_an_existing_record_keeps_the_provider_identity(): void
    {
        $this->normalizer()->upsert($this->apiRecord('PROV-NEW-2'));

        $second = $this->normalizer()->upsert(
            $this->apiRecord('PROV-NEW-2', ['ListPrice' => 475000])
        );

        $this->assertNotNull($second);
        $this->assertFalse($second->isNew, 'the upsert must still match on listing_key');
        $this->assertSame('stellar_bridge', $second->model->provider);
        $this->assertSame('475000.00', (string) $second->model->fresh()->list_price);
    }

    /** @test */
    public function the_normalizer_emits_the_provider_in_its_normalized_shape(): void
    {
        $normalized = $this->normalizer()->normalize($this->apiRecord('PROV-SHAPE-1'));

        $this->assertIsArray($normalized);
        $this->assertArrayHasKey('provider', $normalized);
        $this->assertSame(MlsProvider::current()->value, $normalized['provider']);
    }

    // ─── requirement 3: consistent exposure through the model ────────────────

    /** @test */
    public function the_model_exposes_the_provider_as_a_governed_value(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('PROV-EXPOSE-1'));

        $this->assertSame(MlsProvider::StellarBridge, $result->model->mlsProvider());
        $this->assertSame(MlsProvider::StellarBridge, $result->model->fresh()->mlsProvider());
    }

    /**
     * Fail-closed at the model boundary too: an unrecognised stored value reads
     * as "unknown", never as the provider we happen to have.
     *
     * @test
     */
    public function an_unrecognised_stored_provider_reads_as_null_not_as_the_current_one(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('PROV-UNKNOWN-1'));

        DB::table('bridge_properties')
            ->where('id', $result->model->id)
            ->update(['provider' => 'some_other_mls']);

        $this->assertNull($result->model->fresh()->mlsProvider());
    }

    /** @test */
    public function a_null_stored_provider_reads_as_null(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('PROV-NULL-1'));

        DB::table('bridge_properties')
            ->where('id', $result->model->id)
            ->update(['provider' => null]);

        $this->assertNull($result->model->fresh()->mlsProvider());
    }

    // ─── requirement 1: existing rows acquire the identity ───────────────────

    /**
     * The backfill populates a pre-existing row — one written before the column
     * existed, simulated by nulling it — and leaves everything else alone.
     *
     * @test
     */
    public function the_backfill_populates_rows_that_predate_the_column(): void
    {
        $a = $this->normalizer()->upsert($this->apiRecord('PROV-BACKFILL-1'))->model;
        $b = $this->normalizer()->upsert($this->apiRecord('PROV-BACKFILL-2'))->model;

        DB::table('bridge_properties')
            ->whereIn('id', [$a->id, $b->id])
            ->update(['provider' => null]);

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertSame('stellar_bridge', $a->fresh()->provider);
        $this->assertSame('stellar_bridge', $b->fresh()->provider);

        // The backfill writes one column and nothing else.
        $this->assertSame('123 Main Street, St. Petersburg, FL 33701', $a->fresh()->unparsed_address);
        $this->assertSame('PROV-BACKFILL-1', $a->fresh()->listing_key);
    }

    /** @test */
    public function the_backfill_is_idempotent_and_re_running_writes_nothing_new(): void
    {
        $row = $this->normalizer()->upsert($this->apiRecord('PROV-IDEMPOTENT-1'))->model;

        DB::table('bridge_properties')->where('id', $row->id)->update(['provider' => null]);

        $this->migration(self::BACKFILL_MIGRATION)->up();
        $first = $row->fresh()->provider;

        $this->migration(self::BACKFILL_MIGRATION)->up();
        $second = $row->fresh()->provider;

        $this->assertSame('stellar_bridge', $first);
        $this->assertSame($first, $second);
    }

    /**
     * A row already carrying a DIFFERENT provider is not overwritten. Today no
     * such row can exist; the backfill must still only fill blanks, or it would
     * become a destructive rewrite the day one does.
     *
     * @test
     */
    public function the_backfill_only_fills_blanks_and_never_overwrites(): void
    {
        $row = $this->normalizer()->upsert($this->apiRecord('PROV-FOREIGN-1'))->model;

        DB::table('bridge_properties')
            ->where('id', $row->id)
            ->update(['provider' => 'some_other_mls']);

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertSame('some_other_mls', $row->fresh()->provider);
    }

    /**
     * The frozen literal in the migration and the enum's value agree today.
     * They are permitted to diverge later — the migration is a historical
     * snapshot — but a silent divergence introduced now would mean the backfill
     * wrote a value the application does not recognise.
     *
     * @test
     */
    public function the_frozen_migration_literal_agrees_with_the_enum_today(): void
    {
        $source = file_get_contents(database_path('migrations/' . self::BACKFILL_MIGRATION));

        $this->assertStringContainsString(
            "'" . MlsProvider::StellarBridge->value . "'",
            $source
        );
    }

    /**
     * The backfill migration reaches no application code — the same rule the
     * workflow_type backfill established. An imported enum could be renamed or
     * re-valued later and would silently change what a `migrate:fresh` under a
     * September 2026 filename writes.
     *
     * Judged against the source with comments stripped, because the header
     * legitimately NAMES the enum to explain why it does not use it.
     *
     * @test
     */
    public function the_backfill_migration_references_no_application_code(): void
    {
        $code = '';

        foreach (token_get_all(file_get_contents(database_path('migrations/' . self::BACKFILL_MIGRATION))) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
                continue;
            }
            $code .= $token;
        }

        $this->assertStringNotContainsString('App\\', $code);
        $this->assertStringNotContainsString('MlsProvider', $code);
        $this->assertStringNotContainsString('BridgeProperty', $code);
    }

    // ─── requirement 4/5: nothing else moved ─────────────────────────────────

    /**
     * The lookup that every consumer uses is still a bare listing_key match,
     * unscoped by provider. Making it provider-scoped is a later, separate
     * change; doing it here would alter live behaviour.
     *
     * @test
     */
    public function listing_key_lookup_behaviour_is_unchanged(): void
    {
        $this->normalizer()->upsert($this->apiRecord('PROV-LOOKUP-1'));

        $found = BridgeProperty::where('listing_key', 'PROV-LOOKUP-1')->first();

        $this->assertNotNull($found);
        $this->assertSame('PROV-LOOKUP-1', $found->listing_key);
    }

    /**
     * The single-column unique constraint on listing_key is still in force:
     * two upserts of one key produce one row, not two. P0-2 owns replacing it
     * with a composite; until then it must remain exactly as it was.
     *
     * @test
     */
    public function one_listing_key_still_yields_exactly_one_row(): void
    {
        $this->normalizer()->upsert($this->apiRecord('PROV-UNIQUE-1'));
        $this->normalizer()->upsert($this->apiRecord('PROV-UNIQUE-1', ['ListPrice' => 500000]));

        $this->assertSame(1, BridgeProperty::where('listing_key', 'PROV-UNIQUE-1')->count());
    }

    /**
     * Every field the normalizer already produced is still produced, unchanged.
     * The provider key is additive.
     *
     * @test
     */
    public function existing_normalized_fields_are_unchanged(): void
    {
        $normalized = $this->normalizer()->normalize($this->apiRecord('PROV-FIELDS-1'));

        $this->assertSame('PROV-FIELDS-1', $normalized['listing_key']);
        $this->assertSame('MLS-PROV-FIELDS-1', $normalized['listing_id']);
        $this->assertSame('Active', $normalized['standard_status']);
        $this->assertSame('Residential', $normalized['property_type']);
        $this->assertSame(450000.0, $normalized['list_price']);
        $this->assertSame('St. Petersburg', $normalized['city']);
        $this->assertSame('FL', $normalized['state_or_province']);
        $this->assertSame('33701', $normalized['postal_code']);
        $this->assertSame(3, $normalized['bedrooms_total']);
        $this->assertSame(2, $normalized['bathrooms_total_integer']);
        $this->assertSame(1800, $normalized['living_area']);
    }

    /** A keyless record is still refused, for the same reason as before. */
    /** @test */
    public function a_record_without_a_listing_key_is_still_refused(): void
    {
        $this->assertNull($this->normalizer()->normalize(['ListPrice' => 100]));
        $this->assertNull($this->normalizer()->upsert(['ListPrice' => 100]));
    }

    /**
     * The address-change detection that drives Location DNA dispatch is
     * untouched by the new column.
     *
     * @test
     */
    public function address_change_detection_is_unchanged(): void
    {
        $this->normalizer()->upsert($this->apiRecord('PROV-ADDR-1'));

        $same = $this->normalizer()->upsert($this->apiRecord('PROV-ADDR-1'));
        $this->assertFalse($same->addressChanged);

        $moved = $this->normalizer()->upsert(
            $this->apiRecord('PROV-ADDR-1', ['UnparsedAddress' => '9 Other Road, Tampa, FL 33602'])
        );
        $this->assertTrue($moved->addressChanged);
    }

    // ─── rollback ────────────────────────────────────────────────────────────

    /**
     * The schema migration owns the undo: dropping the column removes every
     * value with it. Re-running up() restores the column.
     *
     * @test
     */
    public function the_schema_migration_rolls_back_by_dropping_the_column(): void
    {
        $schema = $this->migration(self::SCHEMA_MIGRATION);

        $schema->down();
        $this->assertFalse(Schema::hasColumn('bridge_properties', 'provider'));

        $schema->up();
        $this->assertTrue(Schema::hasColumn('bridge_properties', 'provider'));
    }

    /**
     * The backfill's down() is a deliberate no-op: it cannot distinguish the
     * rows it wrote from those ordinary ingestion has written since, so nulling
     * the column wholesale would destroy live data it never created.
     *
     * @test
     */
    public function the_backfill_rollback_is_a_no_op(): void
    {
        $row = $this->normalizer()->upsert($this->apiRecord('PROV-ROLLBACK-1'))->model;

        $this->migration(self::BACKFILL_MIGRATION)->down();

        $this->assertSame('stellar_bridge', $row->fresh()->provider);
    }
}
