<?php

namespace Tests\Unit\Explore;

use App\Services\Explore\ExploreViewport;
use InvalidArgumentException;
use Tests\TestCase;

class ExploreViewportTest extends TestCase
{
    /** @test */
    public function it_parses_a_well_formed_bounding_box(): void
    {
        $viewport = ExploreViewport::fromString('27.70,-82.70,27.80,-82.60');

        $this->assertSame(27.70, $viewport->south);
        $this->assertSame(-82.70, $viewport->west);
        $this->assertSame(27.80, $viewport->north);
        $this->assertSame(-82.60, $viewport->east);
    }

    /** @test */
    public function it_reports_containment(): void
    {
        $viewport = ExploreViewport::fromString('27.70,-82.70,27.80,-82.60');

        $this->assertTrue($viewport->contains(27.75, -82.65));
        $this->assertFalse($viewport->contains(28.90, -82.65));
        $this->assertFalse($viewport->contains(27.75, -80.00));
    }

    /**
     * An over-large box is REFUSED, never clamped. A clamped box answers a
     * question the consumer did not ask, and an emptier-looking neighbourhood
     * reads as "nothing for sale here" — a false statement about a real market.
     *
     * @test
     */
    public function an_oversized_bounding_box_is_refused_rather_than_clamped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zoom in/i');

        // Florida-wide: ~6 degrees of latitude against a 1.0 ceiling.
        ExploreViewport::fromString('25.0,-83.0,31.0,-80.0');
    }

    /** @test */
    public function it_refuses_malformed_input(): void
    {
        foreach ([
            null,
            '',
            '27.7,-82.7,27.8',
            '27.7,-82.7,27.8,-82.6,9',
            'a,b,c,d',
            '27.7,,27.8,-82.6',
        ] as $bbox) {
            try {
                ExploreViewport::fromString($bbox);
                $this->fail('Expected refusal for ' . var_export($bbox, true));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @test */
    public function it_refuses_an_out_of_order_or_out_of_range_box(): void
    {
        foreach ([
            '27.80,-82.70,27.70,-82.60', // north below south
            '27.70,-82.60,27.80,-82.70', // east west of west
            '-91.0,-82.7,-90.5,-82.6',   // below the south pole
            '27.7,-181.0,27.8,-180.5',   // beyond the antimeridian
        ] as $bbox) {
            try {
                ExploreViewport::fromString($bbox);
                $this->fail('Expected refusal for ' . $bbox);
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
