<?php

namespace Tests\Feature\Spatial;

use App\Services\LocationDna\Capability\LocationDnaAccessContext;
use App\Services\LocationDna\Capability\LocationDnaCapabilityResolver;
use App\Services\LocationDna\Capability\LocationDnaCapabilitySet;
use App\Services\LocationDna\Capability\LocationDnaPurpose;
use App\Services\LocationDna\Capability\LocationDnaSurface;
use App\Services\LocationDna\Capability\LocationDnaViewerRelationship;
use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Persistence\LocationDnaCommandBuilder;
use App\Services\LocationDna\Persistence\LocationDnaPersistenceService;
use App\Services\LocationDna\Persistence\LocationDnaWritableRecord;
use App\Models\BuyerAgentAuction;
use App\Models\User;
use App\Services\LocationDna\Provenance\ProvenanceActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * G1f-1 — the canonical writer.
 *
 * WHAT THIS SUITE IS FOR
 * ----------------------
 * `LocationDnaPersistenceService` is the class D-G1-5 names the sole canonical writer, and the
 * class §10.2's binding constraint is about. Its correctness is not a matter of taste: several of
 * the assertions below are the mechanical proof that the constraint holds, and the G1f-1
 * authorization makes them stop conditions rather than nice-to-haves.
 *
 * The record is an in-memory fake implementing {@see LocationDnaWritableRecord}. That is
 * deliberate: the service's contract is entirely expressible through three port methods, so a fake
 * proves it without dragging Eloquent into a unit-level concern. The REAL model path is proven
 * separately by {@see G1f1BuyerAgentAuctionMigrationTest}, which drives the actual save.
 *
 * A `DatabaseTransactions` connection is still required because the service opens a transaction.
 */
class G1f1LocationDnaPersistenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LocationDnaPersistenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationDnaPersistenceService();
    }

    private function ownerCapabilities(): LocationDnaCapabilitySet
    {
        return (new LocationDnaCapabilityResolver())->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            authenticated: true,
        ));
    }

    private function publicCapabilities(): LocationDnaCapabilitySet
    {
        return (new LocationDnaCapabilityResolver())->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        ));
    }

    private function record(mixed $canonical = false): FakeLocationDnaRecord
    {
        return new FakeLocationDnaRecord($canonical);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SET / CLEAR
    // ═════════════════════════════════════════════════════════════════════════

    public function test_set_one_dimension_writes_canonical_state_and_its_mirror(): void
    {
        $record = $this->record();

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isChanged());
        $this->assertSame(['Tampa'], json_decode((string) $record->canonical, true)['cities']);
        $this->assertSame('["Tampa"]', $record->mirrors['cities']);
    }

    public function test_clear_one_dimension_records_present_but_empty_and_clears_the_mirror(): void
    {
        $record = $this->record(json_encode(['cities' => ['Tampa'], 'schema_version' => 2]));

        $result = $this->service->apply(
            $record,
            [DimensionCommand::clear(Dimension::Cities)],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isChanged());

        $decoded = json_decode((string) $record->canonical, true);
        $this->assertArrayHasKey('cities', $decoded, 'a clear is PRESENT-but-empty, not absent');
        $this->assertSame([], $decoded['cities']);
        $this->assertSame('[]', $record->mirrors['cities']);
    }

    public function test_multiple_commands_apply_atomically_in_one_result(): void
    {
        $record = $this->record();

        $result = $this->service->apply(
            $record,
            [
                DimensionCommand::set(Dimension::Cities, ['Tampa']),
                DimensionCommand::set(Dimension::Counties, ['Hillsborough']),
                DimensionCommand::set(Dimension::State, 'FL'),
            ],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isChanged());
        $this->assertSame(1, $record->canonicalWrites, 'exactly one canonical write for the batch');
        $this->assertSame(['cities', 'counties', 'state'], array_keys($record->mirrors));
        $this->assertSame('FL', $record->mirrors['state'], 'raw string, per 4S-i');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // NO-OP — the binding stop condition
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * BINDING · an empty batch writes nothing at all.
     *
     * The stop condition of the G1f-1 authorization, and the mechanical proof of the §10.2
     * constraint. If this fails, provenance persistence becomes a prerequisite and G1f-1 is not
     * viable in its approved form.
     */
    public function test_an_empty_batch_writes_nothing_and_reports_a_no_op(): void
    {
        $record = $this->record(json_encode(['cities' => ['Tampa'], 'schema_version' => 2]));

        $result = $this->service->apply($record, [], $this->ownerCapabilities(), ProvenanceActor::ExplicitOwner);

        $this->assertTrue($result->isNoOp());
        $this->assertSame(0, $record->canonicalWrites);
        $this->assertSame([], $record->mirrors);
        $this->assertSame(0, $record->reads, 'a no-op does not even read the record');
    }

    /**
     * BINDING · a legacy-only record gets no canonical blob from a commandless save.
     *
     * The concrete form of the promotion hazard §10.3 describes. A record whose values live only
     * in legacy mirrors must not acquire a canonical document — and therefore an S2 stamp —
     * merely because some unrelated part of the workflow saved.
     */
    public function test_a_commandless_save_creates_no_blob_for_a_legacy_only_record(): void
    {
        $record = $this->record(false); // `info()` returns false for an unwritten key

        $result = $this->service->apply($record, [], $this->ownerCapabilities(), ProvenanceActor::ExplicitOwner);

        $this->assertTrue($result->isNoOp());
        $this->assertNull($record->canonical, 'no canonical document may be created');
        $this->assertSame([], $record->mirrors, 'and no mirror may be rewritten');
    }

    /** Commands that change no canonical meaning are a no-op, decided by the revision token. */
    public function test_commands_that_change_nothing_are_a_no_op(): void
    {
        $record = $this->record(json_encode(['cities' => ['Tampa'], 'schema_version' => 2]));

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isNoOp());
        $this->assertSame(0, $record->canonicalWrites);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REJECTION
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_denied_capability_rejects_and_writes_nothing(): void
    {
        $record = $this->record();

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->publicCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isRejected());
        $this->assertSame('capability_denied', $result->errorCode);
        $this->assertSame(0, $record->canonicalWrites, 'a denial is never a skipped write — it is no write');
        $this->assertSame([], $record->mirrors);
    }

    /** `subject_property` is read-only (§17 G8), so a command for it is refused. */
    public function test_a_dimension_without_a_mutation_grant_is_rejected(): void
    {
        $record = $this->record();

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::SubjectProperty, ['line1' => '1 Main St'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isRejected());
        $this->assertSame('capability_denied', $result->errorCode);
        $this->assertSame(0, $record->canonicalWrites);
    }

    public function test_a_non_command_in_the_batch_is_rejected(): void
    {
        $record = $this->record();

        /** @phpstan-ignore-next-line deliberate contract violation */
        $result = $this->service->apply($record, ['not a command'], $this->ownerCapabilities(), ProvenanceActor::ExplicitOwner);

        $this->assertTrue($result->isRejected());
        $this->assertSame('invalid_value', $result->errorCode);
        $this->assertSame(0, $record->canonicalWrites);
    }

    /**
     * A malformed stored document is quarantined, never overwritten and never cleared.
     *
     * L5: bytes we could not interpret must not be destroyed by the next save.
     */
    public function test_a_malformed_stored_document_is_rejected_and_preserved(): void
    {
        $record = $this->record('{"cities": [');

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isRejected());
        $this->assertSame('unavailable', $result->errorCode);
        $this->assertSame('{"cities": [', $record->canonical, 'the corrupt bytes survive intact');
        $this->assertSame([], $record->mirrors);
    }

    /** §5.5 · a higher schema version is read-only and refuses to be rewritten. */
    public function test_an_unsupported_higher_schema_version_is_rejected_read_only(): void
    {
        $stored = json_encode(['cities' => ['Tampa'], 'schema_version' => 99]);
        $record = $this->record($stored);

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Orlando'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertTrue($result->isRejected());
        $this->assertSame('unknown_schema_version', $result->errorCode);
        $this->assertSame($stored, $record->canonical);
    }

    /** A non-owner actor may not establish an owner-stated provenance kind. */
    public function test_a_non_owner_actor_is_rejected_on_provenance_grounds(): void
    {
        $record = $this->record();

        $result = $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::AutomaticSystem,
        );

        $this->assertTrue($result->isRejected());
        $this->assertSame('provenance_forbidden', $result->errorCode);
        $this->assertSame(0, $record->canonicalWrites);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // NO LEGACY REPAIR · NO PROVENANCE PERSISTENCE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * BINDING · the service performs no legacy repair, and cannot.
     *
     * It never reads a mirror — the port exposes no reader — so a stale mirror cannot influence
     * what is written, and no mirror is "repaired" as a side effect of an unrelated command.
     */
    public function test_no_legacy_repair_occurs_and_unrelated_mirrors_are_untouched(): void
    {
        $record = $this->record(json_encode(['state' => 'FL', 'schema_version' => 2]));

        $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::State, 'GA')],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertSame(['state'], array_keys($record->mirrors), 'only the commanded dimension mirrors');
        $this->assertArrayNotHasKey('cities', $record->mirrors, 'an uncommanded legacy mirror is not repaired');
    }

    /** BINDING · nothing provenance-shaped is ever persisted. */
    public function test_no_provenance_is_persisted_by_the_canonical_writer(): void
    {
        $record = $this->record();

        $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $written = (string) $record->canonical.json_encode($record->mirrors);

        foreach (['provenance', 'owner_authored', 'legacy_fallback', 'actor', 'explicit_owner'] as $token) {
            $this->assertStringNotContainsString($token, $written, "`{$token}` must never be persisted");
        }

        $this->assertSame(
            ['cities'],
            array_keys(array_diff_key(json_decode((string) $record->canonical, true), ['schema_version' => null])),
            'the document carries dimensions and a schema version, nothing else'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REVISION TOKEN · MUTATION
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_revision_token_is_returned_and_changes_with_meaning(): void
    {
        $first = $this->record();
        $a     = $this->service->apply(
            $first,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $second = $this->record();
        $b      = $this->service->apply(
            $second,
            [DimensionCommand::set(Dimension::Cities, ['Orlando'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertNotNull($a->revisionToken);
        $this->assertStringStartsWith('ldna-r1:', $a->revisionToken);
        $this->assertNotSame($a->revisionToken, $b->revisionToken);
    }

    public function test_the_service_does_not_mutate_the_supplied_commands(): void
    {
        $command  = DimensionCommand::set(Dimension::Cities, ['Tampa']);
        $snapshot = [$command->dimension->value, $command->value()];

        $this->service->apply($this->record(), [$command], $this->ownerCapabilities(), ProvenanceActor::ExplicitOwner);

        $this->assertSame($snapshot, [$command->dimension->value, $command->value()]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TRANSACTION · rollback proofs
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * ROLLBACK · a failing mirror write rolls the canonical write back with it.
     *
     * The proof that blob and mirror cannot be left disagreeing by a partial save — the failure
     * mode §7.2 measured in `BuyerOfferListingEdit` and §22.5 defect 1 measured in the Hire
     * Tenant path. The fake fails on the second mirror write, so the canonical write and the
     * first mirror write are already issued when the failure lands.
     */
    public function test_a_failing_mirror_write_rolls_back_the_canonical_write(): void
    {
        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'rollback',
            'is_draft' => true, 'is_approved' => true, 'is_sold' => false,
        ]);
        $auction->save();

        $record = new FailingRealRecord($auction, failOnMirror: 'state');

        try {
            $this->service->apply(
                $record,
                [
                    DimensionCommand::set(Dimension::Cities, ['Tampa']),
                    DimensionCommand::set(Dimension::State, 'FL'),
                ],
                $this->ownerCapabilities(),
                ProvenanceActor::ExplicitOwner,
            );
            $this->fail('the mirror failure must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('induced mirror failure', $e->getMessage());
        }

        // Read straight from the database. The canonical write and the cities mirror were both
        // ALREADY ISSUED when the state mirror threw, so if the transaction did not roll back
        // they would still be here.
        $fresh = BuyerAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertFalse(
            $fresh->info('location_dna_preferences'),
            'the canonical write must have been rolled back by the failing mirror write'
        );
        $this->assertFalse(
            $fresh->info('cities'),
            'and so must the mirror written before the failure — no partial state may remain'
        );
    }

    /** ROLLBACK · a failing canonical write leaves every mirror untouched. */
    public function test_a_failing_canonical_write_leaves_mirrors_unchanged(): void
    {
        $owner   = User::factory()->create(['user_type' => 'buyer']);
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'rollback',
            'is_draft' => true, 'is_approved' => true, 'is_sold' => false,
        ]);
        $auction->save();
        $auction->saveMeta('cities', '["Clearwater"]');

        $record = new FailingRealRecord($auction, failOnCanonical: true);

        try {
            $this->service->apply(
                $record,
                [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
                $this->ownerCapabilities(),
                ProvenanceActor::ExplicitOwner,
            );
            $this->fail('the canonical failure must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('induced canonical failure', $e->getMessage());
        }

        $fresh = BuyerAgentAuction::with('meta')->findOrFail($auction->id);

        $this->assertSame(
            '["Clearwater"]',
            (string) $fresh->info('cities'),
            'canonical is written FIRST, so no mirror was reached and the pre-existing value stands'
        );
        $this->assertFalse($fresh->info('location_dna_preferences'));
    }

    /** ORDER · canonical state is written before any mirror. */
    public function test_canonical_state_is_written_before_any_mirror(): void
    {
        $record = $this->record();

        $this->service->apply(
            $record,
            [DimensionCommand::set(Dimension::Cities, ['Tampa'])],
            $this->ownerCapabilities(),
            ProvenanceActor::ExplicitOwner,
        );

        $this->assertSame(['canonical', 'mirror:cities'], $record->writeLog);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COMMAND BUILDER · absence means no operation
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_builder_emits_no_command_for_an_absent_empty_or_unparseable_payload(): void
    {
        $builder = new LocationDnaCommandBuilder();

        foreach ([null, false, '', 'not json', '[1,2]', '0'] as $payload) {
            $this->assertSame(
                [],
                $builder->fromEditorPayload($payload),
                'a payload stating nothing must produce no commands: '.var_export($payload, true)
            );
        }
    }

    public function test_the_builder_distinguishes_present_empty_from_absent(): void
    {
        $commands = (new LocationDnaCommandBuilder())
            ->fromEditorPayload(json_encode(['cities' => [], 'state' => 'FL']));

        $byDimension = [];
        foreach ($commands as $c) {
            $byDimension[$c->dimension->value] = $c->isClear() ? 'clear' : 'set';
        }

        $this->assertSame('clear', $byDimension['cities'], 'present-but-empty is an explicit clear');
        $this->assertSame('set', $byDimension['state']);
        $this->assertArrayNotHasKey('counties', $byDimension, 'an absent key produces no command');
    }
}

/**
 * An in-memory {@see LocationDnaWritableRecord} with induced-failure hooks.
 *
 * Records the write order so "canonical first" is provable, and counts reads so a no-op can be
 * shown to touch nothing at all.
 */
class FakeLocationDnaRecord implements LocationDnaWritableRecord
{
    public ?string $canonical;

    /** @var array<string, string> */
    public array $mirrors = [];

    /** @var list<string> */
    public array $writeLog = [];

    public int $canonicalWrites = 0;

    public int $reads = 0;

    public bool $rolledBack = false;

    public function __construct(
        private readonly mixed $stored = false,
        private readonly ?string $failOnMirror = null,
        private readonly bool $failOnCanonical = false,
    ) {
        $this->canonical = is_string($stored) ? $stored : null;
    }

    public function readCanonical(): mixed
    {
        $this->reads++;

        return $this->stored;
    }

    public function writeCanonical(string $json): void
    {
        if ($this->failOnCanonical) {
            $this->rolledBack = true;
            throw new RuntimeException('induced canonical failure');
        }

        $this->canonicalWrites++;
        $this->writeLog[] = 'canonical';
        $this->canonical  = $json;
    }

    public function writeMirror(string $key, string $value): void
    {
        if ($this->failOnMirror === $key) {
            $this->rolledBack = true;
            throw new RuntimeException('induced mirror failure');
        }

        $this->writeLog[]     = 'mirror:'.$key;
        $this->mirrors[$key]  = $value;
    }
}

/**
 * A {@see LocationDnaWritableRecord} backed by a REAL model, with induced-failure hooks.
 *
 * Used only by the rollback tests. The in-memory fake cannot prove a transaction — it would only
 * prove that a flag it sets itself was set — so the rollback assertions read the database instead.
 */
class FailingRealRecord implements LocationDnaWritableRecord
{
    public function __construct(
        private readonly object $model,
        private readonly ?string $failOnMirror = null,
        private readonly bool $failOnCanonical = false,
    ) {
    }

    public function readCanonical(): mixed
    {
        return $this->model->info('location_dna_preferences');
    }

    public function writeCanonical(string $json): void
    {
        if ($this->failOnCanonical) {
            throw new RuntimeException('induced canonical failure');
        }

        $this->model->saveMeta('location_dna_preferences', $json);
    }

    public function writeMirror(string $key, string $value): void
    {
        $this->model->saveMeta($key, $value);

        if ($this->failOnMirror === $key) {
            throw new RuntimeException('induced mirror failure');
        }
    }
}
