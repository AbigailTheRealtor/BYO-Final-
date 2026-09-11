<?php

namespace Tests\Unit\Support\Geo;

use App\Support\Geo\GreatCircleDistance;
use PHPUnit\Framework\TestCase;

/**
 * The one local definition of a straight-line mile the matcher uses. Known distances only: along a
 * meridian the great-circle distance is exactly R·Δφ, so no expected value here is approximate.
 */
class GreatCircleDistanceTest extends TestCase
{
    public function test_one_degree_of_latitude_is_two_pi_r_over_360(): void
    {
        $expected = 2 * M_PI * GreatCircleDistance::EARTH_RADIUS_MILES / 360; // 69.0941... mi at R = 3958.8

        $this->assertEqualsWithDelta($expected, GreatCircleDistance::miles(27.0, -82.5, 28.0, -82.5), 1e-9);
        $this->assertEqualsWithDelta(69.0941, GreatCircleDistance::miles(27.0, -82.5, 28.0, -82.5), 1e-4);
    }

    public function test_it_is_symmetric_and_zero_for_the_same_point(): void
    {
        $there = GreatCircleDistance::miles(27.9506, -82.4572, 27.7676, -82.6403);
        $back  = GreatCircleDistance::miles(27.7676, -82.6403, 27.9506, -82.4572);

        $this->assertEqualsWithDelta($there, $back, 1e-12);
        $this->assertSame(0.0, GreatCircleDistance::miles(27.9506, -82.4572, 27.9506, -82.4572));
    }

    public function test_the_radius_is_the_one_the_scorer_has_always_used(): void
    {
        $this->assertSame(3958.8, GreatCircleDistance::EARTH_RADIUS_MILES);
    }

    public function test_only_a_real_coordinate_may_be_measured_from(): void
    {
        $this->assertTrue(GreatCircleDistance::isCoordinate(27.9506, -82.4572));
        $this->assertTrue(GreatCircleDistance::isCoordinate('27.9506', '-82.4572'));

        foreach ([[null, -82.4], [27.9, null], ['', ''], ['abc', '1'], [91, 0], [0, 181], [0, 0], [0.0, 0.0], [NAN, 1], [INF, 1]] as [$lat, $lng]) {
            $this->assertFalse(GreatCircleDistance::isCoordinate($lat, $lng), json_encode([$lat, $lng]) . ' must not be measured from');
        }
    }
}
