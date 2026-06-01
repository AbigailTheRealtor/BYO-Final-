<?php

namespace App\Services\Dna\Compatibility;

/**
 * PropertyCompatibilityNarrativeService — PROP_NARRATIVE_V1 Deterministic Template Service
 *
 * Accepts the dimension match map produced by CompatibilityEngine::compute() and returns:
 *   - 'narrative' : full per-dimension plain-language text (one sentence per dimension)
 *   - 'summary'   : a single neutral summary sentence describing the overall picture
 *
 * GOVERNANCE CONSTRAINTS:
 * - Scoped exclusively to property ↔ buyer/tenant matching. Never used for BYA (client-agent) matching.
 * - Deterministic only. No randomness, no model calls, no HTTP calls, no AI.
 * - No database reads or writes.
 * - No numeric scores, no percentages, no ranking or recommendation language.
 * - No endorsement, disqualification, or prediction language.
 * - No protected-class, demographic, or externally sourced inputs.
 * - Templates stored in code only — not loaded from DB, config, files, or remote services.
 * - Unknown dimension keys fall back to GENERIC_SENTENCE; never throws.
 * - Same inputs always produce identical outputs (deterministic).
 * - Output is internal persistence only. Never surfaced in any user-facing view or API.
 * - Must not call or depend on ByaCompatibilityNarrativeService.
 */
class PropertyCompatibilityNarrativeService
{
    private const NARRATIVE_VERSION = 'PROP_NARRATIVE_V1';

    /**
     * Neutral, factual per-dimension sentence templates keyed on dimension name and result state.
     * All text is governance-compliant: factual observations only, no rankings, recommendations,
     * predictions, endorsements, disqualifications, or protected-class references.
     */
    private const DIMENSION_SENTENCES = [
        'property_type_alignment' => [
            'aligned'     => 'Property type: the supply and demand listings record matching property type values.',
            'conflicting' => 'Property type: the supply listing records a different property type than the preference stated on the demand listing.',
            'unresolved'  => 'Property type: a property type signal was absent on one or both sides; no property type comparison was made.',
        ],
        'financing_alignment' => [
            'aligned'     => 'Financing terms: the financing options available on the supply listing correspond to the financing interest expressed on the demand listing.',
            'conflicting' => 'Financing terms: the demand listing expresses interest in a financing type that the supply listing does not offer.',
            'unresolved'  => 'Financing terms: a financing preference or availability signal was absent on one or both sides; no financing comparison was made.',
        ],
        'lease_structure_alignment' => [
            'aligned'     => 'Lease structure: the lease structure available on the supply listing corresponds to the structure interest expressed on the demand listing.',
            'conflicting' => 'Lease structure: the demand listing expresses interest in a lease structure that the supply listing does not offer.',
            'unresolved'  => 'Lease structure: a lease structure preference or availability signal was absent on one or both sides; no lease structure comparison was made.',
        ],
        'occupancy_alignment' => [
            'aligned'     => 'Occupancy status: the occupancy status on the supply listing matches the occupancy preference on the demand listing.',
            'conflicting' => 'Occupancy status: the occupancy status on the supply listing does not match the occupancy preference on the demand listing.',
            'unresolved'  => 'Occupancy status: the demand-side occupancy preference signal is not emitted by the current generator architecture; no occupancy comparison was made.',
        ],
        'pet_policy_alignment' => [
            'aligned'     => 'Pet policy: the supply listing indicates pets are permitted, and the demand listing records that the occupant has pets.',
            'conflicting' => 'Pet policy: the demand listing records that the occupant has pets, but the supply listing does not record a pet-permitted signal.',
            'unresolved'  => 'Pet policy: the demand listing does not record a pet signal; no pet policy comparison was made.',
        ],
        'smoking_policy_alignment' => [
            'aligned'     => 'Smoking/restrictions policy: both the supply and demand listings record that property restrictions are specified.',
            'conflicting' => 'Smoking/restrictions policy: the supply and demand listings have differing signals regarding whether restrictions are specified.',
            'unresolved'  => 'Smoking/restrictions policy: a restrictions-specified signal was absent on one or both sides; no smoking policy comparison was made.',
        ],
        'parking_alignment' => [
            'aligned'     => 'Parking: the parking facilities on the supply listing satisfy the parking requirements recorded on the demand listing.',
            'conflicting' => 'Parking: the demand listing records a parking requirement that the supply listing does not indicate is available.',
            'unresolved'  => 'Parking: the demand listing does not record a parking requirement; no parking comparison was made.',
        ],
        'furnishing_alignment' => [
            'aligned'     => 'Furnishing terms: the furnishing terms on the supply listing correspond to the furnishing preference on the demand listing.',
            'conflicting' => 'Furnishing terms: the furnishing terms on the supply listing do not correspond to the furnishing preference on the demand listing.',
            'unresolved'  => 'Furnishing terms: the demand-side furnishing preference signal is not emitted by the current generator architecture; no furnishing comparison was made.',
        ],
        'timeline_alignment' => [
            'aligned'     => 'Move-in timeline: the move-in availability on the supply listing corresponds to the timeline preference on the demand listing.',
            'conflicting' => 'Move-in timeline: the move-in availability on the supply listing does not correspond to the timeline preference on the demand listing.',
            'unresolved'  => 'Move-in timeline: the demand-side timeline preference signal is not emitted by the current generator architecture; no timeline comparison was made.',
        ],
        'hoa_alignment' => [
            'aligned'     => 'HOA/governance: the HOA status on the supply listing corresponds to the HOA preference on the demand listing.',
            'conflicting' => 'HOA/governance: the HOA status on the supply listing does not correspond to the HOA preference on the demand listing.',
            'unresolved'  => 'HOA/governance: the demand-side HOA preference signal is not emitted by the current generator architecture; no HOA comparison was made.',
        ],
        'commercial_alignment' => [
            'aligned'     => 'Commercial use: the commercial use designation on the supply listing is consistent with the property type preference on the demand listing.',
            'conflicting' => 'Commercial use: the supply listing is designated for commercial use, and the demand listing records a preference that does not include commercial property types.',
            'unresolved'  => 'Commercial use: a commercial use or property type preference signal was absent on one or both sides; no commercial alignment comparison was made.',
        ],
        'amenity_alignment' => [
            'aligned'     => 'Amenities: the amenities available on the supply listing satisfy the amenity requirements recorded on the demand listing.',
            'conflicting' => 'Amenities: the demand listing records an amenity requirement that the supply listing does not indicate is available.',
            'unresolved'  => 'Amenities: the demand listing does not record an amenity requirement; no amenity comparison was made.',
        ],
        'budget_alignment' => [
            'aligned'     => 'Budget: the supply listing price signals fall within the budget range recorded on the demand listing.',
            'conflicting' => 'Budget: the supply listing price signals fall outside the budget range recorded on the demand listing.',
            'unresolved'  => 'Budget: the supply-side price signal is not emitted by the current generator architecture; no budget comparison was made.',
        ],
        'lease_term_alignment' => [
            'aligned'     => 'Lease term length: the lease length available on the supply listing corresponds to the term preference on the demand listing.',
            'conflicting' => 'Lease term length: the lease length available on the supply listing does not correspond to the term preference on the demand listing.',
            'unresolved'  => 'Lease term length: the demand-side lease term preference signal is not emitted by the current generator architecture; no lease term comparison was made.',
        ],
    ];

    /**
     * Generic fallback used when a dimension key is not found in DIMENSION_SENTENCES.
     */
    private const GENERIC_SENTENCE =
        'No comparison is available for this dimension. One or both sides did not provide a signal.';

    /**
     * Approved summary templates keyed on dominant pattern.
     */
    private const SUMMARY_TEMPLATES = [
        'all_aligned'    => 'Across the eligible dimensions where signals were present on both sides, all resolved comparisons were aligned.',
        'mostly_aligned' => 'Across the eligible dimensions where signals were present on both sides, more aligned comparisons were found than conflicting ones.',
        'mixed'          => 'Across the eligible dimensions where signals were present on both sides, a mix of aligned and conflicting comparisons was found.',
        'mostly_conflict'=> 'Across the eligible dimensions where signals were present on both sides, more conflicting comparisons were found than aligned ones.',
        'all_conflict'   => 'Across the eligible dimensions where signals were present on both sides, all resolved comparisons identified a conflict.',
        'all_unresolved' => 'No dimension comparisons could be resolved; signals were absent on one or both sides for all eligible dimensions.',
        'no_dimensions'  => 'No dimensions were included in this comparison. No property compatibility summary is available.',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a PROP_NARRATIVE_V1 payload from the dimension match map produced by
     * CompatibilityEngine::compute().
     *
     * @param  array  $dimensionMatchMap  Keyed map of dimension name → 'aligned'|'conflicting'|'unresolved'
     * @return array{narrative: string, summary: string, narrative_version: string}
     */
    public function generate(array $dimensionMatchMap): array
    {
        try {
            if (empty($dimensionMatchMap)) {
                return [
                    'narrative_version' => self::NARRATIVE_VERSION,
                    'narrative'         => '',
                    'summary'           => self::SUMMARY_TEMPLATES['no_dimensions'],
                ];
            }

            $sentences = [];
            $alignedCount    = 0;
            $conflictCount   = 0;
            $unresolvedCount = 0;

            foreach ($dimensionMatchMap as $dimension => $result) {
                $dimension = (string) $dimension;
                $result    = (string) ($result ?? 'unresolved');

                $sentence = self::DIMENSION_SENTENCES[$dimension][$result]
                    ?? self::GENERIC_SENTENCE;

                $sentences[] = $sentence;

                if ($result === 'aligned') {
                    $alignedCount++;
                } elseif ($result === 'conflicting') {
                    $conflictCount++;
                } else {
                    $unresolvedCount++;
                }
            }

            $narrative = implode(' ', $sentences);
            $summary   = $this->buildSummary($alignedCount, $conflictCount, $unresolvedCount);

            return [
                'narrative_version' => self::NARRATIVE_VERSION,
                'narrative'         => $narrative,
                'summary'           => $summary,
            ];
        } catch (\Throwable $e) {
            return [
                'narrative_version' => self::NARRATIVE_VERSION,
                'narrative'         => '',
                'summary'           => self::SUMMARY_TEMPLATES['no_dimensions'],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildSummary(int $aligned, int $conflict, int $unresolved): string
    {
        $resolved = $aligned + $conflict;

        if ($resolved === 0) {
            return self::SUMMARY_TEMPLATES['all_unresolved'];
        }
        if ($conflict === 0) {
            return self::SUMMARY_TEMPLATES['all_aligned'];
        }
        if ($aligned === 0) {
            return self::SUMMARY_TEMPLATES['all_conflict'];
        }
        if ($aligned > $conflict) {
            return self::SUMMARY_TEMPLATES['mostly_aligned'];
        }
        if ($conflict > $aligned) {
            return self::SUMMARY_TEMPLATES['mostly_conflict'];
        }
        return self::SUMMARY_TEMPLATES['mixed'];
    }
}
