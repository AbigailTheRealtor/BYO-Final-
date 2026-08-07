<?php

namespace Tests\Feature\Location;

use App\Rules\ValidStreetAddress;
use App\Services\Location\ZipCodeLookupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Phase 0 — the gazetteer earns its keep.
 *
 * `43434` and `33708` are both five digits. Shape cannot separate them; the
 * `us_zip_codes` table can, and it is already loaded with 34,741 rows. These
 * tests assert the two audit scenarios that depend on that distinction:
 *
 *   1. `43434` is not a US ZIP → "that is a street number on its own"
 *   2. `33708` IS a US ZIP     → "that is a ZIP code, put it in the ZIP field"
 *
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §9 scenarios 1–2
 */
class ValidStreetAddressRuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        DB::table('us_zip_codes')->insert([
            [
                'zip_code'     => '33708',
                'city'         => 'Saint Petersburg',
                'state_abbrev' => 'FL',
                'state_name'   => 'Florida',
                'county'       => 'Pinellas',
                'latitude'     => 27.8116080,
                'longitude'    => -82.8014300,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'zip_code'     => '33701',
                'city'         => 'Saint Petersburg',
                'state_abbrev' => 'FL',
                'state_name'   => 'Florida',
                'county'       => 'Pinellas',
                'latitude'     => 27.7756540,
                'longitude'    => -82.6409200,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    /** Audit scenario 1 — the originally reported bug. */
    public function test_43434_is_rejected_as_a_street_number(): void
    {
        $validator = $this->validateAddress('43434');

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'street number',
            $validator->errors()->first('address')
        );
    }

    /** Audit scenario 2 — a real ZIP typed into the street field. */
    public function test_33708_is_rejected_as_a_zip_code_with_a_zip_specific_message(): void
    {
        $validator = $this->validateAddress('33708');

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'ZIP code',
            $validator->errors()->first('address')
        );
    }

    /**
     * The two five-digit cases must not collapse into one message — that is the
     * whole point of consulting the gazetteer.
     */
    public function test_the_two_five_digit_cases_produce_different_messages(): void
    {
        $streetNumber = $this->validateAddress('43434')->errors()->first('address');
        $zipCode      = $this->validateAddress('33708')->errors()->first('address');

        $this->assertNotSame($streetNumber, $zipCode);
    }

    public function test_a_real_pinellas_address_passes(): void
    {
        $this->assertFalse($this->validateAddress('100 2nd Ave N, St. Petersburg')->fails());
        $this->assertFalse($this->validateAddress('1 Beach Dr SE, St Petersburg FL 33701')->fails());
        $this->assertFalse($this->validateAddress('13801 Walsingham Rd, Largo FL 33774')->fails());
    }

    public function test_an_empty_address_is_rejected(): void
    {
        $this->assertTrue($this->validateAddress('')->fails());
    }

    public function test_lookup_resolves_a_florida_zip_to_its_location(): void
    {
        $row = app(ZipCodeLookupService::class)->find('33708');

        $this->assertSame('Saint Petersburg', $row['city']);
        $this->assertSame('Pinellas', $row['county']);
        $this->assertSame('FL', $row['state']);
        $this->assertSame('Florida', $row['state_name']);
        $this->assertEqualsWithDelta(27.811608, $row['lat'], 0.0001);
    }

    public function test_lookup_accepts_zip_plus_four_and_rejects_non_zips(): void
    {
        $lookup = app(ZipCodeLookupService::class);

        $this->assertTrue($lookup->isKnownZip('33708-1234'));
        $this->assertFalse($lookup->isKnownZip('43434'));
        $this->assertFalse($lookup->isKnownZip('not a zip'));
        $this->assertNull($lookup->normalizeZip('3370'));
        $this->assertSame('33708', $lookup->normalizeZip(' 33708-1234 '));
    }

    /**
     * A ZIP centroid is not a property location. It must always announce itself
     * so nothing downstream can mistake it for a geocoded coordinate.
     */
    public function test_centroid_is_tagged_with_its_provenance(): void
    {
        $centroid = app(ZipCodeLookupService::class)->centroidFor('33701');

        $this->assertSame(ZipCodeLookupService::SOURCE, $centroid['source']);
        $this->assertSame('zip_centroid', $centroid['source']);
    }

    private function validateAddress(string $value): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['address' => $value],
            ['address' => ['required', 'string', new ValidStreetAddress()]]
        );
    }
}
