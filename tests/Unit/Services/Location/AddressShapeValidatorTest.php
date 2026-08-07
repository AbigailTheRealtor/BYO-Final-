<?php

namespace Tests\Unit\Services\Location;

use App\Services\Location\AddressShapeValidator;
use Tests\TestCase;

/**
 * Phase 0 — the rule that makes `43434` impossible.
 *
 * The audit's finding was not "autocomplete is broken". It was that the street
 * field had no server-side shape check at all, so a street number persisted as a
 * whole property address and nothing downstream could tell. These tests pin the
 * boundary between what a street address looks like and what it does not.
 *
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §1, §9
 */
class AddressShapeValidatorTest extends TestCase
{
    private AddressShapeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AddressShapeValidator();
    }

    /**
     * The reported bug, scenario 1 — rejected, which is the part that matters.
     *
     * Shape alone cannot tell `43434` (a street number) from `33708` (a ZIP):
     * both are five digits. This layer says only "five digits, not an address".
     * Deciding *which* of the two it is needs the `us_zip_codes` gazetteer, and
     * that disambiguation is asserted in {@see \Tests\Feature\Location\ValidStreetAddressRuleTest}.
     */
    public function test_bare_street_number_is_rejected(): void
    {
        $this->assertFalse($this->validator->isValid('43434'));
        $this->assertSame(
            AddressShapeValidator::ZIP_SHAPED,
            $this->validator->reasonFor('43434')
        );
    }

    /** Scenario 2 — ZIP-shaped input is classified separately so it can be recovered. */
    public function test_zip_shaped_input_is_classified_as_a_zip_not_an_address(): void
    {
        $this->assertSame(AddressShapeValidator::ZIP_SHAPED, $this->validator->reasonFor('33708'));
        $this->assertSame(AddressShapeValidator::ZIP_SHAPED, $this->validator->reasonFor('33708-1234'));
        $this->assertFalse($this->validator->isValid('33708'));
    }

    /**
     * @dataProvider validFloridaAddresses
     */
    public function test_real_addresses_are_accepted(string $address): void
    {
        $this->assertTrue(
            $this->validator->isValid($address),
            "Expected [{$address}] to validate, got [{$this->validator->reasonFor($address)}]"
        );
    }

    public static function validFloridaAddresses(): array
    {
        return [
            'audit scenario 4'      => ['100 2nd Ave N, St. Petersburg'],
            'audit scenario 5'      => ['1 Beach Dr SE, St Petersburg FL 33701'],
            'audit scenario 6'      => ['13801 Walsingham Rd, Largo FL 33774'],
            'the reported number,'
                . ' now with a street' => ['43434 Main Street'],
            'minimal'               => ['1 Elm St'],
            'written-out number'    => ['One Beach Drive'],
            'hyphenated street'     => ['4200 Park Street North'],
            'extra whitespace'      => ['   100   2nd Ave N   '],
        ];
    }

    /**
     * @dataProvider invalidEntries
     */
    public function test_incomplete_entries_are_rejected(string $address, string $expectedReason): void
    {
        $this->assertSame($expectedReason, $this->validator->reasonFor($address));
        $this->assertFalse($this->validator->isValid($address));
    }

    public static function invalidEntries(): array
    {
        return [
            'empty'             => ['', AddressShapeValidator::EMPTY_VALUE],
            'whitespace only'   => ['   ', AddressShapeValidator::EMPTY_VALUE],
            'five digits'       => ['43434', AddressShapeValidator::ZIP_SHAPED],
            'short number'      => ['12', AddressShapeValidator::NUMBER_ONLY],
            'punctuation'       => ['...', AddressShapeValidator::NUMBER_ONLY],
            'digits and dashes' => ['123-456', AddressShapeValidator::NUMBER_ONLY],
            'no letters at all' => ['1234 #4', AddressShapeValidator::NUMBER_ONLY],
            'single short word' => ['Main', AddressShapeValidator::TOO_SHORT],
            'no street number'  => ['Main Street', AddressShapeValidator::NO_STREET_NUMBER],
            'unit letter only'  => ['1234 A', AddressShapeValidator::NO_STREET_NAME],
        ];
    }

    public function test_every_rejection_explains_what_to_do(): void
    {
        $reasons = [
            AddressShapeValidator::EMPTY_VALUE,
            AddressShapeValidator::ZIP_SHAPED,
            AddressShapeValidator::NUMBER_ONLY,
            AddressShapeValidator::NO_STREET_NAME,
            AddressShapeValidator::NO_STREET_NUMBER,
            AddressShapeValidator::TOO_SHORT,
        ];

        foreach ($reasons as $reason) {
            $message = $this->validator->messageFor($reason);

            $this->assertNotSame('', $message, "Reason [{$reason}] has no message");
            $this->assertStringNotContainsStringIgnoringCase(
                'invalid',
                $message,
                "Reason [{$reason}] says 'invalid' instead of what to do"
            );
        }

        $this->assertSame('', $this->validator->messageFor(AddressShapeValidator::OK));
    }

    public function test_null_is_treated_as_empty_not_as_a_crash(): void
    {
        $this->assertSame(AddressShapeValidator::EMPTY_VALUE, $this->validator->reasonFor(null));
        $this->assertFalse($this->validator->isValid(null));
    }

    public function test_normalization_collapses_pasted_whitespace(): void
    {
        $this->assertSame(
            '100 2nd Ave N',
            $this->validator->normalize("  100\t2nd\xc2\xa0Ave   N \n")
        );
    }
}
