<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\Boundary\FemaFloodCoverageBoundarySource;
use App\Services\Spatial\Boundary\FemaFloodZoneBoundarySource;
use App\Services\Spatial\BoundaryImportAcceptance;
use Tests\TestCase;

/**
 * Batch 2D Part C3c — the two FEMA NFHL boundary source adapters (shared base, D-C3c-1).
 * Pure; no DB, no network, no download.
 */
class FemaNfhlBoundarySourceTest extends TestCase
{
    private function polygon(float $lon = -82.0, float $lat = 27.0): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lon, $lat], [$lon, $lat + 0.01], [$lon + 0.01, $lat + 0.01], [$lon + 0.01, $lat], [$lon, $lat]]]];
    }

    /** @test */
    public function each_adapter_declares_its_source_key_and_kind(): void
    {
        $zone = new FemaFloodZoneBoundarySource();
        $coverage = new FemaFloodCoverageBoundarySource();

        $this->assertSame(['fema_flood_zone', 'flood_zone'], [$zone->sourceKey(), $zone->kind()]);
        $this->assertSame(['fema_flood_coverage', 'flood_coverage'], [$coverage->sourceKey(), $coverage->kind()]);
    }

    /** @test */
    public function flood_zone_keys_on_fld_ar_id_wraps_polygon_and_builds_attrs(): void
    {
        $result = (new FemaFloodZoneBoundarySource())->normalize([
            ['fld_ar_id' => '12103C-0001', 'fld_zone' => 'AE', 'zone_subty' => null, 'sfha_tf' => 'T', 'dfirm_id' => '12103C', 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(1, $result->records);
        $r = $result->records[0];
        $this->assertSame('flood_zone', $r->kind);
        $this->assertSame('12103C-0001', $r->external_ref);
        $this->assertSame('MultiPolygon', $r->geometry['type']);
        $this->assertSame(
            ['flood_zone' => 'AE', 'zone_subtype' => null, 'sfha' => 'T', 'dfirm_id' => '12103C', 'source' => 'fema_nfhl'],
            $r->attrs,
        );
        $this->assertArrayNotHasKey('acres', $r->attrs);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function flood_coverage_keys_on_firm_pan_and_builds_attrs(): void
    {
        $result = (new FemaFloodCoverageBoundarySource())->normalize([
            ['firm_pan' => '12103C0001G', 'dfirm_id' => '12103C', 'panel' => '0001', 'suffix' => 'G', 'panel_typ' => 'FIRM', 'eff_date' => '2021-09-27', 'geometry' => $this->polygon()],
        ]);

        $r = $result->records[0];
        $this->assertSame('flood_coverage', $r->kind);
        $this->assertSame('12103C0001G', $r->external_ref);
        $this->assertSame(
            ['dfirm_id' => '12103C', 'panel' => '0001', 'suffix' => 'G', 'panel_type' => 'FIRM', 'eff_date' => '2021-09-27', 'source' => 'fema_nfhl'],
            $r->attrs,
        );
        $this->assertArrayNotHasKey('acres', $r->attrs);
    }

    /** @test */
    public function both_adapters_resolve_uppercase_shapefile_attribute_names(): void
    {
        $zone = (new FemaFloodZoneBoundarySource())->normalize([
            ['FLD_AR_ID' => '12103C-0009', 'FLD_ZONE' => 'VE', 'ZONE_SUBTY' => null, 'SFHA_TF' => 'T', 'DFIRM_ID' => '12103C', 'geometry' => $this->polygon()],
        ]);
        $this->assertSame('12103C-0009', $zone->records[0]->external_ref);
        $this->assertSame('VE', $zone->records[0]->attrs['flood_zone']);

        $coverage = (new FemaFloodCoverageBoundarySource())->normalize([
            ['FIRM_PAN' => '12103C0009G', 'DFIRM_ID' => '12103C', 'PANEL' => '0009', 'SUFFIX' => 'G', 'PANEL_TYP' => 'FIRM', 'EFF_DATE' => '2021-09-27', 'geometry' => $this->polygon()],
        ]);
        $this->assertSame('12103C0009G', $coverage->records[0]->external_ref);
        $this->assertSame('0009', $coverage->records[0]->attrs['panel']);
    }

    /**
     * The 'T'/'F' token is FEMA's, and it stays FEMA's. Zone D (undetermined) and zone A without a
     * BFE both need their own downstream treatment; a premature boolean would flatten them.
     *
     * @test
     */
    public function flood_zone_passes_sfha_through_unparsed_and_never_coerces_it(): void
    {
        $result = (new FemaFloodZoneBoundarySource())->normalize([
            ['fld_ar_id' => 'a', 'fld_zone' => 'D', 'zone_subty' => 'AREA OF UNDETERMINED FLOOD HAZARD', 'sfha_tf' => 'F', 'geometry' => $this->polygon()],
        ]);

        $sfha = $result->records[0]->attrs['sfha'];
        $this->assertSame('F', $sfha);
        $this->assertIsString($sfha);
        $this->assertSame('AREA OF UNDETERMINED FLOOD HAZARD', $result->records[0]->attrs['zone_subtype']);
        $this->assertNull($result->records[0]->attrs['dfirm_id'], 'an absent optional attribute is null, not invented');
    }

    /** @test */
    public function a_missing_external_ref_is_rejected_invalid_field_with_a_per_layer_reason(): void
    {
        $zone = (new FemaFloodZoneBoundarySource())->normalize([
            ['fld_zone' => 'AE', 'sfha_tf' => 'T', 'geometry' => $this->polygon()],
        ]);
        $this->assertCount(0, $zone->records);
        $this->assertSame(1, $zone->rejectedInvalidField);
        $this->assertSame(0, $zone->rejectedInvalidGeometry);
        $this->assertSame(['invalid_missing_fld_ar_id' => 1], $zone->rejectReasons);
        $this->assertTrue($zone->isFullyAccounted());

        $coverage = (new FemaFloodCoverageBoundarySource())->normalize([
            ['dfirm_id' => '12103C', 'panel' => '0004', 'geometry' => $this->polygon()],
        ]);
        $this->assertSame(['invalid_missing_firm_pan' => 1], $coverage->rejectReasons);
        $this->assertTrue($coverage->isFullyAccounted());
    }

    /** @test */
    public function an_unclosed_ring_is_rejected_invalid_geometry(): void
    {
        $open = ['type' => 'Polygon', 'coordinates' => [[[-80.3, 25.7], [-80.3, 25.71], [-80.29, 25.71], [-80.29, 25.7]]]];

        $result = (new FemaFloodZoneBoundarySource())->normalize([
            ['fld_ar_id' => '12103C-0003', 'fld_zone' => 'VE', 'geometry' => $open],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalidGeometry);
        $this->assertSame(['invalid_geometry' => 1], $result->rejectReasons);
    }

    /** @test */
    public function normalization_preserves_input_order_and_is_deterministic(): void
    {
        $rows = [
            ['fld_ar_id' => '12103C-0002', 'fld_zone' => 'X', 'geometry' => $this->polygon()],
            ['fld_ar_id' => '12103C-0001', 'fld_zone' => 'AE', 'geometry' => $this->polygon()],
        ];
        $result = (new FemaFloodZoneBoundarySource())->normalize($rows);

        $this->assertSame(['12103C-0002', '12103C-0001'], array_map(fn ($r) => $r->external_ref, $result->records));
    }

    /**
     * ref_present and ref_unique stay enforced for FEMA (approved C3c decision). A duplicate
     * FLD_AR_ID is a HARD FAIL — never merged, never given an invented composite id.
     *
     * @test
     */
    public function duplicate_external_refs_hard_fail_acceptance_rather_than_being_merged(): void
    {
        $source = new FemaFloodZoneBoundarySource();
        $result = $source->normalize([
            ['fld_ar_id' => '12103C-0001', 'fld_zone' => 'AE', 'geometry' => $this->polygon()],
            ['fld_ar_id' => '12103C-0001', 'fld_zone' => 'VE', 'geometry' => $this->polygon(-81.0, 26.0)],
        ]);

        // The adapter itself never drops or merges duplicates — both survive normalization.
        $this->assertCount(2, $result->records);

        $verdict = (new BoundaryImportAcceptance(null, [$source->kind()]))->evaluate($result->records);
        $this->assertFalse($verdict['passed']);
        $this->assertContains('ref_unique', $verdict['failures']);
    }

    /** @test */
    public function acceptance_passes_for_both_fema_kinds_and_enforces_ref_present(): void
    {
        foreach ([new FemaFloodZoneBoundarySource(), new FemaFloodCoverageBoundarySource()] as $source) {
            $raw = $source->kind() === 'flood_zone'
                ? ['fld_ar_id' => 'ref-1', 'fld_zone' => 'AE', 'geometry' => $this->polygon()]
                : ['firm_pan' => 'ref-1', 'panel' => '0001', 'geometry' => $this->polygon()];

            $result = $source->normalize([$raw]);
            $verdict = (new BoundaryImportAcceptance(null, [$source->kind()]))->evaluate($result->records);

            $this->assertTrue($verdict['passed'], "{$source->kind()} acceptance should pass");
            $names = array_column($verdict['checks'], 'name');
            $this->assertContains('ref_present', $names);
            $this->assertContains('ref_unique', $names);
        }
    }
}
