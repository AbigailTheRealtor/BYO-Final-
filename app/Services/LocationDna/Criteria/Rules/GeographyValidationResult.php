<?php

namespace App\Services\LocationDna\Criteria\Rules;

/**
 * Phase 1b — the outcome of validating a selection.
 *
 * THE VALIDITY / COMPLETENESS SPLIT (D5)
 * --------------------------------------
 * "Required" means two different things in a live cascade, and collapsing them produces a form that
 * shouts at a user for not having done something they have not had the chance to do yet:
 *
 *   isValid()     nothing selected is ILLEGAL. No unknown ids, no orphans, no duplicates, no
 *                 malformed values. A state-only selection is VALID. So is an empty one.
 *   isComplete()  isValid(), AND a state is chosen, AND at least one county is chosen. This — and
 *                 only this — is what a submit button may gate on.
 *
 * The consequence to keep in mind: `isValid()` true does NOT mean "safe to submit". Every caller
 * that gates submission must ask `isComplete()`.
 *
 * NEVER THROWN, ALWAYS RETURNED (D4). Every violation is accumulated; validation does not stop at
 * the first problem, because a surface that reveals errors one at a time makes the user pay a full
 * round trip per mistake.
 */
final class GeographyValidationResult
{
    /** @param list<GeographyViolation> $violations */
    private function __construct(public readonly array $violations)
    {
    }

    /** @param list<GeographyViolation> $violations */
    public static function of(array $violations): self
    {
        return new self(array_values($violations));
    }

    public static function clean(): self
    {
        return new self([]);
    }

    /**
     * Is the selection free of anything actively WRONG?
     *
     * Completeness-only violations are excluded by design: not having chosen a county yet is not an
     * error, it is an unfinished cascade.
     */
    public function isValid(): bool
    {
        foreach ($this->violations as $violation) {
            if (! $violation->governsCompletenessOnly()) {
                return false;
            }
        }

        return true;
    }

    /** Valid AND finished. The only predicate a submit gate may use. */
    public function isComplete(): bool
    {
        return $this->violations === [];
    }

    /** @return list<GeographyViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return list<GeographyViolation> */
    public function violationsFor(GeographyTier $tier): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (GeographyViolation $v): bool => $v->tier === $tier
        ));
    }

    public function hasRule(GeographyRule $rule): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->rule === $rule) {
                return true;
            }
        }

        return false;
    }

    /** @return list<GeographyRule> every distinct rule broken, in first-seen order */
    public function rules(): array
    {
        $seen = [];

        foreach ($this->violations as $violation) {
            $seen[$violation->rule->value] = $violation->rule;
        }

        return array_values($seen);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid'      => $this->isValid(),
            'complete'   => $this->isComplete(),
            'violations' => array_map(
                static fn (GeographyViolation $v): array => $v->toArray(),
                $this->violations
            ),
        ];
    }
}
