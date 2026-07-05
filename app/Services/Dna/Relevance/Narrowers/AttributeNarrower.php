<?php

namespace App\Services\Dna\Relevance\Narrowers;

use App\Services\Dna\Relevance\CandidateNarrower;
use App\Services\Dna\Relevance\NarrowingContext;

/**
 * AttributeNarrower — Matching V2 consumption slice 2B — OPTIONAL ("where safe").
 *
 * Property-type narrowing ONLY (OD-6): drops a candidate property listing whose
 * property_type is categorically incompatible with the subject seeker's declared
 * property_types. Runs only when hard_filters_enabled is on, and only for
 * property-side candidates (a no-op when candidates are demand profiles).
 *
 * Price / bed / bath hard pre-filters are deliberately NOT done here — their
 * null-tolerance and range semantics (e.g. tenant monthly budget vs sale price)
 * belong to the §F6 scorer. Over-restricting on them silently hides valid matches.
 *
 * "Where safe" = fail-open: unknown property_type on either side → KEEP.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §4.4
 */
class AttributeNarrower implements CandidateNarrower
{
    /**
     * Slug/casing → canonical property type. Mirrors the normalization applied by
     * BuyerOfferListingCriteriaLoader so subject (Bridge-cased) and candidate (raw
     * slug) values compare in the same space.
     */
    private const CANONICAL = [
        'residential'          => 'residential',
        'income'               => 'income',
        'commercial'           => 'commercial sale',
        'commercial sale'      => 'commercial sale',
        'commercial lease'     => 'commercial lease',
        'business'             => 'business opportunity',
        'business opportunity' => 'business opportunity',
        'land'                 => 'vacant land',
        'vacant land'          => 'vacant land',
    ];

    public function narrow(array $tuples, NarrowingContext $context): array
    {
        // Property-type is meaningful only for property-side candidates.
        if ($context->counterpartSide !== 'property') {
            return $tuples;
        }

        $criteria = $context->subjectCriteria;
        if ($criteria === null || empty($criteria->propertyTypes)) {
            return $tuples; // no declared property types → nothing to narrow
        }

        $wanted = [];
        foreach ($criteria->propertyTypes as $type) {
            $wanted[$this->canonical($type)] = true;
        }

        return array_values(array_filter($tuples, function (array $tuple) use ($context, $wanted) {
            $profile = $context->profileFor($tuple);
            if ($profile === null || $profile->propertyType === null) {
                return true; // fail-open: unknown candidate type
            }
            return isset($wanted[$this->canonical($profile->propertyType)]);
        }));
    }

    private function canonical(string $type): string
    {
        $key = strtolower(trim(str_replace('_', ' ', $type)));
        return self::CANONICAL[$key] ?? $key;
    }
}
