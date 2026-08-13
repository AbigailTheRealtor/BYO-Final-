<?php

namespace Tests\Unit\Bridge;

use App\Services\Bridge\BridgePropertyNormalizer;
use Tests\TestCase;

/**
 * RESO PetsAllowed is multi-value, and BridgePropertyNormalizer used to keep
 * only the first element.
 *
 * That is worse than an abridged answer. A listing whose policy is
 * ["Cats OK", "Dogs OK", "Size Limit", "Yes"] stored as "Cats OK" reads as a
 * property that allows cats, says nothing about dogs, and carries no size
 * restriction — a materially more permissive policy than the one the feed
 * published. These tests exist so a regression to "first element wins" fails
 * loudly rather than quietly changing what a landlord appears to permit.
 */
class BridgePetPolicyFidelityTest extends TestCase
{
    /**
     * The exact case the master audit found in the live feed.
     */
    public function test_the_complete_policy_survives_normalization(): void
    {
        $this->assertSame(
            'Cats OK, Dogs OK, Size Limit, Yes',
            BridgePropertyNormalizer::normalizePetsAllowed(['Cats OK', 'Dogs OK', 'Size Limit', 'Yes'])
        );
    }

    /**
     * The specific regression: the old implementation returned 'Cats OK' here.
     */
    public function test_later_values_are_not_discarded(): void
    {
        $result = BridgePropertyNormalizer::normalizePetsAllowed(['Cats OK', 'Dogs OK', 'Size Limit', 'Yes']);

        $this->assertStringContainsString('Dogs OK', $result, 'the second value was dropped — first-element bug is back');
        $this->assertStringContainsString('Size Limit', $result, 'the size restriction was dropped');
        $this->assertNotSame('Cats OK', $result);
    }

    public function test_a_single_value_is_unchanged(): void
    {
        $this->assertSame('Yes', BridgePropertyNormalizer::normalizePetsAllowed(['Yes']));
    }

    public function test_a_plain_string_is_passed_through(): void
    {
        $this->assertSame('No', BridgePropertyNormalizer::normalizePetsAllowed('No'));
    }

    public function test_source_order_is_preserved(): void
    {
        $this->assertSame(
            'Size Limit, Cats OK, Yes',
            BridgePropertyNormalizer::normalizePetsAllowed(['Size Limit', 'Cats OK', 'Yes']),
            'the stored value must record what the feed said, not a re-sorted version of it'
        );
    }

    public function test_duplicates_are_collapsed(): void
    {
        $this->assertSame(
            'Cats OK, Dogs OK',
            BridgePropertyNormalizer::normalizePetsAllowed(['Cats OK', 'Dogs OK', 'Cats OK'])
        );
    }

    public function test_blank_entries_are_dropped(): void
    {
        $this->assertSame(
            'Cats OK, Dogs OK',
            BridgePropertyNormalizer::normalizePetsAllowed(['Cats OK', '', '   ', 'Dogs OK'])
        );
    }

    public function test_values_are_trimmed(): void
    {
        $this->assertSame(
            'Cats OK, Dogs OK',
            BridgePropertyNormalizer::normalizePetsAllowed(['  Cats OK ', "Dogs OK\n"])
        );
    }

    /**
     * @dataProvider emptyInputs
     */
    public function test_empty_input_normalizes_to_null(mixed $input): void
    {
        $this->assertNull(BridgePropertyNormalizer::normalizePetsAllowed($input));
    }

    public static function emptyInputs(): array
    {
        return [
            'null'              => [null],
            'empty array'       => [[]],
            'empty string'      => [''],
            'whitespace string' => ['   '],
            'array of blanks'   => [['', '  ']],
        ];
    }

    /**
     * Nothing is re-worded. A normalizer that turned "Size Limit" into prose
     * would be authoring content, which is exactly what the facts-only boundary
     * excludes — and it would do so in the one layer no reviewer reads.
     */
    public function test_vocabulary_is_not_rewritten(): void
    {
        $result = BridgePropertyNormalizer::normalizePetsAllowed(['Breed Restrictions', 'Number Limit']);

        $this->assertSame('Breed Restrictions, Number Limit', $result);
    }

    /**
     * The longest policy present in the 669 locally-imported Bridge records is
     * 58 characters, which is why the column was widened past its original 50.
     * If this ever exceeds the new width the import raises on PostgreSQL rather
     * than truncating, so the bound is worth asserting.
     */
    public function test_the_longest_observed_policy_fits_the_column(): void
    {
        $longest = BridgePropertyNormalizer::normalizePetsAllowed(
            ['Breed Restrictions', 'Dogs OK', 'Number Limit', 'Size Limit', 'Yes']
        );

        $this->assertSame('Breed Restrictions, Dogs OK, Number Limit, Size Limit, Yes', $longest);
        $this->assertLessThanOrEqual(255, strlen($longest));
        $this->assertGreaterThan(50, strlen($longest), 'this is the case the widening migration exists for');
    }

    /**
     * Nested structures are not a policy value; skipping them keeps the joined
     * string free of "Array".
     */
    public function test_non_scalar_entries_are_skipped(): void
    {
        $this->assertSame(
            'Cats OK',
            BridgePropertyNormalizer::normalizePetsAllowed(['Cats OK', ['nested'], (object) ['a' => 1]])
        );
    }
}
