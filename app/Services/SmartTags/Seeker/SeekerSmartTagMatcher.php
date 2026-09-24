<?php

namespace App\Services\SmartTags\Seeker;

use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * Compares a Buyer/Tenant's selected Smart Tags with one listing's resolved
 * Smart Tags. Pure: no container, no query, no clock.
 *
 * WHAT IT ANSWERS, AND WHAT IT DOES NOT. It counts how many of the seeker's picks
 * the listing has. How many POINTS that is worth is BuyerMatchScorer's decision
 * (its Amenities category), so scoring stays in one file and there is no second
 * scorer. It never filters: the picker says "Property Features You Want" and
 * "Optional … we will use them when we look for a match", which is a preference,
 * not a requirement.
 *
 * LISTING SIDE: RESOLVED ASSIGNMENTS ONLY. The listing's keys are the PRESENT rows
 * of `smart_tag_assignments` ({@see ListingSmartTagIndex}) — the one canonical
 * answer per listing × tag. Never evidence, remarks, descriptions or free text,
 * and nothing is derived here.
 *
 * UNKNOWN IS NOT A MATCH. A listing with no resolved tags earns nothing, the same
 * rule the Amenities category already applies to a pool the feed did not report.
 */
final class SeekerSmartTagMatcher
{
    /**
     * The seeker keys matching may use at all, from whatever a payload was given.
     *
     * Shape, then governance: strings in canonical form, de-duplicated, each an
     * active, seeker-selectable, non-pending taxonomy key whose name the
     * compliance guard accepts. Context applicability was already decided by
     * {@see SmartTagSeekerPreferenceReader::matchingKeysFor()}; this is the
     * second, context-free check at the scoring boundary, so a hand-built
     * payload cannot score a key the picker could never have offered.
     *
     * @param  array<int, mixed> $raw
     * @return list<string>
     */
    public static function governedKeys(array $raw): array
    {
        $out = [];

        foreach ($raw as $value) {
            if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $value) || in_array($value, $out, true)) {
                continue;
            }

            $definition = SmartTagTaxonomy::get($value);

            if ($definition !== null && $definition->isSeekerSelectable() && SmartTagComplianceGuard::keyIsClean($value)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @param list<string>      $selectedKeys    already governed ({@see governedKeys()})
     * @param list<string>|null $listingPresent  the listing's PRESENT keys among the selection; null = no resolved tags at all
     * @return SeekerSmartTagMatch|null          null when the seeker selected nothing
     */
    public static function evaluate(array $selectedKeys, ?array $listingPresent): ?SeekerSmartTagMatch
    {
        if ($selectedKeys === []) {
            return null;
        }

        $present = $listingPresent ?? [];
        $matched = array_values(array_filter(
            $selectedKeys,
            static fn (string $key): bool => in_array($key, $present, true)
        ));

        return new SeekerSmartTagMatch(
            selectedKeys:   array_values($selectedKeys),
            matchedKeys:    $matched,
            hasListingData: $listingPresent !== null,
        );
    }
}
