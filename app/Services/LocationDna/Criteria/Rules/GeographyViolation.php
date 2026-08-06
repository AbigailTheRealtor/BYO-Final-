<?php

namespace App\Services\LocationDna\Criteria\Rules;

use InvalidArgumentException;

/**
 * Phase 1b — one broken rule, placed in a tier and pointing at the selection that broke it.
 *
 * Carrying the tier is what lets a surface show a county problem next to the county picker and
 * nothing next to the ZIP picker. Carrying `offendingId` is what lets it name the specific chip to
 * highlight rather than reprinting the whole rule.
 *
 * Constructing one with an impossible shape throws, which is a DTO invariant and not a validation
 * outcome — {@see GeographySelectionValidator} still never throws for bad USER input.
 */
final class GeographyViolation
{
    public function __construct(
        public readonly GeographyRule $rule,
        public readonly GeographyTier $tier,
        public readonly ?string $offendingId,
        public readonly string $message,
    ) {
    }

    /**
     * Build a violation, defaulting the tier and message from the rule.
     *
     * `$tier` MUST be supplied for the two tier-agnostic rules — a duplicate or a malformed id can
     * occur anywhere, and a violation that could not say where would be useless to a surface.
     */
    public static function of(
        GeographyRule $rule,
        ?string $offendingId = null,
        ?GeographyTier $tier = null,
    ): self {
        $resolved = $tier ?? $rule->defaultTier();

        if ($resolved === null) {
            throw new InvalidArgumentException(
                "`{$rule->value}` is not tier-specific, so a tier must be supplied."
            );
        }

        return new self($rule, $resolved, $offendingId, $rule->describe());
    }

    /** Does this violation leave the selection merely incomplete rather than invalid? */
    public function governsCompletenessOnly(): bool
    {
        return $this->rule->governsCompletenessOnly();
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'rule'         => $this->rule->value,
            'tier'         => $this->tier->value,
            'offending_id' => $this->offendingId,
            'message'      => $this->message,
        ];
    }
}
