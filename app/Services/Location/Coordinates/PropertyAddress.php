<?php

namespace App\Services\Location\Coordinates;

/**
 * The address of one specific property, as handed to coordinate resolution.
 *
 * Provider-neutral and framework-neutral by construction: it holds strings, not
 * a Livewire component, an Eloquent model or a role. Seller, Landlord, Buyer,
 * Tenant, MLS import and any future flow all build one of these, which is what
 * lets a single resolver serve all of them.
 *
 * TWO NORMALIZATIONS, DELIBERATELY DIFFERENT
 * ------------------------------------------
 * A condo raises a question the rest of the address never does: are
 * `123 Main St Unit 4A` and `123 Main St Unit 4B` the same thing? For finding a
 * point on the earth, yes — no US geocoder resolves individual units, and asking
 * one to try lowers the match rate. For deciding which listing you are looking
 * at, absolutely not — they are different properties with different owners.
 *
 * So this object answers both questions separately:
 *
 *   coordinateLookupLine()  drops the unit   — what a geocoder is asked
 *   propertyIdentityLine()  keeps the unit   — what distinguishes the property
 *
 * A coordinate obtained from the unit-stripped line is a *building* coordinate.
 * It must be recorded as {@see CoordinatePrecision::Parcel}, never Rooftop —
 * calling it rooftop would claim we located unit 4A specifically when we located
 * the building. {@see PropertyCoordinateResult::forBuilding()} enforces that.
 *
 * NOT THE PROPERTY FINGERPRINT
 * ----------------------------
 * `identityFingerprintInput()` returns the canonical string a fingerprint would
 * be computed *from*. The fingerprint system itself (hashing, storage,
 * collision policy, cross-source identity) is a later phase and is deliberately
 * not built here — this only guarantees that when it arrives, the string it
 * consumes is already deterministic and unit-aware.
 *
 * PROVENANCE HANDLES (added in G2) — NOT PART OF THE ADDRESS
 * ---------------------------------------------------------
 * `listingType` / `listingId` / `mlsListingKey` are record handles, not address
 * components. They exist because the two local adapters cannot do their job from
 * a postal address alone: reusing a coordinate we already possess means finding
 * the row that possesses it, and the only honest way to find that row is an
 * exact record key. The alternative — matching a stored coordinate to an address
 * by similarity — is precisely the guessing this design refuses to do.
 *
 * They are deliberately absent from `coordinateLookupLine()`,
 * `propertyIdentityLine()`, `identityFingerprintInput()`,
 * `coordinateCacheKeyInput()` and `hasMinimumForLookup()`. A property's identity
 * and its cache key are properties of the address, not of which table happens to
 * hold it today; letting a listing id leak into either would give one physical
 * property as many identities as it has rows, and would defeat the unit-free
 * cache sharing that exists to keep provider calls down.
 */
final class PropertyAddress
{
    public function __construct(
        public readonly string $address = '',
        public readonly string $unitAddress = '',
        public readonly string $city = '',
        public readonly string $county = '',
        public readonly string $state = '',
        public readonly string $zip = '',
        public readonly string $listingType = '',
        public readonly ?int $listingId = null,
        public readonly string $mlsListingKey = '',
    ) {
    }

    /**
     * True when this address carries a listing handle an already-stored
     * coordinate could be looked up by.
     */
    public function hasListingHandle(): bool
    {
        return $this->listingType !== '' && $this->listingId !== null;
    }

    /** True when this address carries an MLS/Bridge record key. */
    public function hasMlsListingKey(): bool
    {
        return trim($this->mlsListingKey) !== '';
    }

    /**
     * Build from the loose associative array the listing components already pass
     * around, so call sites do not have to restate key names.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            address:     (string) ($data['address']         ?? ''),
            unitAddress: (string) ($data['unit_address']    ?? ''),
            city:        (string) ($data['property_city']   ?? $data['city']   ?? ''),
            county:      (string) ($data['property_county'] ?? $data['county'] ?? ''),
            state:       (string) ($data['property_state']  ?? $data['state']  ?? ''),
            zip:         (string) ($data['property_zip']    ?? $data['zip']    ?? ''),

            // Provenance handles — optional, and never part of the address itself.
            listingType:   (string) ($data['listing_type'] ?? ''),
            listingId:     isset($data['listing_id']) && $data['listing_id'] !== ''
                ? (int) $data['listing_id']
                : null,
            mlsListingKey: (string) ($data['mls_listing_key'] ?? $data['listing_key'] ?? ''),
        );
    }

    /**
     * The line a geocoder is asked to resolve — **without** the unit.
     *
     * Street, city, state and ZIP5 only. County is omitted: it is not part of a
     * postal address and providers either ignore it or match it inconsistently.
     */
    public function coordinateLookupLine(): string
    {
        return $this->joinParts([
            $this->normalizeStreet($this->address),
            $this->normalizeToken($this->city),
            $this->normalizeState($this->state),
            $this->zip5($this->zip),
        ]);
    }

    /**
     * The line that identifies this property uniquely — **with** the unit.
     *
     * Two units in one building differ here and nowhere else, which is exactly
     * the property this representation exists to preserve.
     */
    public function propertyIdentityLine(): string
    {
        return $this->joinParts([
            $this->normalizeStreet($this->address),
            $this->normalizeUnit($this->unitAddress),
            $this->normalizeToken($this->city),
            $this->normalizeState($this->state),
            $this->zip5($this->zip),
        ]);
    }

    /**
     * Deterministic input for the future property fingerprint. Same address in
     * any capitalisation, spacing or punctuation yields the same string.
     */
    public function identityFingerprintInput(): string
    {
        return $this->propertyIdentityLine();
    }

    /**
     * Deterministic cache key input for coordinate lookups. Unit-free, so every
     * unit in a building shares one cached building coordinate and one provider
     * call rather than one call per unit.
     */
    public function coordinateCacheKeyInput(): string
    {
        return $this->coordinateLookupLine();
    }

    /** True when there is enough here to attempt a coordinate lookup at all. */
    public function hasMinimumForLookup(): bool
    {
        if ($this->normalizeStreet($this->address) === '') {
            return false;
        }

        // A street line alone is ambiguous nationwide; it needs either a ZIP or
        // a city+state pair to land in one place.
        return $this->zip5($this->zip) !== ''
            || ($this->normalizeToken($this->city) !== '' && $this->normalizeState($this->state) !== '');
    }

    /** True when a unit/apt/suite was supplied. */
    public function hasUnit(): bool
    {
        return $this->normalizeUnit($this->unitAddress) !== '';
    }

    // ── normalization ────────────────────────────────────────────────────────

    /**
     * Lowercase, strip punctuation, collapse whitespace. The shared floor every
     * other normalizer builds on.
     */
    private function normalizeToken(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace('.', '', $value);            // "st." -> "st"
        $value = preg_replace('/[^a-z0-9# ]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Street line normalization: token floor plus USPS-style suffix and
     * directional folding, so "123 North Main Street" and "123 N Main St"
     * converge on one string.
     *
     * Intentionally a small, conservative map. It is not a USPS CASS
     * implementation and does not try to be — a wrong expansion is worse than a
     * missed one, because it changes the identity of a property.
     */
    private function normalizeStreet(string $value): string
    {
        $normalized = $this->normalizeToken($value);

        if ($normalized === '') {
            return '';
        }

        static $map = [
            // directionals
            'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w',
            'northeast' => 'ne', 'northwest' => 'nw',
            'southeast' => 'se', 'southwest' => 'sw',
            // common suffixes
            'street' => 'st', 'avenue' => 'ave', 'boulevard' => 'blvd',
            'road' => 'rd', 'drive' => 'dr', 'lane' => 'ln', 'court' => 'ct',
            'place' => 'pl', 'terrace' => 'ter', 'parkway' => 'pkwy',
            'circle' => 'cir', 'highway' => 'hwy', 'trail' => 'trl',
            'square' => 'sq',
        ];

        $parts = array_map(
            static fn (string $part): string => $map[$part] ?? $part,
            explode(' ', $normalized)
        );

        return implode(' ', array_filter($parts, static fn ($p) => $p !== ''));
    }

    /**
     * Unit normalization: fold every designator ("apt", "suite", "#", "unit")
     * to the bare identifier, so "Unit 4A", "#4a" and "Apt. 4-A" agree.
     *
     * The designator carries no identity — 4A is 4A whether the building calls
     * it a unit or a suite. What must survive is the identifier itself.
     */
    private function normalizeUnit(string $value): string
    {
        $normalized = $this->normalizeToken($value);

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace(
            '/\b(apartment|apt|suite|ste|unit|number|no|rm|room|floor|fl|bldg|building)\b/',
            ' ',
            $normalized
        ) ?? '';

        $normalized = str_replace('#', ' ', $normalized);
        $normalized = str_replace(' ', '', $normalized);   // "4 a" -> "4a"

        return trim($normalized);
    }

    /** Two-letter state code where recognisable, else the normalized token. */
    private function normalizeState(string $value): string
    {
        $normalized = $this->normalizeToken($value);

        if (strlen($normalized) === 2) {
            return $normalized;
        }

        static $states = [
            'alabama' => 'al', 'alaska' => 'ak', 'arizona' => 'az', 'arkansas' => 'ar',
            'california' => 'ca', 'colorado' => 'co', 'connecticut' => 'ct',
            'delaware' => 'de', 'florida' => 'fl', 'georgia' => 'ga', 'hawaii' => 'hi',
            'idaho' => 'id', 'illinois' => 'il', 'indiana' => 'in', 'iowa' => 'ia',
            'kansas' => 'ks', 'kentucky' => 'ky', 'louisiana' => 'la', 'maine' => 'me',
            'maryland' => 'md', 'massachusetts' => 'ma', 'michigan' => 'mi',
            'minnesota' => 'mn', 'mississippi' => 'ms', 'missouri' => 'mo',
            'montana' => 'mt', 'nebraska' => 'ne', 'nevada' => 'nv',
            'new hampshire' => 'nh', 'new jersey' => 'nj', 'new mexico' => 'nm',
            'new york' => 'ny', 'north carolina' => 'nc', 'north dakota' => 'nd',
            'ohio' => 'oh', 'oklahoma' => 'ok', 'oregon' => 'or',
            'pennsylvania' => 'pa', 'rhode island' => 'ri', 'south carolina' => 'sc',
            'south dakota' => 'sd', 'tennessee' => 'tn', 'texas' => 'tx',
            'utah' => 'ut', 'vermont' => 'vt', 'virginia' => 'va',
            'washington' => 'wa', 'west virginia' => 'wv', 'wisconsin' => 'wi',
            'wyoming' => 'wy', 'district of columbia' => 'dc',
        ];

        return $states[$normalized] ?? $normalized;
    }

    /**
     * ZIP5. ZIP+4 is truncated: the +4 identifies a delivery segment, not a
     * property, and carrying it would split one address into several identities
     * depending on which form somebody typed.
     */
    private function zip5(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) >= 5 ? substr($digits, 0, 5) : '';
    }

    /** @param array<int, string> $parts */
    private function joinParts(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}
