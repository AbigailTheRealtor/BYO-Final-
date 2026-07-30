<?php

namespace App\Services\LocationDna\Contract;

/**
 * Dimension — the canonical Location DNA dimensions of the v1.2 §5.1 contract.
 *
 * G1c contract core. INERT: nothing in this namespace is wired into any existing
 * production workflow. See the approved decision package
 * docs/architecture/LOCATION-DNA-ENGINE-V1.2-G1C-DECISION-PACKAGE.md (D-G1-1).
 *
 * WHAT IS AND IS NOT A DIMENSION
 * -----------------------------
 * Exactly the nine keys §5.1 tabulates, plus `subject_property`. Deliberately absent:
 *
 *   - `important_places_json` — §5.1 keeps it a SEPARATE meta key. Merging it would be
 *     a migration with no benefit, so it is not a dimension here.
 *   - `commute` — withdrawn from v1 entirely by §18. No placeholder is created: adding a
 *     dimension with no UI, no provider and no consumer is the speculative schema §18
 *     rejects.
 *   - `neighborhoods` — withdrawn from the contract by §18 but retained **read-tolerant**.
 *     It is therefore not authorable and not settable; where a legacy record carries it,
 *     it survives round-trip through the document's extension bag
 *     ({@see LocationDnaDocument::extensions()}) without ever being interpreted.
 *
 * KIND drives the canonical empty value, which is what "present but cleared" means for
 * that dimension (§5.2, and D-G1-1's approved clarification that an empty array is the
 * canonical cleared value for collection dimensions).
 */
enum Dimension: string
{
    case Cities           = 'cities';
    case ZipCodes         = 'zip_codes';
    case Counties         = 'counties';
    case State            = 'state';
    case Polygons         = 'polygons';
    case RadiusSearches   = 'radius_searches';
    case FlexibleLocation = 'flexible_location';
    case LocationNotes    = 'location_notes';
    case SubjectProperty  = 'subject_property';

    /** Structural family, which fixes the canonical empty and the validation rule. */
    public function kind(): DimensionKind
    {
        return match ($this) {
            self::Cities, self::ZipCodes, self::Counties => DimensionKind::StringList,
            self::Polygons, self::RadiusSearches         => DimensionKind::ObjectList,
            self::State, self::LocationNotes             => DimensionKind::Text,
            self::FlexibleLocation                       => DimensionKind::Flag,
            self::SubjectProperty                        => DimensionKind::Map,
        };
    }

    /**
     * The canonical empty value — i.e. what this dimension looks like when it is
     * present-but-cleared (§5.1 "Canonical empty" column).
     */
    public function canonicalEmpty(): array|string|bool
    {
        return match ($this->kind()) {
            DimensionKind::StringList, DimensionKind::ObjectList, DimensionKind::Map => [],
            DimensionKind::Text                                                      => '',
            DimensionKind::Flag                                                      => false,
        };
    }

    /** True when $value is exactly this dimension's canonical empty. */
    public function isCanonicalEmpty(mixed $value): bool
    {
        return $value === $this->canonicalEmpty();
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_map(static fn (self $d): string => $d->value, self::cases());
    }

    public static function tryFromKey(string $key): ?self
    {
        return self::tryFrom($key);
    }

    /**
     * Keys that are recognised but withdrawn from the writable contract (§18).
     *
     * Retained read-tolerant: a legacy record carrying one round-trips unchanged, but no
     * v1.2 writer emits it and no `set` command may target it.
     *
     * @return list<string>
     */
    public static function withdrawnKeys(): array
    {
        return ['neighborhoods'];
    }
}
