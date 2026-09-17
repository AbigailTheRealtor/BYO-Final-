<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * The write boundary for customer-selected preference reasons.
 *
 * AN INTERSECTION, NEVER A DENY-LIST — the same rule, for the same reason, as
 * SmartTagSelectionPolicy, CompatibilityPreferencePolicy and
 * LandlordScreeningPolicy. A requested value survives only by being an active
 * catalog key, offered for the state the customer chose, applicable to the
 * listing's context, and — for a tag-backed reason — backed by a tag the SEEKER
 * surface still accepts. Everything else is rejected with a reason. Free text is
 * not a key, so no request can add to the vocabulary.
 *
 * WHY A WRITE BOUNDARY FOR SOMETHING WITH NO WRITE PATH YET. Phase 1 is inert,
 * but the shape of the request is already decided: a list of chip keys arriving
 * from a browser. Building the gate now means Phase 2 adds a caller rather than
 * a policy, which is the difference between a boundary and an afterthought.
 *
 * TAG-BACKED REASONS ARE RE-VALIDATED THROUGH SmartTagSelectionPolicy against
 * SURFACE_SEEKER rather than being trusted from the catalog. The catalog already
 * refuses a non-seeker-selectable tag at config-validation time; asking the tag
 * policy again at write time is what makes a taxonomy change take effect on the
 * next request instead of on the next deploy of this file. Two checks of one
 * rule, both reading the one taxonomy — never two rules.
 *
 * Pure and container-free.
 */
final class ListingPreferenceReasonPolicy
{
    /**
     * @param array<int, mixed> $requested raw chip values from a request
     */
    public static function project(
        array $requested,
        ListingPreferenceState $state,
        ?SmartTagContext $context = null,
    ): ListingPreferenceReasonSelectionResult {
        $accepted = [];
        $rejected = [];

        foreach ($requested as $value) {
            if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $value)) {
                $rejected[is_scalar($value) ? (string) $value : gettype($value)]
                    = ListingPreferenceReasonSelectionResult::REASON_NOT_A_KEY;
                continue;
            }

            if (in_array($value, $accepted, true)) {
                continue;
            }

            $reason = self::rejectionReason($value, $state, $context);

            if ($reason === null) {
                $accepted[] = $value;
            } else {
                $rejected[$value] = $reason;
            }
        }

        return new ListingPreferenceReasonSelectionResult($accepted, $rejected);
    }

    private static function rejectionReason(
        string $key,
        ListingPreferenceState $state,
        ?SmartTagContext $context,
    ): ?string {
        $reason = ListingPreferenceReasonCatalog::get($key);

        if ($reason === null) {
            return ListingPreferenceReasonSelectionResult::REASON_UNKNOWN_KEY;
        }

        if (! $reason->isActive()) {
            return ListingPreferenceReasonSelectionResult::REASON_RETIRED;
        }

        if (! $reason->appliesToState($state)) {
            return ListingPreferenceReasonSelectionResult::REASON_NOT_FOR_STATE;
        }

        if ($context !== null && ! $reason->appliesToContext($context)) {
            return ListingPreferenceReasonSelectionResult::REASON_NOT_APPLICABLE;
        }

        if ($reason->dimension->requiresSmartTag()) {
            $tagKey     = $reason->smartTagKey;
            $definition = $tagKey === null ? null : SmartTagTaxonomy::get($tagKey);

            if ($definition === null) {
                return ListingPreferenceReasonSelectionResult::REASON_TAG_NOT_SELECTABLE;
            }

            if ($context !== null) {
                // Ask the tag's own write boundary, on the seeker surface.
                $tagResult = SmartTagSelectionPolicy::project(
                    [$tagKey],
                    $context,
                    SmartTagTaxonomy::SURFACE_SEEKER,
                );

                if ($tagResult->accepted === []) {
                    return ListingPreferenceReasonSelectionResult::REASON_TAG_NOT_SELECTABLE;
                }
            } elseif (! $definition->isSeekerSelectable()) {
                // WITHOUT A CONTEXT, the applicability half of the question
                // cannot be asked — but the half that matters can.
                //
                // SmartTagSelectionPolicy refuses a null context outright, which
                // is right for an OWNER: a listing always has a property type,
                // so no context means something went wrong. A seeker is
                // different — a customer can Pass from a card whose property
                // type was never resolved — and refusing every tag chip there
                // would be a correctness nicety costing a real capability.
                //
                // isSeekerSelectable() is context-independent and is the gate
                // that carries the Fair Housing exclusions (accessible_features,
                // playground) and the pending-review hold. So the safety
                // property is identical on both branches; only applicability is
                // relaxed, and only where it is unanswerable. EVERY Fair Housing
                // and compliance restriction still applies on this branch.
                //
                // REVIEWED AND APPROVED FOR THE INERT FOUNDATION, WITH AN
                // OBLIGATION ON PHASE 2: resolve the real listing/property
                // context wherever it can be resolved, before presenting chips
                // and before accepting them. This branch is the floor for the
                // genuinely unresolvable case — never the convenient default,
                // and never a reason to skip resolving a context a caller could
                // have obtained.
                return ListingPreferenceReasonSelectionResult::REASON_TAG_NOT_SELECTABLE;
            }
        }

        return null;
    }

    /**
     * The canonical Smart Tag keys behind a set of accepted reasons.
     *
     * The bridge a future learner crosses from "what the customer said" to
     * "which property characteristic that is", without the learner needing to
     * know how reasons are shaped.
     *
     * @param  list<string> $acceptedReasonKeys
     * @return list<string>
     */
    public static function smartTagKeysFor(array $acceptedReasonKeys): array
    {
        $keys = [];

        foreach ($acceptedReasonKeys as $key) {
            $reason = ListingPreferenceReasonCatalog::get($key);

            if ($reason !== null && $reason->isOfferable() && $reason->smartTagKey !== null) {
                $keys[$reason->smartTagKey] = true;
            }
        }

        return array_keys($keys);
    }
}
