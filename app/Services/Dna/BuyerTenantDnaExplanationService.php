<?php

namespace App\Services\Dna;

use App\Models\BuyerTenantDnaProfile;

/**
 * BuyerTenantDnaExplanationService — Phase O Buyer/Tenant DNA Explanation Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a TRANSLATION LAYER ONLY. It converts persisted Buyer/Tenant DNA
 * profile arrays (lifestyle_tags, deal_breaker_flags) into structured explanation
 * records using static constant maps.
 *
 * This service MUST NEVER:
 *   - Change, recalculate, modify, or influence any DNA profile, score, or metric.
 *   - Rank, sort, order, or weight explanation output by score or any other signal.
 *   - Recommend any listing, buyer, tenant, seller, landlord, or agent.
 *   - Determine, infer, or output suitability, qualification, approval, or rejection.
 *   - Predict any outcome, likelihood, or probability of any transaction event.
 *   - Perform AI reasoning, language model inference, embedding lookup, or ML logic.
 *   - Generate narrative persuasion copy, endorsements, or matchmaking language.
 *   - Normalize, reformat, label-convert, or transform any persisted value string.
 *   - Read or write any scoring model, compatibility record, or database row.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *
 * All persisted values (tag strings, flag keys, source field strings, value strings)
 * are passed through verbatim — exactly as stored. No string transformation, case
 * conversion, underscore-to-space substitution, or label lookup is applied to values.
 *
 * All output is deterministic and reproducible from persisted DNA profile data.
 * Output order is deterministic: input order from the persisted array is preserved.
 * No reordering, sorting, or weighting is applied.
 * ==================================================================================
 */
class BuyerTenantDnaExplanationService
{
    /**
     * Neutral, factual explanation strings for each lifestyle tag prefix.
     *
     * For bare tags without a colon (e.g., `has-pets`), the full tag string is
     * used as the lookup key instead of a prefix.
     *
     * Each entry describes what the tag prefix dimension slot represents —
     * not what the specific persisted value implies about the buyer or tenant.
     *
     * All strings are:
     *   - Factual and neutral — no endorsements, no forecasts, no rankings.
     *   - Free of protected-class language, demographic inference, or behavioral inference.
     *   - Deterministically assigned from the tag prefix extracted from persisted data.
     */
    private const LIFESTYLE_TAG_PREFIX_EXPLANATIONS = [
        'prefers-type'      => 'Identifies a property type preference recorded on the demand listing.',
        'prefers-condition' => 'Identifies a property condition preference recorded on the demand listing.',
        'has-pets'          => 'Indicates that the demand listing records the presence of pets.',
        'seeks'             => 'Identifies a community or living arrangement the demand listing indicates is sought.',
        'requires'          => 'Identifies an amenity or facility the demand listing records as required.',
        'open-to'           => 'Identifies a transaction structure or financing option the demand listing records as acceptable.',
        'financial'         => 'Identifies a financial status or qualification term recorded on the demand listing.',
        'preference'        => 'Identifies a policy or community preference term recorded on the demand listing.',
    ];

    /**
     * Neutral, factual explanation strings for each deal-breaker flag key.
     *
     * Each entry describes what the flag dimension slot represents —
     * not what the specific persisted value implies about the buyer or tenant.
     *
     * All strings are:
     *   - Factual and neutral — no endorsements, no forecasts, no rankings.
     *   - Free of protected-class language, demographic inference, or behavioral inference.
     *   - Deterministically assigned from the flag key found in persisted data.
     */
    private const DEAL_BREAKER_EXPLANATIONS = [
        '55_plus_required'           => 'The demand listing records that a 55-plus community designation is required.',
        'pool_required'              => 'The demand listing records that a pool is required.',
        'garage_required'            => 'The demand listing records that a garage is required.',
        'carport_required'           => 'The demand listing records that a carport is required.',
        'minimum_bedrooms_specified' => 'The demand listing records a minimum bedroom count requirement; the persisted value is the recorded minimum.',
        'minimum_bathrooms_specified'=> 'The demand listing records a minimum bathroom count requirement; the persisted value is the recorded minimum.',
        'minimum_sqft_specified'     => 'The demand listing records a minimum square footage requirement; the persisted value is the recorded minimum.',
        'budget_ceiling_specified'   => 'The demand listing records a budget ceiling; the persisted value is the recorded budget figure.',
    ];

    /**
     * Generate structured explanation records from a persisted BuyerTenantDnaProfile.
     *
     * Reads the `lifestyle_tags` and `deal_breaker_flags` arrays from the profile
     * model and returns a structured array of explanation records.
     *
     * For lifestyle tags: the tag string is split on the first `:` to extract the
     * prefix; if no `:` is present, the full tag string is used as the lookup key.
     * The prefix (or full tag) is mapped to a neutral explanation string. The full
     * original tag string is passed through verbatim as the `tag` field.
     *
     * For deal-breaker flags: the `flag` key is mapped to a neutral explanation
     * string. The `flag`, `source_field`, and `value` fields are passed through
     * verbatim. `value` may be null for flags that record no quantity.
     *
     * Output structure:
     * [
     *     'lifestyle_tag_explanations' => [
     *         ['tag' => string, 'explanation' => string],
     *         ...
     *     ],
     *     'deal_breaker_explanations' => [
     *         ['flag' => string, 'source_field' => string, 'value' => string|null, 'explanation' => string],
     *         ...
     *     ],
     * ]
     *
     * Output order is deterministic: elements appear in the order they are stored
     * in the persisted array. No reordering, sorting, or weighting is applied.
     *
     * If a tag prefix or flag key is not found in the constant maps, a neutral
     * fallback string is used — no element present in the persisted data is silently
     * dropped.
     *
     * @param  BuyerTenantDnaProfile $profile  A persisted, cast profile model instance.
     * @return array{lifestyle_tag_explanations: array, deal_breaker_explanations: array}
     */
    public function generate(BuyerTenantDnaProfile $profile): array
    {
        $lifestyleTags    = (array) ($profile->lifestyle_tags ?? []);
        $dealBreakerFlags = (array) ($profile->deal_breaker_flags ?? []);

        return [
            'lifestyle_tag_explanations' => $this->mapLifestyleTags($lifestyleTags),
            'deal_breaker_explanations'  => $this->mapDealBreakerFlags($dealBreakerFlags),
        ];
    }

    /**
     * Map an array of lifestyle tag strings to explanation records.
     *
     * Each tag string is split on the first `:` to extract the prefix. If no `:`
     * is present (bare tags such as `has-pets`), the full tag string is used as the
     * lookup key. The full original tag string is placed in the `tag` field verbatim
     * — no normalization or transformation.
     *
     * If the prefix or bare tag is not found in LIFESTYLE_TAG_PREFIX_EXPLANATIONS,
     * a neutral fallback string is used so that no tag present in the persisted data
     * is silently dropped.
     *
     * @param  string[] $tags  Lifestyle tag strings from persisted profile data.
     * @return array<int, array{tag: string, explanation: string}>
     */
    private function mapLifestyleTags(array $tags): array
    {
        $records = [];

        foreach ($tags as $tag) {
            $tag = (string) $tag;

            $colonPos  = strpos($tag, ':');
            $lookupKey = ($colonPos !== false) ? substr($tag, 0, $colonPos) : $tag;

            if (isset(self::LIFESTYLE_TAG_PREFIX_EXPLANATIONS[$lookupKey])) {
                $explanation = self::LIFESTYLE_TAG_PREFIX_EXPLANATIONS[$lookupKey];
            } else {
                $explanation = 'No explanation is mapped for this lifestyle tag prefix.';
            }

            $records[] = [
                'tag'         => $tag,
                'explanation' => $explanation,
            ];
        }

        return $records;
    }

    /**
     * Map an array of deal-breaker flag records to explanation records.
     *
     * Each flag record contains a `flag` key, a `source_field` string, and an
     * optional `value`. The `flag` key is looked up in DEAL_BREAKER_EXPLANATIONS.
     * The `flag`, `source_field`, and `value` fields are placed in the output
     * verbatim — no normalization or transformation of any field.
     *
     * `value` is null when the persisted flag record contains no value entry (flags
     * that record only presence, not a quantity). When `value` is present in the
     * persisted record it is passed through as a string, exactly as stored.
     *
     * If the flag key is not found in DEAL_BREAKER_EXPLANATIONS, a neutral fallback
     * string is used so that no flag present in the persisted data is silently dropped.
     *
     * @param  array $flags  Deal-breaker flag records from persisted profile data.
     * @return array<int, array{flag: string, source_field: string, value: string|null, explanation: string}>
     */
    private function mapDealBreakerFlags(array $flags): array
    {
        $records = [];

        foreach ($flags as $flagRecord) {
            $flagRecord  = (array) $flagRecord;
            $flag        = (string) ($flagRecord['flag']         ?? '');
            $sourceField = (string) ($flagRecord['source_field'] ?? '');
            $value       = array_key_exists('value', $flagRecord)
                ? (($flagRecord['value'] !== null) ? (string) $flagRecord['value'] : null)
                : null;

            if (isset(self::DEAL_BREAKER_EXPLANATIONS[$flag])) {
                $explanation = self::DEAL_BREAKER_EXPLANATIONS[$flag];
            } else {
                $explanation = 'No explanation is mapped for this deal-breaker flag key.';
            }

            $records[] = [
                'flag'         => $flag,
                'source_field' => $sourceField,
                'value'        => $value,
                'explanation'  => $explanation,
            ];
        }

        return $records;
    }
}
