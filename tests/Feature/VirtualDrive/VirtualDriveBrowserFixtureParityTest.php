<?php

namespace Tests\Feature\VirtualDrive;

use Tests\TestCase;

/**
 * The browser specs run the real shell against fixture pages that copy the
 * proof page's markup. A fixture that drifted from the Blade template would let
 * the specs pass against a page the proof no longer renders, so every element
 * id and data attribute the template carries must be in both fixtures.
 */
class VirtualDriveBrowserFixtureParityTest extends TestCase
{
    private const FIXTURES = [
        'tests/browser/fixtures/virtual-drive/google.html',
        'tests/browser/fixtures/virtual-drive/apple.html',
    ];

    /** @test */
    public function both_fixtures_carry_every_element_id_and_data_attribute_of_the_proof_page(): void
    {
        $blade = (string) file_get_contents(base_path('resources/views/dev/virtual-drive/show.blade.php'));

        preg_match_all('/id="(vd-[a-z0-9-]+)"/', $blade, $ids);
        preg_match_all('/\b(data-[a-z-]+)="/', $blade, $attributes);

        $ids        = array_values(array_unique($ids[1]));
        $attributes = array_values(array_unique($attributes[1]));

        $this->assertGreaterThan(25, count($ids), 'the id scan found too little to be trusted');
        $this->assertContains('data-launch-label', $attributes);

        foreach (self::FIXTURES as $path) {
            $fixture = (string) file_get_contents(base_path($path));

            foreach ($ids as $id) {
                $this->assertStringContainsString('id="' . $id . '"', $fixture, "{$path} is missing #{$id}");
            }

            foreach ($attributes as $attribute) {
                $this->assertStringContainsString($attribute . '="', $fixture, "{$path} is missing {$attribute}");
            }
        }
    }
}
