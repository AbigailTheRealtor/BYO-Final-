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
 * LISTING SIDE: FACTS BUILT BEFORE SCORING. Present and known-absent keys come from
 * {@see ListingSmartTagIndex} — resolved `smart_tag_assignments` plus governed
 * checkability. Never evidence, remarks, descriptions or free text,
 * and nothing is derived here.
 *
 * UNKNOWN IS NEITHER A MATCH NOR A MISS. A selected tag the listing's data could not
 * check ({@see BridgeSmartTagCheckability}) is left out of the comparison entirely;
 * only matched and known-absent tags are checkable.
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
     * @param list<string>|null $listingPresent  the listing's PRESENT keys among the selection; null = no facts (all unknown)
     * @param list<string>      $listingKnownAbsent the selection's keys the listing's data checked and did not find
     * @return SeekerSmartTagMatch|null          null when the seeker selected nothing
     */
    public static function evaluate(array $selectedKeys, ?array $listingPresent, array $listingKnownAbsent = []): ?SeekerSmartTagMatch
    {
        if ($selectedKeys === []) {
            return null;
        }

        $present = $listingPresent ?? [];
        $absent = $listingPresent === null ? [] : $listingKnownAbsent;

        $matched = array_values(array_filter($selectedKeys, static fn (string $key): bool => in_array($key, $present, true)));
        $knownAbsent = array_values(array_filter(
            $selectedKeys,
            static fn (string $key): bool => ! in_array($key, $matched, true) && in_array($key, $absent, true),
        ));

        return new SeekerSmartTagMatch(
            selectedKeys:    array_values($selectedKeys),
            matchedKeys:     $matched,
            knownAbsentKeys: $knownAbsent,
        );
    }
}
