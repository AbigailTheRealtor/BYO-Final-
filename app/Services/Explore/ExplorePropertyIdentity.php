<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;

/**
 * A stable identity for a PHYSICAL PROPERTY, distinct from the identity of a
 * listing.
 *
 * WHY A SEPARATE IDENTITY AT ALL
 * ------------------------------
 * `ListingKey` identifies one MLS listing. One house can have several over
 * time, and the future Property Intelligence tier is the feature that needs to
 * group them. Building that grouping rule now — while there is nothing
 * historical to group — is what makes the later tier an addition rather than a
 * re-architecture, and it costs nothing today because Explore already needs a
 * per-property key for its markers.
 *
 * THE LADDER, MOST SPECIFIC FIRST
 * -------------------------------
 *  1. ParcelNumber + UnitNumber. The parcel is the assessor's own identifier —
 *     the closest thing to a durable physical-property key this feed carries
 *     (1,090 of 1,225 live records have one). The unit is appended because a
 *     parcel is routinely the WHOLE BUILDING: in the live cache, unit 17208 and
 *     the building's income listing share parcel 183116855380170208 exactly.
 *     Dropping the unit would merge two condominiums into one property.
 *  2. The unit-preserving normalised address —
 *     {@see \App\Services\Location\Coordinates\PropertyAddress::propertyIdentityLine()},
 *     which exists precisely because `coordinateLookupLine()` drops the unit and
 *     this question needs it kept.
 *  3. Nothing. A record with neither resolves to null and is treated as its own
 *     listing, never grouped.
 *
 * COORDINATES ARE NEVER AN IDENTITY
 * ---------------------------------
 * Two records near each other are not one property, and every unit in a tower
 * shares one rooftop coordinate. Proximity is deliberately absent from this
 * class and must stay absent: the moment it is added, a forty-unit building
 * becomes one "property" with forty conflicting histories.
 *
 * MULTI-PARCEL RECORDS
 * --------------------
 * ParcelNumber is sometimes a list ("362500109, 362500159" in the live cache).
 * The whole string is used verbatim rather than split: it identifies THIS
 * assemblage, and picking the first parcel would silently identify a different,
 * smaller property.
 *
 * WHAT LEAVES THE SERVER
 * ----------------------
 * Only {@see opaqueId()}. A parcel number is an assessor identifier that leads
 * to owner records, so the raw components are never serialised — the consumer
 * gets an unguessable, stable handle and nothing else.
 */
class ExplorePropertyIdentity
{
    public const SOURCE_PARCEL   = 'parcel';
    public const SOURCE_ADDRESS  = 'address';
    public const SOURCE_LISTING  = 'listing';

    /**
     * @param array<string,mixed> $raw
     * @return array{source:string, opaque_id:string}
     */
    public function for(BridgeProperty $listing, array $raw): array
    {
        $parcel = $this->trimmed($raw['ParcelNumber'] ?? null);

        if ($parcel !== null) {
            $unit = $this->trimmed($raw['UnitNumber'] ?? null);

            return [
                'source'    => self::SOURCE_PARCEL,
                'opaque_id' => $this->hash('parcel', $parcel . '|' . ($unit ?? '')),
            ];
        }

        $address = $this->identityLine($raw);

        if ($address !== null) {
            return [
                'source'    => self::SOURCE_ADDRESS,
                'opaque_id' => $this->hash('address', $address),
            ];
        }

        // No physical identity is derivable. The listing is its own property,
        // and will never be grouped with another — which is the correct,
        // conservative answer rather than a fallback that guesses.
        return [
            'source'    => self::SOURCE_LISTING,
            'opaque_id' => $this->hash('listing', (string) ($listing->listing_key ?? $listing->getKey())),
        ];
    }

    /**
     * The unit-preserving address line, built from the feed's own components.
     *
     * Assembled here rather than through PropertyAddress::fromArray() because
     * that helper expects BidYourOffer's own field names; the normalisation
     * rule — upper-case, collapse whitespace, keep the unit — is what matters
     * and is reproduced faithfully.
     *
     * @param array<string,mixed> $raw
     */
    private function identityLine(array $raw): ?string
    {
        $street = $this->trimmed($raw['UnparsedAddress'] ?? null);
        $city   = $this->trimmed($raw['City'] ?? null);
        $state  = $this->trimmed($raw['StateOrProvince'] ?? null);
        $zip    = $this->trimmed($raw['PostalCode'] ?? null);
        $unit   = $this->trimmed($raw['UnitNumber'] ?? null);

        if ($street === null || $city === null) {
            return null;
        }

        $parts = array_filter([
            $street,
            $unit !== null ? 'UNIT ' . $unit : null,
            $city,
            $state,
            $zip,
        ]);

        $line = strtoupper(implode(' ', $parts));

        return trim(preg_replace('/\s+/', ' ', $line) ?? '');
    }

    /**
     * A namespaced, unguessable handle.
     *
     * Namespaced by source so a parcel string and an address line that happen to
     * be byte-identical cannot collide, and truncated to 32 hex characters —
     * long enough that collision is not a practical concern, short enough to sit
     * in a URL.
     */
    private function hash(string $namespace, string $value): string
    {
        return substr(hash('sha256', $namespace . "\0" . $value), 0, 32);
    }

    private function trimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
