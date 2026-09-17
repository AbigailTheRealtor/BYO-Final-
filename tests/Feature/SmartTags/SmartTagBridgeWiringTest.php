<?php

namespace Tests\Feature\SmartTags;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\SmartTags\SmartTagDerivationService;
use App\Services\SmartTags\SmartTagEvidenceWriter;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagWriteRefused;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use App\Support\SmartTags\SmartTagVersion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\Feature\SmartTags\Doubles\RecordingSmartTagLifecycle;
use Tests\Feature\SmartTags\Doubles\ThrowingDerivationService;
use Tests\Feature\SmartTags\Doubles\ThrowingSmartTagLifecycle;
use Tests\TestCase;

/**
 * Phase 2 — Bridge/MLS wiring.
 *
 * The single-record lookup seam derives; the bulk paths do not; the normalizer
 * itself stays a persistence primitive; and PublicRemarks is unreachable from
 * every direction.
 *
 * Feature tests here run against the shared dev database with transactional
 * rollback, so every identifier is synthetic and collision-proof.
 */
class SmartTagBridgeWiringTest extends TestCase
{
    use DatabaseTransactions;
    use WiresSmartTags;

    private const CITY = 'PhpunitSmartTagCity';

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function makeService(BridgeApiService $api): BridgeListingLookupService
    {
        return new BridgeListingLookupService(
            $api,
            new BridgePropertyNormalizer(),
            new BridgePropertyCandidateAdapter(),
        );
    }

    /**
     * A Residential record with structured fields the rules read, plus
     * PublicRemarks that the description parser WOULD match if it ever ran.
     */
    private function record(string $listingKey, array $overrides = []): array
    {
        return array_merge([
            'ListingKey'            => $listingKey,
            'ListingId'             => $listingKey . '-id',
            'StandardStatus'        => 'Active',
            'PropertyType'          => 'Residential',
            'ListPrice'             => 350000,
            'UnparsedAddress'       => '123 Smart Tag St',
            'City'                  => self::CITY,
            'StateOrProvince'       => 'FL',
            'PostalCode'            => '33601',
            'BedroomsTotal'         => 3,
            'BathroomsTotalInteger' => 2,
            'LivingArea'            => 1800,
            'ModificationTimestamp' => '2026-01-15T12:00:00Z',
            'WaterfrontYN'          => true,
            'PoolPrivateYN'         => true,
            'PublicRemarks'         => 'Stunning home with quartz countertops, a private pool, '
                                     . 'a three car garage and a fenced yard. Move in ready.',
        ], $overrides);
    }

    private function bridgeRow(string $listingKey): BridgeProperty
    {
        return BridgeProperty::query()->where('listing_key', $listingKey)->firstOrFail();
    }

    // ── single-record derivation ──────────────────────────────────────────

    /** @test */
    public function a_single_record_lookup_derives_structured_tags(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-NEW')]);

        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-NEW-id');

        $row = $this->bridgeRow('PHPUNIT-ST-NEW');

        $this->assertNotEmpty(
            $this->evidenceFor('bridge', $row->id, 'structured_mls'),
            'A single-record Bridge lookup derived no structured evidence.'
        );

        $state = SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->first();

        $this->assertNotNull($state);
        $this->assertSame('residential.sale', $state->context);
    }

    /** @test */
    public function changed_structured_data_retags(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-CHANGE')]);

        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-CHANGE-id');

        $row = $this->bridgeRow('PHPUNIT-ST-CHANGE');
        $firstHash = SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)
            ->value('structured_inputs_hash');

        $this->assertNotNull($firstHash, 'The first derivation recorded no structured hash.');

        // A structured field the rules read actually moves.
        (new BridgePropertyNormalizer())->upsert(
            $this->record('PHPUNIT-ST-CHANGE', ['WaterfrontYN' => false, 'PoolPrivateYN' => false])
        );

        $after = app(SmartTagLifecycle::class)->deriveForBridgeSilently($row->fresh());

        $this->assertNotNull($after);
        $this->assertTrue($after->structuredDerived, 'A changed structured field did not retag.');
        $this->assertNotSame($firstHash, SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)
            ->value('structured_inputs_hash'));
    }

    /**
     * The staleness guarantee, across the two provenances that used to disagree.
     *
     * A Bridge row is derived from the model `updateOrCreate()` returned by the
     * lookup seam (write-shaped) and re-read fresh by the backfill (read-shaped).
     * Those must produce the same structured hash, or each path re-derives what
     * the other already did.
     *
     * This was blocked until PR #176. `BridgeRecordAccessor::inputsFor()` hashed
     * RAW attribute values, so `waterfront_yn` and `pool_private_yn` hashed as
     * PHP `true` after a write and int `1` after a re-read — different canonical
     * JSON, different SHA-256, for a row whose data had not changed. The accessor
     * now hashes the value as the RULE interprets it, so the two agree.
     *
     * @test
     */
    public function identical_bridge_input_skips_rederivation(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-SKIP')]);

        // Derives from the write-shaped model the upsert returned.
        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-SKIP-id');

        $row = $this->bridgeRow('PHPUNIT-ST-SKIP');

        // Derives again from a read-shaped row, as the backfill does.
        $unchanged = app(SmartTagLifecycle::class)->deriveForBridgeSilently($row->fresh());

        $this->assertNotNull($unchanged);
        $this->assertFalse($unchanged->structuredDerived,
            'An unchanged Bridge row was re-derived across the write/read provenance boundary.');
    }

    /**
     * The same row derived twice from the SAME provenance is stable, which is
     * what localises the defect to the write/read representation rather than to
     * the hashing itself.
     *
     * @test
     */
    public function two_reads_of_one_bridge_row_hash_identically(): void
    {
        (new BridgePropertyNormalizer())->upsert($this->record('PHPUNIT-ST-STABLE'));
        $row = $this->bridgeRow('PHPUNIT-ST-STABLE');

        app(SmartTagLifecycle::class)->deriveForBridgeSilently($row->fresh());

        $second = app(SmartTagLifecycle::class)->deriveForBridgeSilently($row->fresh());

        $this->assertNotNull($second);
        $this->assertFalse($second->structuredDerived,
            'Two reads of one unchanged Bridge row produced different structured hashes.');
    }

    /** @test */
    public function a_byte_identical_reimport_does_not_reach_derivation_at_all(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-IDENTICAL')]);

        $service = $this->makeService($api);
        $service->findByMlsNumber('PHPUNIT-ST-IDENTICAL-id');

        $row = $this->bridgeRow('PHPUNIT-ST-IDENTICAL');
        $derivedAt = SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->value('derived_at');

        // A COUNTING double, not a throwing one: the call sites go through the
        // non-throwing shim, so a throw would be swallowed and prove nothing.
        $spy = new RecordingSmartTagLifecycle();
        $this->app->instance(SmartTagLifecycle::class, $spy);

        $this->makeService($api)->refreshByListingKey('PHPUNIT-ST-IDENTICAL');

        $this->assertSame(0, $spy->countOf('bridge'),
            'An unchanged Bridge payload still reached derivation.');
        $this->assertEquals($derivedAt, SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->value('derived_at'));
    }

    /** @test */
    public function every_bridge_property_type_resolves_to_its_own_context(): void
    {
        $map = [
            'Residential'          => 'residential.sale',
            'Income'               => 'income.sale',
            'Commercial Sale'      => 'commercial.sale',
            'Business Opportunity' => 'business.sale',
            'Vacant Land'          => 'land.sale',
            'Residential Lease'    => 'residential.lease',
            'Commercial Lease'     => 'commercial.lease',
        ];

        foreach ($map as $propertyType => $expected) {
            $key = 'PHPUNIT-ST-CTX-' . substr(md5($propertyType), 0, 8);

            (new BridgePropertyNormalizer())->upsert($this->record($key, ['PropertyType' => $propertyType]));
            $row = $this->bridgeRow($key);

            $outcome = app(SmartTagLifecycle::class)->deriveForBridgeSilently($row);

            $this->assertNotNull($outcome, "{$propertyType} produced no outcome.");
            $this->assertSame($expected, $outcome->context?->value, "{$propertyType} resolved to the wrong context.");
        }
    }

    /** @test */
    public function an_unrecognised_bridge_property_type_derives_nothing(): void
    {
        (new BridgePropertyNormalizer())->upsert($this->record('PHPUNIT-ST-UNKNOWN', ['PropertyType' => 'Houseboat']));

        $outcome = app(SmartTagLifecycle::class)->deriveForBridgeSilently($this->bridgeRow('PHPUNIT-ST-UNKNOWN'));

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->derived);
        $this->assertSame(\App\Services\SmartTags\DerivationOutcome::NO_CONTEXT, $outcome->skippedReason);
        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'bridge')
            ->where('listing_id', $this->bridgeRow('PHPUNIT-ST-UNKNOWN')->id)
            ->count());
    }

    // ── PublicRemarks, from every direction ───────────────────────────────

    /** @test */
    public function public_remarks_never_becomes_evidence_however_matchable_it_is(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-REMARKS')]);

        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-REMARKS-id');

        $row = $this->bridgeRow('PHPUNIT-ST-REMARKS');

        // The fixture's remarks name four taggable features in plain language.
        $this->assertStringContainsString('quartz countertops', $row->raw_json);

        $this->assertSame([], $this->evidenceFor('bridge', $row->id, 'mls_remarks'),
            'MLS PublicRemarks produced Smart Tag evidence.');

        $this->assertSame(0, SmartTagEvidence::query()->where('source', 'mls_remarks')->count(),
            'An mls_remarks row exists anywhere in the database.');
    }

    /** @test */
    public function the_derivation_outcome_reports_that_remarks_were_refused(): void
    {
        (new BridgePropertyNormalizer())->upsert($this->record('PHPUNIT-ST-REMARKS-NOTE'));

        $outcome = app(SmartTagLifecycle::class)->deriveForBridgeSilently($this->bridgeRow('PHPUNIT-ST-REMARKS-NOTE'));

        $this->assertNotNull($outcome);
        $this->assertContains(\App\Services\SmartTags\DerivationOutcome::MLS_REMARKS_NOT_APPROVED, $outcome->notes,
            'The refusal is not observable — it can only be assumed.');
        $this->assertNull(SmartTagDerivationState::query()
            ->where('listing_type', 'bridge')
            ->where('listing_id', $this->bridgeRow('PHPUNIT-ST-REMARKS-NOTE')->id)
            ->value('mls_remarks_hash'),
            'A remarks hash was recorded as seen.');
    }

    /** @test */
    public function the_evidence_writer_refuses_remarks_evidence_outright(): void
    {
        (new BridgePropertyNormalizer())->upsert($this->record('PHPUNIT-ST-REMARKS-WRITE'));
        $row = $this->bridgeRow('PHPUNIT-ST-REMARKS-WRITE');

        $this->expectException(SmartTagWriteRefused::class);

        app(SmartTagEvidenceWriter::class)->replaceDerived(
            SmartTagListingRef::fromModel($row),
            SmartTagContext::ResidentialSale,
            SmartTagSource::MlsRemarks,
            [],
            SmartTagVersion::taggerVersion(),
        );
    }

    /** @test */
    public function the_remarks_approval_constants_are_still_false(): void
    {
        $this->assertFalse(SmartTagDerivationService::MLS_REMARKS_PROCESSING_APPROVED);
        $this->assertFalse(SmartTagEvidenceWriter::MLS_REMARKS_PERSISTENCE_APPROVED);
    }

    // ── failure isolation ─────────────────────────────────────────────────

    /** @test */
    public function a_smart_tag_failure_does_not_fail_or_roll_back_a_bridge_upsert(): void
    {
        // Two independent faults, because two independent guards must hold:
        // the derivation service throwing (the facade's own catch), and the
        // facade itself throwing (the static shim's catch, which also covers a
        // container that cannot build it at all).
        $this->app->bind(\App\Services\SmartTags\SmartTagDerivationService::class,
            fn () => new ThrowingDerivationService());
        $this->app->bind(SmartTagLifecycle::class, fn () => new ThrowingSmartTagLifecycle());

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-FAIL')]);

        $candidate = $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-FAIL-id');

        $this->assertNotNull($candidate, 'A Smart Tag failure took the lookup down with it.');
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-ST-FAIL']);

        $row = $this->bridgeRow('PHPUNIT-ST-FAIL');
        $this->assertSame('Residential', $row->property_type, 'The Bridge row was rolled back.');
        $this->assertNotNull($row->raw_json);
    }

    // ── the gates ─────────────────────────────────────────────────────────

    /** @test */
    public function with_the_bridge_gate_closed_a_lookup_derives_nothing(): void
    {
        $this->enableSmartTags(master: true, bridge: false);

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-GATE-B')]);

        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-GATE-B-id');

        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-ST-GATE-B']);
        $this->assertSame(0, SmartTagEvidence::query()->count(),
            'The Bridge gate was closed and evidence was still written.');
    }

    /** @test */
    public function the_master_gate_closes_bridge_derivation_even_with_the_bridge_gate_open(): void
    {
        $this->enableSmartTags(master: false, bridge: true);

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([$this->record('PHPUNIT-ST-GATE-M')]);

        $this->makeService($api)->findByMlsNumber('PHPUNIT-ST-GATE-M-id');

        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-ST-GATE-M']);
        $this->assertSame(0, SmartTagEvidence::query()->count(),
            'The Bridge gate alone enabled derivation.');
    }

    /** @test */
    public function native_derivation_is_unaffected_by_the_bridge_gate(): void
    {
        $this->enableSmartTags(master: true, bridge: false);

        $this->assertTrue(\App\Support\SmartTags\SmartTagWiring::enabledFor(SmartTagListingType::SellerAgent));
        $this->assertTrue(\App\Support\SmartTags\SmartTagWiring::enabledFor(SmartTagListingType::LandlordAgent));
        $this->assertFalse(\App\Support\SmartTags\SmartTagWiring::enabledFor(SmartTagListingType::Bridge));
    }

    // ── high-volume paths ─────────────────────────────────────────────────

    /** @test */
    public function the_bulk_importer_derives_nothing_when_a_caller_opts_out(): void
    {
        $spy = new RecordingSmartTagLifecycle();
        $this->app->instance(SmartTagLifecycle::class, $spy);

        $result = (new BridgePropertyNormalizer())->upsert($this->record('PHPUNIT-ST-BULK'));

        $this->assertNotNull($result);
        $this->assertSame(0, $spy->countOf('bridge'),
            'The normalizer derived Smart Tags — it must stay a persistence primitive.');
        $this->assertSame(0, SmartTagEvidence::query()->count());
    }

    /** @test */
    public function the_cli_importer_does_not_derive_without_its_flag(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/ImportBridgeProperties.php'));

        // The option exists, defaults off, and guards the call.
        $this->assertStringContainsString('--derive-smart-tags', $source);
        $this->assertStringContainsString("\$this->option('derive-smart-tags')", $source);

        // And the option is a plain flag, so its default is false.
        $this->assertDoesNotMatchRegularExpression('/derive-smart-tags=/', $source,
            'The CLI flag takes a value, so its default is no longer "off".');
    }
}
