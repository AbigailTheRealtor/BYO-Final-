<?php

namespace App\Http\Livewire\Concerns;

use App\Rules\ValidStreetAddress;
use App\Services\Location\AddressShapeValidator;
use App\Services\Location\ZipCodeLookupService;

/**
 * Phase 0 — shared property-address behaviour for the four Seller/Landlord flows.
 *
 * Mixed into Create Seller, Create Landlord, Hire Seller and Hire Landlord
 * (create + edit — eight components). All eight already declare an identical set
 * of address props — `address`, `unit_address`, `property_city`,
 * `property_county`, `property_state`, `property_zip`, `zip_code` — which is why
 * one trait can serve them without a per-role adapter.
 *
 * WHAT IT PROVIDES
 * ----------------
 *   1. `propertyAddressRules()` — the server-side street-address rule, to merge
 *      into each component's publish rules.
 *   2. ZIP → City / County / State autofill from `us_zip_codes`, with **no
 *      external call**, restoring most of what died with the Google credential.
 *   3. Recovery for a ZIP typed into the Street Address field: the digits are
 *      moved to the ZIP field, the location is filled in, and the user is told
 *      what happened rather than being handed a bare rejection.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * It never writes coordinates. A ZIP centroid is not a property location and
 * writing one into `property_lat` / `property_lng` would silently poison the
 * matching engine the moment Phase 4 switches the geo narrower on. Coordinates
 * stay empty until a real geocoder fills them in Phase 2.
 *
 * PRIVACY
 * -------
 * Untouched. This trait only ever writes City / County / State / ZIP — the
 * fields already shown publicly. It never changes what is displayed, and the
 * street line remains agent-only-after-hire exactly as before.
 *
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §8 Phase 0
 */
trait ValidatesPropertyAddress
{
    /**
     * Set when a ZIP typed into the Street Address field was moved to the ZIP
     * field. The Blade partial surfaces it as an inline explanation.
     */
    public string $addressAssistNotice = '';

    /**
     * Street-address + unit rules, to merge into a component's publish rules.
     * Kept as a method (not a property) so the rule object is built per
     * validation pass and carries no state between requests.
     *
     * @return array<string,mixed>
     */
    protected function propertyAddressRules(): array
    {
        return ValidStreetAddress::rules();
    }

    /**
     * Livewire hook body for `updated($property, $value)`. Call from the host
     * component's `updated()` when it defines one; components without their own
     * hook can rely on the trait's `updatedZipCode` / `updatedAddress` below.
     */
    protected function handlePropertyAddressUpdate(string $property, $value): void
    {
        if ($property === 'zip_code') {
            $this->applyZipLocation((string) $value);
        }

        if ($property === 'address') {
            $this->recoverZipTypedIntoStreetField((string) $value);
        }
    }

    /**
     * Fill City / County / State from a ZIP using the owned gazetteer.
     *
     * Non-destructive by default: an existing value is never overwritten, so a
     * user who deliberately corrected the city keeps their correction. Pass
     * `$overwrite = true` only where the user explicitly picked a ZIP suggestion.
     */
    public function applyZipLocation(?string $zip, bool $overwrite = false): bool
    {
        $row = $this->zipLookup()->find($zip);

        if ($row === null) {
            return false;
        }

        $this->fillIfEmpty('property_city', $row['city'], $overwrite);
        $this->fillIfEmpty('property_county', $row['county'], $overwrite);
        $this->fillIfEmpty('property_state', $row['state'], $overwrite);
        $this->fillIfEmpty('property_zip', $row['zip'], $overwrite);

        // `state` is the discrete prop the match engine and public display read.
        // It carries the full state name on these components, not the abbreviation.
        $this->fillIfEmpty('state', $row['state_name'], $overwrite);

        return true;
    }

    /**
     * When the whole Street Address field is a real US ZIP, move it to the ZIP
     * field, autofill the location, clear the street field, and explain.
     *
     * Recovering the input beats rejecting it: the user gave us something
     * usable, just in the wrong box.
     */
    public function recoverZipTypedIntoStreetField(?string $value): bool
    {
        $shape = $this->addressShape();

        if ($shape->reasonFor($value) !== AddressShapeValidator::ZIP_SHAPED) {
            return false;
        }

        $zips = $this->zipLookup();
        $zip = $zips->normalizeZip($value);

        if ($zip === null || ! $zips->isKnownZip($zip)) {
            return false;
        }

        if (property_exists($this, 'zip_code') && trim((string) $this->zip_code) === '') {
            $this->zip_code = $zip;
        }

        $this->applyZipLocation($zip);
        $this->address = '';

        $row = $zips->find($zip);
        $this->addressAssistNotice = sprintf(
            'We moved %s to the ZIP Code field and filled in %s, %s County, %s. Enter the street address above — for example, 100 2nd Ave N.',
            $zip,
            $row['city'],
            $row['county'],
            $row['state']
        );

        return true;
    }

    /** Livewire default hook — active only when the host defines no `updated()`. */
    public function updatedZipCode($value): void
    {
        $this->applyZipLocation((string) $value);
    }

    /** Livewire default hook — active only when the host defines no `updated()`. */
    public function updatedAddress($value): void
    {
        $this->addressAssistNotice = '';
        $this->recoverZipTypedIntoStreetField((string) $value);
    }

    /**
     * Assign only when the prop exists on the host and is currently blank
     * (or when the caller explicitly asked to overwrite).
     */
    private function fillIfEmpty(string $prop, string $value, bool $overwrite): void
    {
        if ($value === '' || ! property_exists($this, $prop)) {
            return;
        }

        if ($overwrite || trim((string) $this->{$prop}) === '') {
            $this->{$prop} = $value;
        }
    }

    private function zipLookup(): ZipCodeLookupService
    {
        return app(ZipCodeLookupService::class);
    }

    private function addressShape(): AddressShapeValidator
    {
        return app(AddressShapeValidator::class);
    }
}
