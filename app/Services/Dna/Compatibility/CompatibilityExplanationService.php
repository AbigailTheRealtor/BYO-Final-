<?php

namespace App\Services\Dna\Compatibility;

use App\Models\ListingCompatibilityScore;

/**
 * CompatibilityExplanationService — Phase N Deterministic Explanation Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a TRANSLATION LAYER ONLY. It converts persisted compatibility
 * dimension identifiers into neutral, plain-language explanation strings.
 *
 * This service MUST NEVER:
 *   - Change, recalculate, modify, or influence any compatibility score or metric.
 *   - Rank, sort, order, or weight explanation output by score or any other signal.
 *   - Recommend any listing, buyer, tenant, seller, landlord, or agent.
 *   - Determine, infer, or output suitability, qualification, approval, or rejection.
 *   - Predict any outcome, likelihood, or probability of any transaction event.
 *   - Perform AI reasoning, language model inference, embedding lookup, or ML logic.
 *   - Generate narrative persuasion copy, endorsements, or matchmaking language.
 *   - Read or write any scoring model, DNA profile, or database row.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *
 * All output is deterministic and reproducible from persisted compatibility data.
 * Output order is deterministic (input order preserved; no sorting by score/weight).
 * ==================================================================================
 */
class CompatibilityExplanationService
{
    /**
     * Neutral, factual explanation strings for each of the 14 approved compatibility
     * dimensions across all three result states: aligned, conflicting, unresolved.
     *
     * All strings are:
     *   - Factual and neutral — no recommendations, no predictions, no rankings.
     *   - Free of protected-class language, demographic inference, or behavioral prediction.
     *   - Deterministically assigned from the persisted dimension identifier.
     *   - Identical for every invocation given the same dimension and result state.
     */
    private const DIMENSION_EXPLANATIONS = [
        'property_type_alignment' => [
            'aligned'     => 'The property type recorded on the supply listing matches the property type preference recorded on the demand listing.',
            'conflicting' => 'The property type recorded on the supply listing does not match the property type preference recorded on the demand listing.',
            'unresolved'  => 'A property type signal was absent on one or both sides; no property type comparison was made.',
        ],

        'financing_alignment' => [
            'aligned'     => 'The financing terms available on the supply listing (seller financing or assumable loan) correspond to the financing options expressed on the demand listing.',
            'conflicting' => 'The demand listing indicates interest in a financing type (seller financing or assumable loan) that the supply listing does not offer.',
            'unresolved'  => 'A financing preference or availability signal was absent on one or both sides; no financing comparison was made.',
        ],

        'lease_structure_alignment' => [
            'aligned'     => 'The lease structure available on the supply listing (lease-option or lease-purchase) corresponds to the lease structure interest expressed on the demand listing.',
            'conflicting' => 'The demand listing indicates interest in a lease structure (lease-option or lease-purchase) that the supply listing does not offer.',
            'unresolved'  => 'A lease structure preference or availability signal was absent on one or both sides; no lease structure comparison was made.',
        ],

        'occupancy_alignment' => [
            'aligned'     => 'The occupancy status recorded on the supply listing matches the occupancy preference recorded on the demand listing.',
            'conflicting' => 'The occupancy status recorded on the supply listing does not match the occupancy preference recorded on the demand listing.',
            'unresolved'  => 'The demand-side occupancy preference signal is not emitted by the current generator architecture; no occupancy comparison was made.',
        ],

        'pet_policy_alignment' => [
            'aligned'     => 'The supply listing indicates pets are permitted, and the demand listing indicates the occupant has pets.',
            'conflicting' => 'The demand listing indicates the occupant has pets, but the supply listing does not record a pet-permitted signal.',
            'unresolved'  => 'The demand listing does not record a pet signal; no pet policy comparison was made.',
        ],

        'smoking_policy_alignment' => [
            'aligned'     => 'Both the supply listing and the demand listing record that property restrictions are specified, indicating mutual awareness of policy terms.',
            'conflicting' => 'The supply listing and demand listing have differing signals regarding whether property restrictions are specified.',
            'unresolved'  => 'A restrictions-specified signal was absent on one or both sides; no smoking policy comparison was made.',
        ],

        'parking_alignment' => [
            'aligned'     => 'The parking facilities available on the supply listing (garage or carport) satisfy the parking requirements recorded on the demand listing.',
            'conflicting' => 'The demand listing records a parking requirement (garage or carport) that the supply listing does not indicate is available.',
            'unresolved'  => 'The demand listing does not record a parking requirement; no parking comparison was made.',
        ],

        'furnishing_alignment' => [
            'aligned'     => 'The furnishing terms recorded on the supply listing correspond to the furnishing preference recorded on the demand listing.',
            'conflicting' => 'The furnishing terms recorded on the supply listing do not correspond to the furnishing preference recorded on the demand listing.',
            'unresolved'  => 'The demand-side furnishing preference signal is not emitted by the current generator architecture; no furnishing comparison was made.',
        ],

        'timeline_alignment' => [
            'aligned'     => 'The move-in availability timing recorded on the supply listing corresponds to the timeline preference recorded on the demand listing.',
            'conflicting' => 'The move-in availability timing recorded on the supply listing does not correspond to the timeline preference recorded on the demand listing.',
            'unresolved'  => 'The demand-side timeline preference signal is not emitted by the current generator architecture; no timeline comparison was made.',
        ],

        'hoa_alignment' => [
            'aligned'     => 'The HOA or community governance status recorded on the supply listing corresponds to the HOA preference recorded on the demand listing.',
            'conflicting' => 'The HOA or community governance status recorded on the supply listing does not correspond to the HOA preference recorded on the demand listing.',
            'unresolved'  => 'The demand-side HOA preference signal is not emitted by the current generator architecture; no HOA comparison was made.',
        ],

        'commercial_alignment' => [
            'aligned'     => 'The commercial use designation on the supply listing is consistent with the property type preference recorded on the demand listing.',
            'conflicting' => 'The supply listing is designated for commercial use, and the demand listing records a preference that does not include commercial property types.',
            'unresolved'  => 'A commercial use or property type preference signal was absent on one or both sides; no commercial alignment comparison was made.',
        ],

        'amenity_alignment' => [
            'aligned'     => 'The amenities available on the supply listing (such as a pool) satisfy the amenity requirements recorded on the demand listing.',
            'conflicting' => 'The demand listing records an amenity requirement (such as a pool) that the supply listing does not indicate is available.',
            'unresolved'  => 'The demand listing does not record an amenity requirement; no amenity comparison was made.',
        ],

        'budget_alignment' => [
            'aligned'     => 'The price or value signals on the supply listing are within the budget range recorded on the demand listing.',
            'conflicting' => 'The price or value signals on the supply listing fall outside the budget range recorded on the demand listing.',
            'unresolved'  => 'The supply-side price or value signal is not emitted by the current generator architecture; no budget comparison was made.',
        ],

        'lease_term_alignment' => [
            'aligned'     => 'The lease length available on the supply listing corresponds to the lease term preference recorded on the demand listing.',
            'conflicting' => 'The lease length available on the supply listing does not correspond to the lease term preference recorded on the demand listing.',
            'unresolved'  => 'The demand-side lease term preference signal is not emitted by the current generator architecture; no lease term comparison was made.',
        ],
    ];

    /**
     * Generate plain-language explanation records from a persisted compatibility score.
     *
     * Reads the `score_explanation` field of the provided score model and maps each
     * dimension identifier in the `aligned_dimensions`, `conflicting_dimensions`, and
     * `unresolved_dimensions` arrays to its corresponding neutral explanation string.
     *
     * Output structure:
     * [
     *     'aligned'     => [ ['dimension' => string, 'explanation' => string], ... ],
     *     'conflicting' => [ ['dimension' => string, 'explanation' => string], ... ],
     *     'unresolved'  => [ ['dimension' => string, 'explanation' => string], ... ],
     * ]
     *
     * Output order is deterministic: dimensions appear in the order they are stored
     * in `score_explanation`. No reordering, sorting, or weighting is applied.
     *
     * If a dimension identifier does not appear in DIMENSION_EXPLANATIONS (e.g., a
     * future dimension not yet mapped), a neutral fallback string is used and the
     * dimension is still included in output — this service never silently drops
     * dimensions present in the persisted data.
     *
     * @param  ListingCompatibilityScore $score  A persisted, cast score model instance.
     * @return array{aligned: array, conflicting: array, unresolved: array}
     */
    public function generate(ListingCompatibilityScore $score): array
    {
        $explanation = (array) ($score->score_explanation ?? []);

        $aligned     = (array) ($explanation['aligned_dimensions']     ?? []);
        $conflicting = (array) ($explanation['conflicting_dimensions'] ?? []);
        $unresolved  = (array) ($explanation['unresolved_dimensions']  ?? []);

        return [
            'aligned'     => $this->mapDimensions($aligned,     'aligned'),
            'conflicting' => $this->mapDimensions($conflicting, 'conflicting'),
            'unresolved'  => $this->mapDimensions($unresolved,  'unresolved'),
        ];
    }

    /**
     * Map an array of dimension identifier strings to explanation records.
     *
     * Each record contains the dimension identifier and its corresponding
     * neutral explanation string for the given result state.
     *
     * If a dimension identifier is not found in DIMENSION_EXPLANATIONS, a
     * neutral fallback string is used so that no dimension is silently dropped.
     *
     * @param  string[] $dimensions  Dimension identifiers from persisted score data.
     * @param  string   $state       Result state: 'aligned', 'conflicting', or 'unresolved'.
     * @return array<int, array{dimension: string, explanation: string}>
     */
    private function mapDimensions(array $dimensions, string $state): array
    {
        $records = [];

        foreach ($dimensions as $dimension) {
            $dimension = (string) $dimension;

            if (isset(self::DIMENSION_EXPLANATIONS[$dimension][$state])) {
                $explanation = self::DIMENSION_EXPLANATIONS[$dimension][$state];
            } else {
                $explanation = 'No explanation is mapped for this dimension and result state.';
            }

            $records[] = [
                'dimension'   => $dimension,
                'explanation' => $explanation,
            ];
        }

        return $records;
    }
}
