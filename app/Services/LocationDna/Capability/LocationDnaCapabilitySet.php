<?php

namespace App\Services\LocationDna\Capability;

use App\Services\LocationDna\Contract\Dimension;

/**
 * LocationDnaCapabilitySet — an immutable, default-deny resolution result.
 *
 * G1d inert capability resolver. INERT.
 *
 * DEFAULT DENY IS THE CONSTRUCTION, NOT A FALLBACK
 * ------------------------------------------------
 * v1.2 §7.2: "Deny by default. A dimension not affirmatively enabled for the resolved context is
 * denied. An unrecognised context, a missing profile or a typo resolves to deny, never to permit."
 * This class starts from {@see self::deniedAll()} and only ever adds affirmative grants, so a
 * capability absent from the grant list is denied because it was never granted — not because a
 * default happened to be false.
 *
 * NO IMPLICATION BETWEEN CAPABILITIES
 * -----------------------------------
 * `allows()` consults only the exact capability asked about. Reading does not imply editing;
 * exposing administrative labels does not imply exposing geometry; exposing geometry does not
 * imply exposing `location_notes`. There is deliberately no inference table.
 *
 * DIMENSION MUTATION IS SEPARATE FROM EDITING
 * -------------------------------------------
 * `EditDocument` is the precondition; a per-dimension grant is the permission. Both are required,
 * which is §7.3's "every envelope is authorised against (principal, record, dimension)" expressed
 * as data. Dimension grants use the G1c {@see Dimension} enum rather than strings, so an unknown
 * dimension cannot be named at all.
 *
 * IMMUTABLE
 * ---------
 * `readonly` properties, and the accessors return value copies. A caller cannot widen a set it
 * has been handed.
 */
final class LocationDnaCapabilitySet
{
    /**
     * @param  array<string, true>  $granted            capability value => true
     * @param  array<string, true>  $settableDimensions dimension value => true
     * @param  array<string, true>  $clearableDimensions
     */
    private function __construct(
        private readonly array $granted,
        private readonly array $settableDimensions,
        private readonly array $clearableDimensions,
        public readonly string $contextSignature,
    ) {
    }

    /** The zero point: nothing granted. Every resolution starts here. */
    public static function deniedAll(string $contextSignature = 'denied'): self
    {
        return new self([], [], [], $contextSignature);
    }

    /**
     * A set carrying exactly the listed grants.
     *
     * @param  list<LocationDnaCapability>  $capabilities
     * @param  list<Dimension>  $settable
     * @param  list<Dimension>  $clearable
     */
    public static function granting(
        array $capabilities,
        array $settable = [],
        array $clearable = [],
        string $contextSignature = 'granted',
    ): self {
        $granted = [];

        foreach ($capabilities as $capability) {
            $granted[$capability->value] = true;
        }

        $settableMap = [];

        foreach ($settable as $dimension) {
            $settableMap[$dimension->value] = true;
        }

        $clearableMap = [];

        foreach ($clearable as $dimension) {
            $clearableMap[$dimension->value] = true;
        }

        ksort($granted);
        ksort($settableMap);
        ksort($clearableMap);

        return new self($granted, $settableMap, $clearableMap, $contextSignature);
    }

    /** Exact-match capability check. No implication, no inference. */
    public function allows(LocationDnaCapability $capability): bool
    {
        return array_key_exists($capability->value, $this->granted);
    }

    public function denies(LocationDnaCapability $capability): bool
    {
        return ! $this->allows($capability);
    }

    /** True only when the document is editable AND this dimension is affirmatively settable. */
    public function maySet(Dimension $dimension): bool
    {
        return $this->allows(LocationDnaCapability::EditDocument)
            && array_key_exists($dimension->value, $this->settableDimensions);
    }

    /** True only when the document is editable AND this dimension is affirmatively clearable. */
    public function mayClear(Dimension $dimension): bool
    {
        return $this->allows(LocationDnaCapability::EditDocument)
            && array_key_exists($dimension->value, $this->clearableDimensions);
    }

    /** True when nothing at all is granted. */
    public function isFullyDenied(): bool
    {
        return $this->granted === [] && $this->settableDimensions === [] && $this->clearableDimensions === [];
    }

    /** @return list<string> granted capability values, deterministically ordered */
    public function grantedCapabilities(): array
    {
        return array_keys($this->granted);
    }

    /** @return list<string> */
    public function settableDimensionKeys(): array
    {
        return array_keys($this->settableDimensions);
    }

    /** @return list<string> */
    public function clearableDimensionKeys(): array
    {
        return array_keys($this->clearableDimensions);
    }
}
