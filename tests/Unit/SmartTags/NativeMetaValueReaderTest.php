<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\NativeMetaValueReader;
use PHPUnit\Framework\TestCase;

class NativeMetaValueReaderTest extends TestCase
{
    /** @test */
    public function it_reads_every_stored_shape_of_a_single_select(): void
    {
        $reader = new NativeMetaValueReader([
            'plain'   => 'Furnished',
            'quoted'  => '"Furnished"',
            'array1'  => '["Furnished"]',
            'empty'   => '[]',
            'blank'   => '  ',
            'other'   => 'Other',
            'nullstr' => 'null',
        ]);

        $this->assertSame('Furnished', $reader->scalar('plain'));
        $this->assertSame('Furnished', $reader->scalar('quoted'));
        $this->assertSame('Furnished', $reader->scalar('array1'));
        $this->assertNull($reader->scalar('empty'), 'Landlord Create stores single-selects as "[]": that is unknown, not an answer');
        $this->assertNull($reader->scalar('blank'));
        $this->assertNull($reader->scalar('other'));
        $this->assertNull($reader->scalar('nullstr'));
        $this->assertNull($reader->scalar('missing'));
    }

    /** @test */
    public function it_reads_multi_selects_and_drops_empty_and_other(): void
    {
        $reader = new NativeMetaValueReader([
            'features' => '["Open Floorplan","Other","","Vaulted Ceiling(s)","Open Floorplan"]',
            'single'   => 'Quartz Counters',
            'empty'    => '[]',
        ]);

        $this->assertSame(['Open Floorplan', 'Vaulted Ceiling(s)'], $reader->list('features'));
        $this->assertSame(['Quartz Counters'], $reader->list('single'));
        $this->assertSame([], $reader->list('empty'));
    }

    /** @test */
    public function it_reads_yes_no_flags_and_numbers(): void
    {
        $reader = new NativeMetaValueReader([
            'waterfront' => 'Yes',
            'garage'     => 'No',
            'pool'       => 'Optional',
            'pool_type'  => '{"private":true,"community":"0"}',
            'meters'     => '12',
            'price'      => '$1,250',
        ]);

        $this->assertTrue($reader->yesNo('waterfront'));
        $this->assertFalse($reader->yesNo('garage'));
        $this->assertNull($reader->yesNo('pool'), 'Optional is not a yes/no answer');
        $this->assertTrue($reader->flag('pool_type', 'private'));
        $this->assertFalse($reader->flag('pool_type', 'community'));
        $this->assertNull($reader->flag('pool_type', 'missing'));
        $this->assertSame(12.0, $reader->number('meters'));
        $this->assertSame(1250.0, $reader->number('price'));
    }

    /** @test */
    public function option_normalisation_absorbs_dash_space_and_case_drift(): void
    {
        $this->assertSame(
            NativeMetaValueReader::normalizeOption('Elevator - None'),
            NativeMetaValueReader::normalizeOption("Elevator \u{2013} None"),
        );
        $this->assertSame('five or more', NativeMetaValueReader::normalizeOption('Five or More '));
    }
}
