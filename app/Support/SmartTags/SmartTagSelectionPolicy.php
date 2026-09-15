<?php

namespace App\Support\SmartTags;

/**
 * The write boundary for manually selected Smart Tags — owner selections now,
 * Buyer/Tenant preferences later.
 *
 * AN INTERSECTION, NEVER A DENY-LIST. A requested value survives only by being
 * an active canonical key, applicable to the listing's context, selectable on
 * this surface, not pending compliance review, and not already answered by an
 * authoritative Property Details field. Everything else is rejected with a
 * reason. No request can add to the taxonomy: free text is not a key.
 *
 * The same idea as CompatibilityPreferencePolicy and LandlordScreeningPolicy,
 * for the same reason: a public Livewire property is client-writable, so the
 * gate has to be at the write.
 *
 * Pure and container-free. The caller supplies the context (from the STORED
 * property type, never a submitted one) and the keys the listing's own Yes/No
 * fields already answer.
 */
final class SmartTagSelectionPolicy
{
    /**
     * @param array<int, mixed> $requested    raw values from a request
     * @param string            $surface      SmartTagTaxonomy::SURFACE_OWNER | SURFACE_SEEKER
     * @param string[]          $answeredKeys keys an authoritative structured field already answers
     */
    public static function project(
        array $requested,
        ?SmartTagContext $context,
        string $surface,
        array $answeredKeys = [],
    ): SmartTagSelectionResult {
        $accepted = [];
        $rejected = [];

        foreach ($requested as $value) {
            if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $value)) {
                $rejected[is_scalar($value) ? (string) $value : gettype($value)] = SmartTagSelectionResult::REASON_NOT_A_KEY;
                continue;
            }

            if (in_array($value, $accepted, true)) {
                continue;
            }

            $reason = self::rejectionReason($value, $context, $surface, $answeredKeys);

            if ($reason === null) {
                $accepted[] = $value;
            } else {
                $rejected[$value] = $reason;
            }
        }

        return new SmartTagSelectionResult($accepted, $rejected);
    }

    /**
     * @param string[] $answeredKeys
     */
    private static function rejectionReason(string $key, ?SmartTagContext $context, string $surface, array $answeredKeys): ?string
    {
        $definition = SmartTagTaxonomy::get($key);

        if ($definition === null) {
            // Belt and braces: an undeclared key that also reads as a prohibited
            // concept is reported as such, so the refusal says why.
            return SmartTagComplianceGuard::keyIsClean($key)
                ? SmartTagSelectionResult::REASON_UNKNOWN_KEY
                : SmartTagSelectionResult::REASON_PROHIBITED;
        }

        if (! $definition->isActive()) {
            return SmartTagSelectionResult::REASON_RETIRED;
        }

        if ($context === null) {
            return SmartTagSelectionResult::REASON_NO_CONTEXT;
        }

        if (! $definition->appliesTo($context)) {
            return SmartTagSelectionResult::REASON_NOT_APPLICABLE;
        }

        if ($definition->isPendingReview()) {
            return SmartTagSelectionResult::REASON_PENDING_REVIEW;
        }

        $selectable = match ($surface) {
            SmartTagTaxonomy::SURFACE_OWNER  => $definition->isOwnerSelectable(),
            SmartTagTaxonomy::SURFACE_SEEKER => $definition->isSeekerSelectable(),
            default                          => false,
        };

        if (! $selectable) {
            return SmartTagSelectionResult::REASON_NOT_SELECTABLE;
        }

        if ($surface === SmartTagTaxonomy::SURFACE_OWNER && in_array($key, $answeredKeys, true)) {
            return SmartTagSelectionResult::REASON_ANSWERED_BY_PROPERTY_DETAILS;
        }

        return null;
    }
}
