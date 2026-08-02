<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use PHPUnit\Framework\TestCase;

/**
 * G1f-1 — the pure write-side mirror projection.
 *
 * Every assertion here is about a PURE function: canonical document in, mirror map out. There is
 * no database, no model and no framework, which is the point — if a mirror value can be wrong,
 * it must be provable without a save path.
 */
class LegacyMirrorProjectionTest extends TestCase
{
    private LegacyMirrorProjection $projection;

    private DimensionCommandApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projection = new LegacyMirrorProjection();
        $this->applier    = new DimensionCommandApplier();
    }

    /** @param list<DimensionCommand> $commands */
    private function documentWith(array $commands): LocationDnaDocument
    {
        return $this->applier->apply(LocationDnaDocument::emptyDocument(), $commands);
    }

    public function test_cities_project_as_a_json_encoded_list(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa', 'Orlando']),
        ]));

        $this->assertSame('["Tampa","Orlando"]', $mirrors['cities']);
    }

    public function test_counties_project_as_a_json_encoded_list(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::Counties, ['Hillsborough']),
        ]));

        $this->assertSame('["Hillsborough"]', $mirrors['counties']);
    }

    /**
     * D-G1F-4 4S-i · `state` is a RAW string, not JSON-encoded.
     *
     * The decision that keeps `BuyerAgentAuction` encoding-neutral through its migration, and the
     * one that would silently change stored data for Tenant Criteria records if reversed.
     */
    public function test_state_projects_as_a_raw_string_not_json(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::State, 'FL'),
        ]));

        $this->assertSame('FL', $mirrors['state']);
        $this->assertNotSame('"FL"', $mirrors['state']);
    }

    /** D-G1-4 4-A · a cleared collection mirrors as an empty JSON list. */
    public function test_cleared_cities_and_counties_project_as_empty_lists(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::clear(Dimension::Cities),
            DimensionCommand::clear(Dimension::Counties),
        ]));

        $this->assertSame('[]', $mirrors['cities']);
        $this->assertSame('[]', $mirrors['counties']);
    }

    /**
     * D-G1-4 4-A · a cleared `state` mirrors as the empty string.
     *
     * This is the half that changes real behaviour: today a cleared state leaves the previous
     * value standing in the mirror on every workflow.
     */
    public function test_cleared_state_projects_as_an_empty_string(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::clear(Dimension::State),
        ]));

        $this->assertArrayHasKey('state', $mirrors);
        $this->assertSame('', $mirrors['state']);
    }

    /**
     * THE NO-OP GUARANTEE · an absent dimension produces NO key.
     *
     * The single most important assertion in this suite. Because the caller writes only the keys
     * the map contains, an absent dimension results in no mirror write at all — which is what
     * stops a save from overwriting a legacy-only mirror it knows nothing about.
     */
    public function test_absent_dimensions_invent_no_mirror_keys(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
        ]));

        $this->assertSame(['cities'], array_keys($mirrors));
        $this->assertArrayNotHasKey('counties', $mirrors);
        $this->assertArrayNotHasKey('state', $mirrors);
    }

    public function test_an_empty_document_projects_no_mirrors_at_all(): void
    {
        $this->assertSame([], $this->projection->project(LocationDnaDocument::emptyDocument()));
    }

    /** D-G1F-4 (a) · `zipCodes` is out of scope for G1f-1 and never emitted. */
    public function test_zipcodes_is_never_emitted_even_when_the_dimension_is_authored(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::ZipCodes, ['33701']),
        ]));

        $this->assertArrayNotHasKey('zipCodes', $mirrors);
        $this->assertArrayNotHasKey('zip_codes', $mirrors);
        $this->assertSame([], $mirrors, 'an authored zip_codes dimension alone produces no managed mirror');
    }

    /** §17.5 · the plural `states` key is a legacy dead write and is never emitted. */
    public function test_the_plural_states_key_is_never_emitted(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::State, 'FL'),
        ]));

        $this->assertArrayNotHasKey('states', $mirrors);
    }

    /** Only the three approved keys can ever appear. */
    public function test_only_the_managed_keys_can_be_emitted(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::set(Dimension::Counties, ['Hillsborough']),
            DimensionCommand::set(Dimension::State, 'FL'),
            DimensionCommand::set(Dimension::ZipCodes, ['33701']),
            DimensionCommand::set(Dimension::LocationNotes, 'private note'),
            DimensionCommand::set(Dimension::FlexibleLocation, true),
        ]));

        $this->assertSame(LegacyMirrorProjection::MANAGED_KEYS, array_keys($mirrors));
    }

    /** Geometry and notes never reach a mirror — they have no discrete counterpart. */
    public function test_geometry_and_notes_never_reach_a_mirror(): void
    {
        $mirrors = $this->projection->project($this->documentWith([
            DimensionCommand::set(Dimension::Polygons, [['label' => 'A', 'path' => [['lat' => 27.9, 'lng' => -82.4]]]]),
            DimensionCommand::set(Dimension::LocationNotes, 'sensitive note'),
        ]));

        $this->assertSame([], $mirrors);
        $this->assertStringNotContainsString('sensitive', json_encode($mirrors));
    }

    public function test_projection_is_deterministic_across_repeated_calls(): void
    {
        $document = $this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::set(Dimension::State, 'FL'),
        ]);

        $this->assertSame($this->projection->project($document), $this->projection->project($document));
    }

    public function test_projection_does_not_mutate_the_document(): void
    {
        $document = $this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
        ]);

        $before = $document->toDimensionArray();
        $this->projection->project($document);

        $this->assertSame($before, $document->toDimensionArray());
    }
}
