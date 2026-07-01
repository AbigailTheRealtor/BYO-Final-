<?php

namespace Tests\Unit\Offers;

use App\Services\Offers\ImportantPlacesService;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9C — pure normalization + validation for the Important Places rows stored in the
 * additive `important_places_json` meta. No DB / Livewire context needed.
 */
class ImportantPlacesServiceTest extends TestCase
{
    private ImportantPlacesService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ImportantPlacesService();
    }

    private function completeRow(array $overrides = []): array
    {
        return array_merge([
            'type'           => 'Work',
            'type_other'     => '',
            'address'        => '123 Main St, Tampa, FL',
            'lat'            => 27.95,
            'lng'            => -82.45,
            'distance_pref'  => 'miles',
            'distance_value' => 5,
            'travel_mode'    => 'driving',
        ], $overrides);
    }

    public function test_decode_handles_json_string_array_and_garbage(): void
    {
        $this->assertSame([], $this->svc->decode(''));
        $this->assertSame([], $this->svc->decode('not json'));
        $this->assertSame([], $this->svc->decode(null));
        $this->assertCount(1, $this->svc->decode(json_encode([$this->completeRow()])));
        $this->assertCount(1, $this->svc->decode([$this->completeRow()]));
    }

    public function test_normalize_drops_fully_empty_rows(): void
    {
        $rows = [
            $this->completeRow(),
            ['type' => '', 'type_other' => '', 'address' => '', 'distance_value' => ''],
            ['type' => '', 'address' => '  '],
        ];

        $out = $this->svc->normalize($rows);

        $this->assertCount(1, $out);
        $this->assertSame('Work', $out[0]['type']);
    }

    public function test_normalize_keeps_partial_rows_for_drafts(): void
    {
        // Address only — incomplete, but preserved so a draft never loses in-progress work.
        $out = $this->svc->normalize([['address' => '500 Elm St']]);

        $this->assertCount(1, $out);
        $this->assertSame('500 Elm St', $out[0]['address']);
        $this->assertNull($out[0]['distance_value']);
    }

    public function test_normalize_coerces_shape_and_defaults(): void
    {
        $out = $this->svc->normalize([[
            'type'           => 'School',
            'address'        => '1 School Rd',
            'lat'            => 'not-a-number',
            'distance_pref'  => 'bogus',
            'distance_value' => '3.5',
            'travel_mode'    => 'teleport',
        ]]);

        $row = $out[0];
        $this->assertNull($row['lat']);                    // non-numeric → null
        $this->assertSame('miles', $row['distance_pref']); // invalid → default miles
        $this->assertSame(3.5, $row['distance_value']);    // numeric string → float
        $this->assertSame('driving', $row['travel_mode']); // invalid → default driving
        $this->assertSame('', $row['type_other']);         // cleared unless type === Other
    }

    public function test_normalize_preserves_type_other_only_for_other(): void
    {
        $kept = $this->svc->normalize([$this->completeRow(['type' => 'Other', 'type_other' => 'Boat slip'])]);
        $this->assertSame('Boat slip', $kept[0]['type_other']);

        $dropped = $this->svc->normalize([$this->completeRow(['type' => 'Work', 'type_other' => 'Boat slip'])]);
        $this->assertSame('', $dropped[0]['type_other']);
    }

    public function test_validate_passes_for_complete_rows(): void
    {
        $this->assertSame([], $this->svc->validate([$this->completeRow()]));
        // Fully-empty rows never trip validation (dropped first).
        $this->assertSame([], $this->svc->validate([['type' => '', 'address' => '']]));
        $this->assertSame([], $this->svc->validate(''));
    }

    public function test_validate_flags_missing_type(): void
    {
        $errors = $this->svc->validate([$this->completeRow(['type' => ''])]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('choose a place type', $errors[0]);
    }

    public function test_validate_requires_type_other_when_other(): void
    {
        $errors = $this->svc->validate([$this->completeRow(['type' => 'Other', 'type_other' => ''])]);
        $this->assertStringContainsString('custom place type', implode(' ', $errors));
    }

    public function test_validate_flags_missing_address(): void
    {
        $errors = $this->svc->validate([$this->completeRow(['address' => ''])]);
        $this->assertStringContainsString('enter an address', implode(' ', $errors));
    }

    public function test_validate_flags_non_positive_distance(): void
    {
        $zero = $this->svc->validate([$this->completeRow(['distance_value' => 0])]);
        $this->assertStringContainsString('greater than zero', implode(' ', $zero));

        $missing = $this->svc->validate([$this->completeRow(['distance_value' => ''])]);
        $this->assertStringContainsString('greater than zero', implode(' ', $missing));

        // "minutes" preference words the message as travel time.
        $minutes = $this->svc->validate([$this->completeRow(['distance_pref' => 'minutes', 'distance_value' => 0])]);
        $this->assertStringContainsString('travel time', implode(' ', $minutes));
    }

    public function test_validate_numbers_rows_by_position(): void
    {
        $errors = $this->svc->validate([
            $this->completeRow(),                       // #1 valid
            $this->completeRow(['address' => '']),      // #2 invalid
        ]);

        $this->assertStringContainsString('Important Place #2', implode(' ', $errors));
        $this->assertStringNotContainsString('Important Place #1', implode(' ', $errors));
    }

    public function test_encode_round_trips_normalized_rows(): void
    {
        $json = $this->svc->encode([
            $this->completeRow(),
            ['type' => '', 'address' => ''], // dropped
        ]);

        $decoded = json_decode($json, true);
        $this->assertCount(1, $decoded);
        $this->assertSame('Work', $decoded[0]['type']);
    }
}
