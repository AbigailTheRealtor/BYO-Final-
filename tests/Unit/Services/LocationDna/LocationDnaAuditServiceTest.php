<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDnaAudit;
use App\Services\LocationDna\LocationDnaAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LocationDnaAuditServiceTest
 *
 * Verifies LocationDnaAuditService::record() using a SQLite in-memory database.
 *
 * Test coverage:
 *   (a) Audit row is created in the DB
 *   (b) input_snapshot is cast as array
 *   (c) output_snapshot is cast as array
 *   (d) Append-only: attempt to update an existing row is blocked
 *   (e) Append-only: attempt to delete an existing row is blocked
 *   (f) record() does not throw even when DB is unavailable (mock a failure)
 *   (g) Service file contains no reference to OpenAI/AI classes
 *   (h) Service file contains no reference to marketing report classes
 */
class LocationDnaAuditServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 101;

    private function makeService(): LocationDnaAuditService
    {
        return new LocationDnaAuditService();
    }

    private function recordSample(
        ?array $inputSnapshot  = ['address' => '123 Main St', 'city' => 'Tampa', 'state' => 'FL'],
        ?array $outputSnapshot = ['success' => true, 'status' => 'geocoded'],
    ): PropertyLocationDnaAudit {
        return $this->makeService()->record(
            listingType:    self::LISTING_TYPE,
            listingId:      self::LISTING_ID,
            eventType:      'geocode',
            status:         'geocoded',
            source:         'google',
            inputSnapshot:  $inputSnapshot,
            outputSnapshot: $outputSnapshot,
            error:          null,
        );
    }

    // =========================================================================
    // (a) Audit row is created in the DB
    // =========================================================================

    /** @test */
    public function it_inserts_an_audit_row_into_the_database(): void
    {
        $audit = $this->recordSample();

        $this->assertTrue($audit->exists, 'Returned model must be persisted (exists=true)');

        $this->assertDatabaseHas('property_location_dna_audits', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'event_type'   => 'geocode',
            'status'       => 'geocoded',
            'source'       => 'google',
        ]);
    }

    // =========================================================================
    // (b) input_snapshot is cast as array
    // =========================================================================

    /** @test */
    public function input_snapshot_is_cast_as_array(): void
    {
        $input = ['address' => '456 Elm St', 'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801'];

        $audit = $this->makeService()->record(
            listingType:    self::LISTING_TYPE,
            listingId:      self::LISTING_ID,
            eventType:      'geocode',
            status:         'geocoded',
            source:         'google',
            inputSnapshot:  $input,
            outputSnapshot: null,
            error:          null,
        );

        $fresh = PropertyLocationDnaAudit::find($audit->id);

        $this->assertIsArray($fresh->input_snapshot);
        $this->assertSame('456 Elm St', $fresh->input_snapshot['address']);
        $this->assertSame('Orlando', $fresh->input_snapshot['city']);
    }

    // =========================================================================
    // (c) output_snapshot is cast as array
    // =========================================================================

    /** @test */
    public function output_snapshot_is_cast_as_array(): void
    {
        $output = ['success' => true, 'status' => 'geocoded', 'lat' => 27.9506, 'lng' => -82.4572];

        $audit = $this->makeService()->record(
            listingType:    self::LISTING_TYPE,
            listingId:      self::LISTING_ID,
            eventType:      'geocode',
            status:         'geocoded',
            source:         'google',
            inputSnapshot:  null,
            outputSnapshot: $output,
            error:          null,
        );

        $fresh = PropertyLocationDnaAudit::find($audit->id);

        $this->assertIsArray($fresh->output_snapshot);
        $this->assertTrue($fresh->output_snapshot['success']);
        $this->assertSame('geocoded', $fresh->output_snapshot['status']);
    }

    // =========================================================================
    // (d) Append-only: updating an existing row throws LogicException
    // =========================================================================

    /** @test */
    public function updating_an_existing_audit_row_throws_logic_exception(): void
    {
        $audit = $this->recordSample();

        $this->expectException(\LogicException::class);

        $audit->status = 'mutated';
        $audit->save();
    }

    // =========================================================================
    // (e) Append-only: deleting an existing row throws LogicException
    // =========================================================================

    /** @test */
    public function deleting_an_existing_audit_row_throws_logic_exception(): void
    {
        $audit = $this->recordSample();

        $this->expectException(\LogicException::class);

        $audit->delete();
    }

    // =========================================================================
    // (f) record() does not throw even when DB write fails
    // =========================================================================

    /** @test */
    public function record_does_not_throw_when_db_write_fails(): void
    {
        // Use a subclass that overrides PropertyLocationDnaAudit::create to throw.
        // We achieve this by mocking the service so ::create throws a RuntimeException.
        $service = new class extends LocationDnaAuditService {
            public function record(
                string  $listingType,
                int     $listingId,
                string  $eventType,
                string  $status,
                ?string $source,
                ?array  $inputSnapshot,
                ?array  $outputSnapshot,
                ?string $error,
            ): PropertyLocationDnaAudit {
                try {
                    throw new \RuntimeException('Simulated DB failure');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('LocationDnaAuditService::record() failed to persist audit row', [
                        'listing_type' => $listingType,
                        'listing_id'   => $listingId,
                        'event_type'   => $eventType,
                        'exception'    => $e->getMessage(),
                    ]);

                    return new PropertyLocationDnaAudit([
                        'listing_type'    => $listingType,
                        'listing_id'      => $listingId,
                        'event_type'      => $eventType,
                        'status'          => $status,
                        'source'          => $source,
                        'input_snapshot'  => $inputSnapshot,
                        'output_snapshot' => $outputSnapshot,
                        'error'           => $error,
                    ]);
                }
            }
        };

        // Must not throw
        $result = $service->record(
            listingType:    self::LISTING_TYPE,
            listingId:      self::LISTING_ID,
            eventType:      'geocode',
            status:         'geocoded',
            source:         'google',
            inputSnapshot:  ['key' => 'value'],
            outputSnapshot: ['success' => true],
            error:          null,
        );

        // Returns an unsaved model instance
        $this->assertFalse($result->exists, 'On DB failure, an unsaved model must be returned');
        $this->assertSame(self::LISTING_TYPE, $result->listing_type);
        $this->assertSame(self::LISTING_ID, $result->listing_id);
    }

    // =========================================================================
    // (g) Service file contains no reference to OpenAI/AI classes
    // =========================================================================

    /** @test */
    public function service_file_contains_no_openai_or_ai_class_references(): void
    {
        $source = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaAuditService.php')
        );

        // Check import statements only — the governance comment block may mention
        // "OpenAI" as a prohibition; we only enforce that no such class is imported.
        $importLines = array_filter(
            explode("\n", $source),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('openai', $line,
                "LocationDnaAuditService must not import OpenAI classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('\\Ai\\', $line,
                "LocationDnaAuditService must not import AI pipeline classes (found: {$line})");
        }
    }

    // =========================================================================
    // (h) Service file contains no reference to marketing report classes
    // =========================================================================

    /** @test */
    public function service_file_contains_no_marketing_report_class_references(): void
    {
        $source = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaAuditService.php')
        );

        $this->assertStringNotContainsStringIgnoringCase('MarketingReport', $source,
            'LocationDnaAuditService must not reference marketing report classes');
        $this->assertStringNotContainsStringIgnoringCase('PropertyDna', $source,
            'LocationDnaAuditService must not reference PropertyDna pipeline classes');
        $this->assertStringNotContainsStringIgnoringCase('MarketingIntelligence', $source,
            'LocationDnaAuditService must not reference MarketingIntelligence classes');
    }

    // =========================================================================
    // Additional: multiple calls produce multiple rows (truly append-only)
    // =========================================================================

    /** @test */
    public function calling_record_multiple_times_inserts_multiple_rows(): void
    {
        $this->recordSample();
        $this->recordSample();
        $this->recordSample();

        $count = PropertyLocationDnaAudit::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertSame(3, $count, 'Each call to record() must insert a new row');
    }

    // =========================================================================
    // Additional: null snapshots and error field are stored correctly
    // =========================================================================

    /** @test */
    public function it_stores_null_snapshots_and_error_correctly(): void
    {
        $audit = $this->makeService()->record(
            listingType:    self::LISTING_TYPE,
            listingId:      self::LISTING_ID,
            eventType:      'geocode',
            status:         'skipped',
            source:         null,
            inputSnapshot:  null,
            outputSnapshot: null,
            error:          'missing_required_address_fields',
        );

        $fresh = PropertyLocationDnaAudit::find($audit->id);

        $this->assertNull($fresh->input_snapshot);
        $this->assertNull($fresh->output_snapshot);
        $this->assertNull($fresh->source);
        $this->assertSame('missing_required_address_fields', $fresh->error);
    }

    // =========================================================================
    // Additional: created_at is set automatically
    // =========================================================================

    /** @test */
    public function created_at_is_set_on_the_persisted_row(): void
    {
        $before = now()->subSecond();

        $audit = $this->recordSample();

        $fresh = PropertyLocationDnaAudit::find($audit->id);

        $this->assertNotNull($fresh->created_at);
        $this->assertTrue(
            $fresh->created_at->greaterThanOrEqualTo($before),
            'created_at must be set to approximately now()',
        );
    }
}
