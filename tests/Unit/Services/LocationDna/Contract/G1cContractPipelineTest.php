<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Contract\LocationDnaRevisionToken;
use App\Services\LocationDna\Contract\LocationDnaSerializer;
use PHPUnit\Framework\TestCase;

/**
 * G1c — the inert pipeline end to end: hydrate → apply → serialize → tokenise.
 *
 * Nothing here persists anything. The applier is the pure, domain-local one the G1c
 * authorization permits; `LocationDnaPersistenceService` is deliberately NOT created in this
 * increment (D-G1-5), so there is no write path to exercise even accidentally.
 */
class G1cContractPipelineTest extends TestCase
{
    private LocationDnaHydrator $hydrator;
    private LocationDnaSerializer $serializer;
    private LocationDnaRevisionToken $token;
    private DimensionCommandApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hydrator   = new LocationDnaHydrator();
        $this->serializer = new LocationDnaSerializer();
        $this->token      = new LocationDnaRevisionToken();
        $this->applier    = new DimensionCommandApplier();
    }

    private function storedBlob(): array
    {
        return [
            'schema_version'  => 2,
            'cities'          => ['  Tampa  ', 'Orlando'],
            'counties'        => [],
            'state'           => 'FL',
            'polygons'        => [['label' => ' A ', 'path' => [
                ['lat' => 27.99, 'lng' => -82.49],
                ['lat' => 27.90, 'lng' => -82.40],
            ]]],
            'radius_searches' => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 3.5, 'address' => '1 Main St']],
            'location_notes'  => 'Private note.',
            'neighborhoods'   => ['Old Northeast'],
        ];
    }

    public function test_hydrate_normalize_serialize_is_deterministic(): void
    {
        $a = $this->serializer->toArray($this->hydrator->hydrate($this->storedBlob())->documentOrFail());
        $b = $this->serializer->toArray($this->hydrator->hydrate($this->storedBlob())->documentOrFail());

        $this->assertSame($a, $b);
    }

    public function test_serialize_then_hydrate_preserves_canonical_meaning(): void
    {
        $first  = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $json   = $this->serializer->toJson($first);
        $second = $this->hydrator->hydrate($json)->documentOrFail();

        $this->assertSame($first->toDimensionArray(), $second->toDimensionArray());
        $this->assertSame($first->presenceSet(), $second->presenceSet());
        $this->assertSame($first->extensions(), $second->extensions());
        $this->assertSame(
            $this->token->forDocument($first),
            $this->token->forDocument($second),
            'a full round trip must preserve the revision token',
        );
    }

    public function test_round_trip_preserves_polygon_vertex_order(): void
    {
        $doc     = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $roundTrip = $this->hydrator->hydrate($this->serializer->toJson($doc))->documentOrFail();

        $this->assertSame(
            [27.99, 27.90],
            array_column($roundTrip->value(Dimension::Polygons)[0]['path'], 'lat'),
        );
    }

    public function test_an_explicit_clear_produces_a_different_document_and_token(): void
    {
        $before = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $after  = $this->applier->apply($before, [DimensionCommand::clear(Dimension::Polygons)]);

        $this->assertTrue($before->isAuthored(Dimension::Polygons));
        $this->assertTrue($after->isCleared(Dimension::Polygons));
        $this->assertNotSame($this->token->forDocument($before), $this->token->forDocument($after));

        // And the clear survives serialisation as a present canonical empty.
        $this->assertSame([], $this->serializer->toArray($after)['polygons']);
    }

    public function test_no_command_leaves_the_document_and_token_unchanged(): void
    {
        $before = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $after  = $this->applier->apply($before, []);

        $this->assertSame($before->toDimensionArray(), $after->toDimensionArray());
        $this->assertSame($this->token->forDocument($before), $this->token->forDocument($after));
    }

    public function test_present_but_cleared_remains_authoritative_through_the_whole_pipeline(): void
    {
        // `counties` is stored cleared. It must stay cleared through hydrate → serialize →
        // hydrate, and must never revert to absent or acquire a value. This is the invariant the
        // live code violates for six of eight workflows (G1a's 6/2 baseline).
        $doc = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $this->assertTrue($doc->isCleared(Dimension::Counties));

        $again = $this->hydrator->hydrate($this->serializer->toJson($doc))->documentOrFail();

        $this->assertTrue($again->isCleared(Dimension::Counties));
        $this->assertFalse($again->isAbsent(Dimension::Counties));
        $this->assertSame([], $again->value(Dimension::Counties));
    }

    public function test_absent_stays_absent_through_the_whole_pipeline(): void
    {
        $doc = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $this->assertTrue($doc->isAbsent(Dimension::ZipCodes));

        $again = $this->hydrator->hydrate($this->serializer->toJson($doc))->documentOrFail();

        $this->assertTrue($again->isAbsent(Dimension::ZipCodes), 'omission survives; it did not become cleared');
        $this->assertFalse($again->isCleared(Dimension::ZipCodes));
    }

    public function test_withdrawn_neighborhoods_key_survives_the_pipeline_uninterpreted(): void
    {
        $doc   = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $again = $this->hydrator->hydrate($this->serializer->toJson($doc))->documentOrFail();

        $this->assertSame(['Old Northeast'], $again->extensions()['neighborhoods']);
        $this->assertNotContains('neighborhoods', $again->presenceSet());
    }

    public function test_a_set_command_then_round_trip_is_stable(): void
    {
        $before = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();
        $after  = $this->applier->apply($before, [DimensionCommand::set(Dimension::Cities, ['Clearwater'])]);

        $again = $this->hydrator->hydrate($this->serializer->toJson($after))->documentOrFail();

        $this->assertSame(['Clearwater'], $again->value(Dimension::Cities));
        $this->assertSame($this->token->forDocument($after), $this->token->forDocument($again));
    }

    public function test_the_pipeline_writes_nothing_and_needs_no_framework(): void
    {
        // Constructed directly with `new`, no container, no DB, no request. If any component
        // acquires a framework or persistence dependency, this test stops constructing.
        $doc = $this->hydrator->hydrate($this->storedBlob())->documentOrFail();

        $this->assertIsString($this->serializer->toJson($doc));
        $this->assertIsString($this->token->forDocument($doc));
    }
}
