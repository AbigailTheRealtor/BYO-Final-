<?php

namespace App\Http\Livewire\Concerns;

use App\Rules\ValidStreetAddress;
use App\Services\Location\AddressShapeValidator;
use App\Services\Location\ZipCodeLookupService;

/**
 * Phase 0 — shared property-address behaviour for the Seller/Landlord surfaces.
 *
 * Three things, in one place, so the eight components cannot drift apart the way
 * their four copies of `fillFromResolvedAddress()` did:
 *
 *   1. The canonical validation rules, delegated to {@see ValidStreetAddress}.
 *   2. ZIP-in-street-field recovery — the user typed `33708` where the street
 *      address goes. We know what they meant, so we move it and say so.
 *   3. ZIP → City / County / State autofill, from the `us_zip_codes` gazetteer
 *      we already own. Zero external calls, zero credentials.
 *
 * WHY THESE ARE NOT `updated*` HOOKS
 * ----------------------------------
 * Four of the eight components (all four Edit variants) already declare their own
 * `updatedAddress()`. A trait method of the same name loses to the class method
 * silently in PHP — the four Create components would get the trait behaviour and
 * the four Edit components would not, which is precisely the create/edit fork
 * this phase exists to close. So the entry points are explicitly named and every
 * component calls them deliberately.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * ----------------------------------
 * It never writes coordinates. A ZIP centroid is the middle of a postal area that
 * can span several miles; storing one as a property location would poison the
 * geo narrower that arrives in a later phase. {@see ZipCodeLookupService::centroidFor()}
 * exists for map framing and is not called here.
 *
 * It never overwrites a value the user has already put in a field. Autofill is a
 * convenience, not an authority — a user who corrected the county by hand must
 * not have that correction reverted by a later ZIP entry. The one exception is an
 * explicit ZIP selection, which passes `$force = true` because picking a ZIP from
 * the list is an unambiguous statement of intent.
 *
 * @see \App\Rules\ValidStreetAddress
 * @see \App\Services\Location\ZipCodeLookupService
 */
trait ValidatesPropertyAddress
{
    /**
     * Inline explanation shown when we move a ZIP out of the street field.
     *
     * Empty means "nothing to explain". Rendered by
     * `resources/views/components/address-assist-notice.blade.php`.
     */
    public $addressAssistNotice = '';

    /**
     * The canonical street + unit rules. Defined once, in the rule class.
     *
     * @return array<string,array<int,mixed>>
     */
    protected function propertyAddressRules(): array
    {
        return ValidStreetAddress::rules();
    }

    /**
     * Messages for the rules above. The rule supplies its own message for the
     * shape failures; these cover the framework-level rules around it.
     *
     * @return array<string,string>
     */
    protected function propertyAddressMessages(): array
    {
        return [
            'address.required' => (new AddressShapeValidator())
                ->messageFor(AddressShapeValidator::EMPTY_VALUE),
            'address.max'      => 'That street address is too long. Enter just the street address — the city, state and ZIP have their own fields.',
            'unit_address.max' => 'Unit / Apt is limited to 100 characters.',
        ];
    }

    /**
     * The recovery path: a real US ZIP typed into the street-address field.
     *
     * `33708` is not a street address, but it is not junk either — it is a ZIP in
     * the wrong box. Rejecting it with "enter a street address" would be true and
     * useless. Instead we move it to the ZIP field, autofill what it tells us, and
     * explain what happened, so the user's next action is to type the street line
     * rather than to work out what we objected to.
     *
     * Digits that are ZIP-shaped but not a real ZIP (`43434`) are left alone — we
     * have no evidence they were meant as a ZIP, so moving them would be a guess.
     * Those fall through to {@see ValidStreetAddress} and its street-number message.
     *
     * @return bool True when a ZIP was moved out of the address field.
     */
    public function assistPropertyAddress(): bool
    {
        $this->addressAssistNotice = '';

        $shape = new AddressShapeValidator();
        $typed = $shape->normalize($this->address ?? '');

        if ($typed === '' || $shape->reasonFor($typed) !== AddressShapeValidator::ZIP_SHAPED) {
            return false;
        }

        $lookup = app(ZipCodeLookupService::class);
        $zip    = $lookup->normalizeZip($typed);

        if ($zip === null || ! $lookup->isKnownZip($zip)) {
            return false;
        }

        $this->address = '';

        if ($this->isBlankAddressField($this->zip_code ?? null)) {
            $this->zip_code = $zip;
        }

        $this->applyZipAutofill($zip);

        $this->addressAssistNotice = "We moved {$zip} to the ZIP Code field and filled in the location it belongs to. Enter the street address here — for example, 100 2nd Ave N.";

        return true;
    }

    /**
     * Fill City / County / State from a ZIP, without external calls.
     *
     * Only writes fields that are currently blank, unless `$force` is set — see
     * the class docblock for why. Returns the field names it actually wrote so a
     * caller can tell "filled three things" from "filled nothing".
     *
     * @return array<int,string>
     */
    protected function applyZipAutofill(?string $zip, bool $force = false): array
    {
        $row = app(ZipCodeLookupService::class)->find($zip);

        if ($row === null) {
            return [];
        }

        $filled = [];

        if (property_exists($this, 'property_city')
            && ($force || $this->isBlankAddressField($this->property_city))) {
            $this->property_city = $row['city'];
            $filled[] = 'property_city';
        }

        if (property_exists($this, 'property_county')
            && ($force || $this->isBlankAddressField($this->property_county))) {
            $this->property_county = $this->formatCounty($row['county'], $row['state']);
            $filled[] = 'property_county';
        }

        if (property_exists($this, 'state')
            && ($force || $this->isBlankAddressField($this->state))) {
            $this->state = $row['state_name'];
            $filled[] = 'state';
        }

        if (property_exists($this, 'property_zip')
            && ($force || $this->isBlankAddressField($this->property_zip))) {
            $this->property_zip = $row['zip'];
            $filled[] = 'property_zip';
        }

        // No coordinate is written here. Deliberate — see the class docblock.

        return $filled;
    }

    /**
     * An explicit ZIP selection is an unambiguous statement of intent, so it may
     * overwrite values a previous ZIP filled in.
     *
     * @return array<int,string>
     */
    public function selectPropertyZip(?string $zip): array
    {
        $normalized = app(ZipCodeLookupService::class)->normalizeZip($zip);

        if ($normalized === null) {
            return [];
        }

        $this->zip_code = $normalized;

        return $this->applyZipAutofill($normalized, true);
    }

    /**
     * "Pinellas" → "Pinellas County, FL", matching the format the existing
     * city/county autocomplete already writes to this field.
     */
    private function formatCounty(string $county, string $stateAbbrev): string
    {
        $county = trim($county);

        if (! str_contains(strtolower($county), 'county')) {
            $county .= ' County';
        }

        return $county . ', ' . strtoupper($stateAbbrev);
    }

    /**
     * Blank means blank — not "0", and not an array that happens to be empty.
     */
    private function isBlankAddressField($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
