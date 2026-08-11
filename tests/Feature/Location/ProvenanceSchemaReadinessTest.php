<?php

namespace Tests\Feature\Location;

use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenance;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\Schema\ProvenanceSchemaReadiness;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Code and schema ship separately, so there is a real window in which a release
 * knows about `geocode_precision` and the database does not.
 *
 * THE ABSENCE IS GENUINE
 * ----------------------
 * The "not ready" cases below rebuild `property_location_dna` with its pre-G4
 * column set — the table really exists and the three provenance columns really
 * do not, which is exactly the production condition. Nothing is mocked and no
 * column is quietly re-added to make an assertion pass; a test that faked the
 * absence would be testing its own fixture.
 *
 * (The columns are dropped by recreating the table rather than with
 * `dropColumn`, because SQLite needs doctrine/dbal for that and this project
 * does not install it. SQLite has transactional DDL, so DatabaseTransactions
 * restores the real table at the end of each test.)
 */
class ProvenanceSchemaReadinessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        ProvenanceSchemaReadiness::flush();
        CoordinateProvenance::flushWarningLatch();
    }

    protected function tearDown(): void
    {
        ProvenanceSchemaReadiness::flush();
        CoordinateProvenance::flushWarningLatch();

        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function resolved(): PropertyCoordinateResult
    {
        return PropertyCoordinateResult::resolved(
            latitude:          27.948434712759,
            longitude:         -82.458094358643,
            precision:         CoordinatePrecision::Interpolated,
            source:            CoordinateSource::Geocoder,
            provider:          'us_census',
            normalizedAddress: '315 madison st tampa fl 33602',
        );
    }

    /**
     * Rebuild property_location_dna exactly as it stood before the G4
     * provenance migration.
     */
    private function revertToPreProvenanceSchema(): void
    {
        Schema::drop(ProvenanceSchemaReadiness::TABLE);

        Schema::create(ProvenanceSchemaReadiness::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');
            $table->string('source_address')->nullable();
            $table->string('source_city')->nullable();
            $table->string('source_county')->nullable();
            $table->string('source_state')->nullable();
            $table->string('source_zip')->nullable();
            $table->decimal('geocoded_lat', 10, 7)->nullable();
            $table->decimal('geocoded_lng', 10, 7)->nullable();
            $table->string('geocode_source')->nullable();
            $table->string('geocode_status')->default('pending');
            $table->text('geocode_error')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('lifestyle_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_type', 'listing_id']);
        });

        ProvenanceSchemaReadiness::flush();
    }

    // ── schema ready ────────────────────────────────────────────────────────

    public function test_the_migrated_schema_is_reported_ready(): void
    {
        $this->assertTrue(ProvenanceSchemaReadiness::isReady());
        $this->assertSame([], ProvenanceSchemaReadiness::missingColumns());
        $this->assertNull(CoordinateProvenance::persistenceSkipReason());
    }

    public function test_provenance_is_written_when_the_schema_is_ready(): void
    {
        $columns = CoordinateProvenance::persistableColumnsFor($this->resolved());

        $row = PropertyLocationDna::create(array_merge([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 995001,
            'geocoded_lat'   => 27.948434712759,
            'geocoded_lng'   => -82.458094358643,
            'geocode_status' => 'geocoded',
        ], $columns))->fresh();

        $this->assertSame('interpolated', $row->geocode_precision);
        $this->assertSame('us_census', $row->geocode_provider);
        $this->assertSame('315 madison st tampa fl 33602', $row->normalized_address);
        $this->assertTrue(
            CoordinateProvenance::storedIsUsableForLocationDna($row->geocode_precision)
        );
    }

    // ── schema NOT ready ────────────────────────────────────────────────────

    public function test_a_pre_migration_schema_is_reported_not_ready(): void
    {
        $this->revertToPreProvenanceSchema();

        $this->assertTrue(
            Schema::hasTable(ProvenanceSchemaReadiness::TABLE),
            'The table exists; only the provenance columns are missing'
        );
        $this->assertFalse(ProvenanceSchemaReadiness::isReady());
        $this->assertSame(
            ['geocode_precision', 'geocode_provider', 'normalized_address'],
            ProvenanceSchemaReadiness::missingColumns()
        );
    }

    public function test_the_provenance_write_is_skipped_when_the_schema_is_not_ready(): void
    {
        $this->revertToPreProvenanceSchema();

        $this->assertSame([], CoordinateProvenance::persistableColumnsFor($this->resolved()));
        $this->assertSame(
            ProvenanceSchemaReadiness::REASON_NOT_READY,
            CoordinateProvenance::persistenceSkipReason()
        );
    }

    public function test_the_coordinate_is_still_stored_and_nothing_throws(): void
    {
        // The property that matters. Provenance is metadata about a coordinate
        // we already computed; losing it costs a diagnostic field, whereas an
        // undefined-column exception inside a listing save is a 500 on publish.
        $this->revertToPreProvenanceSchema();

        $row = PropertyLocationDna::create(array_merge([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 995002,
            'geocoded_lat'   => 27.948434712759,
            'geocoded_lng'   => -82.458094358643,
            'geocode_status' => 'geocoded',
        ], CoordinateProvenance::persistableColumnsFor($this->resolved())));

        $this->assertTrue($row->exists);
        $this->assertEqualsWithDelta(
            27.948434712759,
            (float) $row->fresh()->geocoded_lat,
            0.0001,
            'The coordinate itself must survive a missing provenance schema'
        );
    }

    public function test_writing_the_provenance_columns_directly_really_would_fail(): void
    {
        // Proves the guard is preventing something real rather than decorating
        // a write that would have succeeded anyway. Without the guard, this is
        // what reaches a listing save.
        $this->revertToPreProvenanceSchema();

        $this->expectException(\Illuminate\Database\QueryException::class);

        PropertyLocationDna::create(array_merge([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 995003,
            'geocoded_lat'   => 27.948434712759,
            'geocoded_lng'   => -82.458094358643,
            'geocode_status' => 'geocoded',
        ], CoordinateProvenance::columnsFor($this->resolved())));
    }

    public function test_a_missing_table_is_also_not_ready(): void
    {
        Schema::drop(ProvenanceSchemaReadiness::TABLE);
        ProvenanceSchemaReadiness::flush();

        $this->assertFalse(ProvenanceSchemaReadiness::isReady());
        $this->assertSame([], CoordinateProvenance::persistableColumnsFor($this->resolved()));
    }

    // ── it says so ──────────────────────────────────────────────────────────

    public function test_the_skip_is_logged_with_a_structured_reason(): void
    {
        $this->revertToPreProvenanceSchema();

        $events = [];
        Log::listen(function ($event) use (&$events) {
            if ($event->message === 'coordinate_provenance_schema_not_ready') {
                $events[] = $event->context;
            }
        });

        CoordinateProvenance::persistableColumnsFor($this->resolved());

        $this->assertCount(1, $events);
        $this->assertSame(ProvenanceSchemaReadiness::REASON_NOT_READY, $events[0]['reason']);
        $this->assertSame(
            ['geocode_precision', 'geocode_provider', 'normalized_address'],
            $events[0]['missing_columns']
        );
    }

    public function test_the_warning_is_logged_once_per_process_not_once_per_property(): void
    {
        // A listing sweep would otherwise emit an identical line per row,
        // burying the one fact worth seeing under thousands of copies of it.
        $this->revertToPreProvenanceSchema();

        $events = [];
        Log::listen(function ($event) use (&$events) {
            if ($event->message === 'coordinate_provenance_schema_not_ready') {
                $events[] = $event->context;
            }
        });

        for ($i = 0; $i < 25; $i++) {
            CoordinateProvenance::persistableColumnsFor($this->resolved());
        }

        $this->assertCount(1, $events);
    }

    // ── caching ─────────────────────────────────────────────────────────────

    public function test_the_schema_answer_is_memoised(): void
    {
        // Provenance is written once per property, potentially in a loop over a
        // listing set. Three schema lookups per row would turn a metadata
        // concern into a query-count problem.
        ProvenanceSchemaReadiness::flush();

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queries) {
            $queries++;
        });

        ProvenanceSchemaReadiness::isReady();
        $afterFirst = $queries;

        for ($i = 0; $i < 20; $i++) {
            ProvenanceSchemaReadiness::isReady();
        }

        $this->assertSame(
            $afterFirst,
            $queries,
            'Repeated readiness checks must not re-query the schema'
        );
    }

    public function test_flushing_forces_a_fresh_inspection(): void
    {
        // A worker that outlives a migration must be able to notice.
        $this->assertTrue(ProvenanceSchemaReadiness::isReady());

        $this->revertToPreProvenanceSchema(); // flushes internally

        $this->assertFalse(ProvenanceSchemaReadiness::isReady());
    }

    // ── legacy rows are not promoted ────────────────────────────────────────

    public function test_a_null_provenance_row_is_never_treated_as_exact(): void
    {
        // Both the pre-G4 case and the schema-not-ready case leave provenance
        // absent. Neither may read back as a coordinate good enough to measure
        // from.
        $this->assertFalse(CoordinateProvenance::storedIsUsableForLocationDna(null));
        $this->assertFalse(CoordinateProvenance::storedIsUsableForLocationDna(''));
        $this->assertSame(
            CoordinatePrecision::Unknown,
            CoordinateProvenance::precisionFrom(null)
        );
    }

    // ── the guard is neutral ────────────────────────────────────────────────

    public function test_the_guard_names_no_provider_and_no_role(): void
    {
        // Code only, comments stripped. The guard's docblock explains its own
        // neutrality by naming what it is deliberately not coupled to, and a
        // scan that could not tell that apart from a real dependency would
        // force the explanation to be deleted to make the test pass.
        $source = file_get_contents(base_path('app/Services/Schema/ProvenanceSchemaReadiness.php'));

        $this->assertIsString($source);

        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        $this->assertStringContainsString('class ProvenanceSchemaReadiness', $code, 'The scan must still see the class body');

        foreach (['Census', 'census', 'Seller', 'Landlord', 'Buyer', 'Tenant', 'Livewire'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "The guard must stay provider- and flow-neutral (found '{$forbidden}')"
            );
        }
    }
}
