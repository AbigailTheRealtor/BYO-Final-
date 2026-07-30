<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\PublicGeometryProjection;
use PHPUnit\Framework\TestCase;

/**
 * G0.1 — PublicGeometryProjection unit contract.
 *
 * THE INVARIANT THIS SUITE EXISTS TO PROTECT
 * ------------------------------------------
 *   A decoded `location_dna_preferences` array that has passed through
 *   project() carries NO exact user-authored geometry and NO free-text notes,
 *   while retaining the published administrative names that v1.2 §13.3 permits
 *   on a public surface.
 *
 * Decisions enforced:
 *   D4 — output never varies by session, user or login state. There is no
 *        parameter by which it could: project() takes only the array.
 *   D5 — notes are withheld, never truncated, summarised or inspected.
 *
 * CATEGORY — behavioural (pure). This suite proves the redaction function is
 * correct. It proves nothing about whether any route calls it; that is
 * PublicGeometryContainmentTest's job, and nothing here should be read as
 * evidence of route behaviour.
 *
 * Extends PHPUnit's TestCase directly, not Tests\TestCase: the subject touches
 * no database, container, config or network, and a pure unit test should not
 * boot an application to prove it.
 */
class PublicGeometryProjectionTest extends TestCase
{
    private PublicGeometryProjection $projection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projection = new PublicGeometryProjection();
    }

    /** A realistic blob with every sensitive field populated. */
    private function sensitivePreferences(): array
    {
        return [
            'schema_version' => 2,
            'cities'         => ['St. Petersburg, FL'],
            'zip_codes'      => ['33708'],
            'counties'       => ['Pinellas County, FL'],
            'state'          => 'Florida',
            'neighborhoods'  => ['Historic Kenwood'],
            'polygons'       => [
                [
                    'label' => 'Near the school',
                    'path'  => [
                        ['lat' => 27.7634891, 'lng' => -82.6401234],
                        ['lat' => 27.7644892, 'lng' => -82.6411235],
                    ],
                ],
            ],
            'radius_searches' => [
                [
                    'lat'          => 27.7701234,
                    'lng'          => -82.6501234,
                    'radius_miles' => 5,
                    'address'      => '1234 Sensitive Lane, St. Petersburg, FL',
                ],
            ],
            'flexible_location' => true,
            'location_notes'    => 'We want to be close to my mother on Elm Street.',
        ];
    }

    public function test_null_input_returns_null(): void
    {
        $this->assertNull($this->projection->project(null));
    }

    public function test_polygons_are_withheld(): void
    {
        $out = $this->projection->project($this->sensitivePreferences());

        $this->assertSame([], $out['polygons']);
    }

    public function test_radius_searches_are_withheld(): void
    {
        $out = $this->projection->project($this->sensitivePreferences());

        $this->assertSame([], $out['radius_searches']);
    }

    public function test_location_notes_are_withheld_and_not_truncated(): void
    {
        $out = $this->projection->project($this->sensitivePreferences());

        // Withheld entirely — not shortened, not summarised, not partially shown.
        $this->assertSame('', $out['location_notes']);
    }

    public function test_no_sensitive_literal_survives_anywhere_in_the_output(): void
    {
        $out  = $this->projection->project($this->sensitivePreferences());
        $json = json_encode($out);

        foreach ([
            '27.7634891', '-82.6401234',   // polygon vertex
            '27.7644892', '-82.6411235',   // polygon vertex
            '27.7701234', '-82.6501234',   // radius centre
            '1234 Sensitive Lane',         // radius-centre street address
            'Elm Street',                  // free-text note content
            'Near the school',             // user-authored polygon label
        ] as $literal) {
            $this->assertStringNotContainsString(
                $literal,
                $json,
                "Sensitive literal '{$literal}' survived the public projection."
            );
        }
    }

    public function test_published_administrative_names_are_retained(): void
    {
        $out = $this->projection->project($this->sensitivePreferences());

        $this->assertSame(['St. Petersburg, FL'], $out['cities']);
        $this->assertSame(['33708'], $out['zip_codes']);
        $this->assertSame(['Pinellas County, FL'], $out['counties']);
        $this->assertSame('Florida', $out['state']);
        $this->assertSame(['Historic Kenwood'], $out['neighborhoods']);
        $this->assertTrue($out['flexible_location']);
    }

    public function test_withheld_flags_are_set_when_sensitive_data_existed(): void
    {
        $out = $this->projection->project($this->sensitivePreferences());

        $this->assertTrue($out[PublicGeometryProjection::MARKER]);
        $this->assertTrue($out[PublicGeometryProjection::WITHHELD_GEOMETRY]);
        $this->assertTrue($out[PublicGeometryProjection::WITHHELD_NOTES]);
    }

    public function test_withheld_flags_are_false_when_nothing_sensitive_existed(): void
    {
        $out = $this->projection->project([
            'cities'          => ['Tampa, FL'],
            'polygons'        => [],
            'radius_searches' => [],
            'location_notes'  => '',
        ]);

        $this->assertTrue($out[PublicGeometryProjection::MARKER]);
        $this->assertFalse($out[PublicGeometryProjection::WITHHELD_GEOMETRY]);
        $this->assertFalse($out[PublicGeometryProjection::WITHHELD_NOTES]);
    }

    public function test_whitespace_only_notes_are_not_reported_as_withheld(): void
    {
        $out = $this->projection->project(['location_notes' => "   \n\t "]);

        $this->assertFalse($out[PublicGeometryProjection::WITHHELD_NOTES]);
        $this->assertSame('', $out['location_notes']);
    }

    public function test_absent_keys_are_not_invented(): void
    {
        $out = $this->projection->project(['cities' => ['Tampa, FL']]);

        // v1.2 §5.2: absence is meaningful. The projection must not manufacture
        // a present-but-empty dimension where the source had none.
        $this->assertArrayNotHasKey('polygons', $out);
        $this->assertArrayNotHasKey('radius_searches', $out);
        $this->assertArrayNotHasKey('location_notes', $out);
    }

    public function test_input_array_is_never_mutated(): void
    {
        $input  = $this->sensitivePreferences();
        $before = $input;

        $this->projection->project($input);

        $this->assertSame($before, $input, 'project() mutated the caller\'s array.');
    }

    public function test_projection_is_idempotent_and_preserves_flags(): void
    {
        $once  = $this->projection->project($this->sensitivePreferences());
        $twice = $this->projection->project($once);

        $this->assertSame($once, $twice);

        // The trap this guards: re-measuring an already-emptied array would
        // flip the withheld flags to false and silently drop the indicator.
        $this->assertTrue($twice[PublicGeometryProjection::WITHHELD_GEOMETRY]);
        $this->assertTrue($twice[PublicGeometryProjection::WITHHELD_NOTES]);
    }

    public function test_output_is_deterministic(): void
    {
        $a = $this->projection->project($this->sensitivePreferences());
        $b = $this->projection->project($this->sensitivePreferences());

        $this->assertSame($a, $b);
    }

    public function test_empty_array_input_is_marked_but_carries_no_data(): void
    {
        $out = $this->projection->project([]);

        $this->assertTrue($out[PublicGeometryProjection::MARKER]);
        $this->assertFalse($out[PublicGeometryProjection::WITHHELD_GEOMETRY]);
        $this->assertFalse($out[PublicGeometryProjection::WITHHELD_NOTES]);
    }

    public function test_malformed_geometry_values_do_not_throw(): void
    {
        $out = $this->projection->project([
            'polygons'        => 'not-an-array',
            'radius_searches' => 42,
            'location_notes'  => ['unexpectedly', 'an', 'array'],
        ]);

        // Withheld regardless of shape; a malformed value must never pass through.
        $this->assertSame([], $out['polygons']);
        $this->assertSame([], $out['radius_searches']);
        $this->assertSame('', $out['location_notes']);
    }
}
