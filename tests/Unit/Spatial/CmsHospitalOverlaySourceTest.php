<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\Overlay\CmsHospitalOverlaySource;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — CMS Hospital Star Rating overlay importer (decisions D3/D4/D5).
 * Pure; no DB, no network.
 */
class CmsHospitalOverlaySourceTest extends TestCase
{
    private CmsHospitalOverlaySource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = new CmsHospitalOverlaySource();
    }

    /** @test */
    public function it_declares_an_overlay_link_target_with_the_star_metric_domain(): void
    {
        $this->assertSame('cms', $this->source->sourceKey());
        $this->assertSame('link', $this->source->target());
        $this->assertSame('cms_overall_star_rating', $this->source->metricLabel());
        $this->assertSame([1.0, 5.0], $this->source->metricDomain());
    }

    /** @test */
    public function a_rated_hospital_becomes_an_authority_record_with_the_star_metric(): void
    {
        $result = $this->source->normalize([
            ['facility_id' => '100001', 'facility_name' => 'Synthetic General Hospital', 'hospital_overall_rating' => '4', 'lon' => -82.64, 'lat' => 27.77],
        ]);

        $this->assertCount(1, $result->records);
        $r = $result->records[0];
        $this->assertSame('cms', $r->authority_source);
        $this->assertSame('100001', $r->authority_ref);
        $this->assertSame('Synthetic General Hospital', $r->name);
        $this->assertSame(4.0, $r->authority_metric);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function a_not_available_rating_is_kept_with_a_null_metric_D3(): void
    {
        $result = $this->source->normalize([
            ['facility_id' => '100002', 'facility_name' => 'Synthetic Community Medical Center', 'hospital_overall_rating' => 'Not Available', 'lon' => -82.63, 'lat' => 27.78],
        ]);

        $this->assertCount(1, $result->records);
        $this->assertNull($result->records[0]->authority_metric);
        $this->assertSame(0, $result->rejectedInvalid);
        $this->assertSame(0, $result->rejectedOutOfDomain);
    }

    /** @test */
    public function a_row_without_a_ccn_is_rejected_invalid(): void
    {
        $result = $this->source->normalize([
            ['facility_name' => 'Ghost Clinic', 'hospital_overall_rating' => '3', 'lon' => -82.62, 'lat' => 27.79],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalid);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function an_out_of_domain_rating_is_rejected_never_clamped_D4(): void
    {
        $result = $this->source->normalize([
            ['facility_id' => '100004', 'facility_name' => 'Overrated Regional Hospital', 'hospital_overall_rating' => '9', 'lon' => -82.61, 'lat' => 27.8],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedOutOfDomain);
    }

    /** @test */
    public function a_row_missing_coordinates_is_rejected_invalid(): void
    {
        $result = $this->source->normalize([
            ['facility_id' => '100005', 'facility_name' => 'No Coords Hospital', 'hospital_overall_rating' => '3'],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalid);
    }

    /** @test */
    public function normalization_is_deterministic_and_input_order_preserved(): void
    {
        $rows = [
            ['facility_id' => '100002', 'facility_name' => 'B', 'hospital_overall_rating' => 'Not Available', 'lon' => -82.63, 'lat' => 27.78],
            ['facility_id' => '100001', 'facility_name' => 'A', 'hospital_overall_rating' => '4', 'lon' => -82.64, 'lat' => 27.77],
        ];

        $result = $this->source->normalize($rows);
        $this->assertSame(['100002', '100001'], array_map(fn ($r) => $r->authority_ref, $result->records));
    }
}
