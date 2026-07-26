<?php

namespace App\Rules;

use App\Services\Location\AddressShapeValidator;
use App\Services\Location\ZipCodeLookupService;
use Illuminate\Contracts\Validation\Rule;

/**
 * Phase 0 — `required|string` is not address validation.
 *
 * Server-side guard for the property street-address field on every Seller and
 * Landlord surface (Create + Hire, create + edit). Rejects the shapes the audit
 * caught reaching the database: a bare street number (`43434`), a ZIP typed into
 * the street field (`33708`), a lone word (`Main`), and punctuation.
 *
 * ZIP-shaped input gets a sharper message when the digits are a real US ZIP,
 * because then we know precisely what the user meant and can say so. That check
 * costs one cached query against data we already own — no external call.
 *
 * This is a SHAPE rule, not an existence check. It cannot tell you whether
 * 100 2nd Ave N exists; that is geocoding, and it lands in Phase 2. The value of
 * shipping the shape rule first is that it closes the hole immediately and
 * without depending on either unresolved architecture decision.
 *
 * @see \App\Services\Location\AddressShapeValidator
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §8 Phase 0
 */
class ValidStreetAddress implements Rule
{
    private AddressShapeValidator $shape;

    private ?ZipCodeLookupService $zips;

    private string $message = '';

    public function __construct(
        ?AddressShapeValidator $shape = null,
        ?ZipCodeLookupService $zips = null
    ) {
        $this->shape = $shape ?? new AddressShapeValidator();
        $this->zips = $zips;
    }

    /**
     * The canonical street + unit rule pair, defined once.
     *
     * Every Seller/Landlord publish path calls this rather than restating the
     * rules, so the eight components cannot drift apart the way their four
     * copies of `fillFromGooglePlaces()` did.
     *
     * @return array<string,array<int,mixed>>
     */
    public static function rules(): array
    {
        return [
            'address'      => ['required', 'string', 'max:255', new self()],
            'unit_address' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (! is_string($value) && ! is_null($value)) {
            $this->message = $this->shape->messageFor(AddressShapeValidator::EMPTY_VALUE);

            return false;
        }

        $reason = $this->shape->reasonFor($value);

        if ($reason === AddressShapeValidator::OK) {
            return true;
        }

        $this->message = $this->resolveMessage($reason, (string) $value);

        return false;
    }

    public function message(): string
    {
        return $this->message !== ''
            ? $this->message
            : $this->shape->messageFor(AddressShapeValidator::NUMBER_ONLY);
    }

    /**
     * ZIP-shaped input splits two ways: a real US ZIP gets the "we know what you
     * meant" message; five digits that are not a ZIP are a street number.
     */
    private function resolveMessage(string $reason, string $value): string
    {
        if ($reason !== AddressShapeValidator::ZIP_SHAPED) {
            return $this->shape->messageFor($reason);
        }

        $zips = $this->zips ?? app(ZipCodeLookupService::class);

        if ($zips->isKnownZip($value)) {
            return $this->shape->messageFor(AddressShapeValidator::ZIP_SHAPED);
        }

        return $this->shape->messageFor(AddressShapeValidator::NUMBER_ONLY);
    }
}
