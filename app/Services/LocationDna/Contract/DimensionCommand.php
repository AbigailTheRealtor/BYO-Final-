<?php

namespace App\Services\LocationDna\Contract;

/**
 * DimensionCommand — one immutable, server-built mutation intent for exactly one dimension.
 *
 * G1c contract core. INERT. Approved by D-G1-2 (option 2-A).
 *
 * WHY A COMMAND OBJECT
 * --------------------
 * D-G1-2 approved that intent is carried in a server-built command object — not in the raw
 * payload, and not in client-supplied dirty-field metadata. A dirty list would be client
 * authority over server semantics, contradicting settled decisions 3 and 4 and L6.
 *
 * This class therefore:
 *   - does not inspect an HTTP request,
 *   - does not read Livewire dirty state,
 *   - writes nothing.
 *
 * WHAT IT MAKES IMPOSSIBLE
 * ------------------------
 * The named constructors are the only way in, so the ambiguities G1a proved cannot be
 * expressed:
 *   - clear-through-omission — omission produces NO command; there is no constructor for it
 *   - clear-through-null     — set(null) throws AuthoredNull
 *   - clear-through-empty-string — set('') on a Text dimension throws, because '' is that
 *     dimension's canonical empty and D-G1-2 approved that an empty string may not silently
 *     stand in for clear
 *   - preserve-disguised-as-mutation — there is no preserve case in the enum
 *   - unsupported operation names — {@see DimensionOperation::fromName()} throws
 *
 * One command names one dimension (§6.1 "One envelope, one dimension").
 */
final class DimensionCommand
{
    private function __construct(
        public readonly Dimension $dimension,
        public readonly DimensionOperation $operation,
        private readonly mixed $value,
    ) {
    }

    /**
     * Set a dimension to an authored value.
     *
     * @throws LocationDnaContractException when $value is null, or is the canonical empty
     *                                      (§6.2: `set` with a canonically empty value is
     *                                      rejected, not silently normalised — use clear())
     */
    public static function set(Dimension $dimension, mixed $value): self
    {
        if ($value === null) {
            throw LocationDnaContractException::authoredNull($dimension->value);
        }

        if ($dimension->isCanonicalEmpty($value)) {
            throw LocationDnaContractException::invalidDimensionValue(
                $dimension->value,
                'set with the canonical empty value is rejected; use a clear command to withdraw the dimension.',
            );
        }

        return new self($dimension, DimensionOperation::Set, $value);
    }

    /** Record an intentional clear. The dimension becomes present-but-empty (§5.4 S4). */
    public static function clear(Dimension $dimension): self
    {
        return new self($dimension, DimensionOperation::Clear, null);
    }

    /**
     * Build from an operation name and an optional value.
     *
     * The strict entry point for an adapter that has a name in hand. Enforces §6.1's
     * conditional-value rule: required for set, forbidden for clear.
     *
     * @throws LocationDnaContractException
     */
    public static function fromOperationName(Dimension $dimension, string $operationName, mixed $value = null): self
    {
        $operation = DimensionOperation::fromName($operationName);

        if ($operation->forbidsValue() && $value !== null) {
            throw LocationDnaContractException::invalidOperation(
                "Operation `clear` on `{$dimension->value}` must not carry a value.",
            );
        }

        return $operation === DimensionOperation::Clear
            ? self::clear($dimension)
            : self::set($dimension, $value);
    }

    public function isSet(): bool
    {
        return $this->operation === DimensionOperation::Set;
    }

    public function isClear(): bool
    {
        return $this->operation === DimensionOperation::Clear;
    }

    /** The authored value for a set command; null for a clear. */
    public function value(): mixed
    {
        return $this->value;
    }

    /** The value this command will place in the document. */
    public function effectiveValue(): mixed
    {
        return $this->isClear() ? $this->dimension->canonicalEmpty() : $this->value;
    }
}
