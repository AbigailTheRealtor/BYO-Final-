<?php

namespace App\Services\LocationDna\Contract;

/**
 * DimensionOperation — the v1 mutation vocabulary. Exactly two operations.
 *
 * G1c contract core. INERT. Approved by D-G1-2 (option 2-A).
 *
 * §6.2 and D-G1-2's approved clarifications:
 *
 *   set    — replace the entire dimension. Marks it present and authored.
 *            Value REQUIRED, and must not be the canonical empty.
 *   clear  — record intentional clearing. Marks it present-but-empty (§5.4 S4).
 *            Value FORBIDDEN.
 *
 * NOT operations, each for a stated reason:
 *
 *   preserve  — represented by the ABSENCE of a command for that dimension. D-G1-2
 *               approved that preserve is not a third mutation operation; §6.2 rejects a
 *               synonym operation on the L1 duplicate-source-of-truth ground.
 *   replace   — a synonym for `set` (§6.2).
 *   append / remove / reorder — require element identity the array dimensions lack (§6.2).
 *   merge     — undefined for arrays of unlabelled geometry, and an undefined merge is how
 *               data gets silently lost (§6.2).
 *   migrate-from-legacy — D-G1-2 approved this as an internal provenance/hydration
 *               concern, NOT a user mutation operation.
 *
 * "Absent from payload" is likewise never an instruction (§6.2, §5.3).
 */
enum DimensionOperation: string
{
    case Set   = 'set';
    case Clear = 'clear';

    public function requiresValue(): bool
    {
        return $this === self::Set;
    }

    public function forbidsValue(): bool
    {
        return $this === self::Clear;
    }

    /**
     * Strict parse of an operation name.
     *
     * Rejects every unsupported name — including the deliberately excluded `preserve`,
     * `replace`, `merge`, `append`, `remove`, `reorder` and `omit` — with a domain error
     * rather than a null, so an unsupported operation cannot be silently skipped.
     *
     * @throws LocationDnaContractException
     */
    public static function fromName(string $name): self
    {
        $parsed = self::tryFrom(strtolower(trim($name)));

        if ($parsed === null) {
            throw LocationDnaContractException::invalidOperation(
                "Unsupported operation `{$name}`. The v1 vocabulary is exactly: set, clear. "
                .'Preserve is expressed by sending no command for the dimension.',
            );
        }

        return $parsed;
    }
}
