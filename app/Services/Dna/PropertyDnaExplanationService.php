<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * PropertyDnaExplanationService — Phase O Property DNA Explanation Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a TRANSLATION LAYER ONLY. It converts persisted Property DNA
 * profile arrays (ai_buyer_archetype_tags, ai_marketing_hooks) into structured
 * explanation records using static constant maps.
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
 * All persisted values (tag strings, trait keys, trait values) are passed through
 * verbatim — exactly as stored. No string transformation, case conversion,
 * underscore-to-space substitution, or label lookup is applied to values.
 *
 * All output is deterministic and reproducible from persisted DNA profile data.
 * Output order is deterministic: input order from the persisted array is preserved.
 * No reordering, sorting, or weighting is applied.
 * ==================================================================================
 */
class PropertyDnaExplanationService
{
    /**
     * Neutral, factual explanation strings for each archetype tag prefix.
     *
     * Each entry describes what the tag prefix dimension slot represents —
     * not what the specific persisted value implies about the property.
     *
     * All strings are:
     *   - Factual and neutral — no endorsements, no forecasts, no rankings.
     *   - Free of protected-class language, demographic inference, or behavioral inference.
     *   - Deterministically assigned from the tag prefix extracted from persisted data.
     */
    private const TAG_PREFIX_EXPLANATIONS = [
        'type'       => 'Identifies the structural or categorical property type recorded on the listing.',
        'style'      => 'Identifies the architectural or stylistic classification recorded on the listing.',
        'condition'  => 'Identifies the physical condition or state of the property as recorded on the listing.',
        'amenity'    => 'Identifies an on-site amenity or facility that the property listing indicates is present.',
        'parking'    => 'Identifies the type of parking facility the property listing indicates is available.',
        'feature'    => 'Identifies a physical feature or specified terms recorded on the property listing.',
        'policy'     => 'Identifies an occupancy or use policy term that the property listing indicates is specified.',
        'community'  => 'Identifies a community designation or restriction recorded on the property listing.',
        'use'        => 'Identifies a use classification (such as commercial) recorded on the property listing.',
        'governance' => 'Identifies a governance or association structure recorded on the property listing.',
        'timing'     => 'Identifies a timing or availability term that the property listing indicates is specified.',
        'structure'  => 'Identifies a transaction or lease structure option recorded on the property listing.',
        'financing'  => 'Identifies a financing option or loan structure that the property listing indicates is available.',
        'marketing'  => 'Identifies a marketing asset or presentation feature recorded on the property listing.',
    ];

    /**
     * Neutral, factual explanation strings for each marketing hook trait key.
     *
     * Each entry describes what the trait dimension slot represents —
     * not what the specific persisted value implies about the property.
     */
    private const HOOK_TRAIT_EXPLANATIONS = [
        'property_type'   => 'The property type dimension recorded on the listing.',
        'bedrooms'        => 'The bedroom count dimension recorded on the listing.',
        'bathrooms'       => 'The bathroom count dimension recorded on the listing.',
        'minimum_sqft'    => 'The minimum heated square footage dimension recorded on the listing.',
        'total_acreage'   => 'The total acreage dimension recorded on the listing.',
        'occupant_status' => 'The current occupancy status dimension recorded on the listing.',
        'lease_length'    => 'The lease length or flexibility dimension recorded on the listing.',
        'sale_provision'  => 'The sale provision type dimension recorded on the listing.',
        'financing_types' => 'The financing types offered dimension recorded on the listing.',
        'view'            => 'The view preference dimension recorded on the listing.',
    ];

    /**
     * Generate structured explanation records from a persisted PropertyDnaProfile.
     *
     * Reads the `ai_buyer_archetype_tags` and `ai_marketing_hooks` arrays from the
     * profile model and returns a structured array of explanation records.
     *
     * For archetype tags: the tag string is split on the first `:` to extract the
     * prefix; the prefix is mapped to a neutral explanation string. The full original
     * tag string is passed through verbatim as the `tag` field.
     *
     * For marketing hooks: the `trait` key is mapped to a neutral explanation string.
     * Both `trait` and `value` fields are passed through verbatim.
     *
     * Output structure:
     * [
     *     'archetype_tag_explanations' => [
     *         ['tag' => string, 'explanation' => string],
     *         ...
     *     ],
     *     'marketing_hook_explanations' => [
     *         ['trait' => string, 'value' => string, 'explanation' => string],
     *         ...
     *     ],
     * ]
     *
     * Output order is deterministic: elements appear in the order they are stored
     * in the persisted array. No reordering, sorting, or weighting is applied.
     *
     * If a tag prefix or trait key is not found in the constant maps, a neutral
     * fallback string is used — no element present in the persisted data is silently
     * dropped.
     *
     * @param  PropertyDnaProfile $profile  A persisted, cast profile model instance.
     * @return array{archetype_tag_explanations: array, marketing_hook_explanations: array}
     */
    public function generate(PropertyDnaProfile $profile): array
    {
        $archetypeTags  = (array) ($profile->ai_buyer_archetype_tags ?? []);
        $marketingHooks = (array) ($profile->ai_marketing_hooks ?? []);

        return [
            'archetype_tag_explanations'  => $this->mapArchetypeTags($archetypeTags),
            'marketing_hook_explanations' => $this->mapMarketingHooks($marketingHooks),
        ];
    }

    /**
     * Map an array of archetype tag strings to explanation records.
     *
     * Each tag string is split on the first `:` to extract the prefix. The prefix
     * is looked up in TAG_PREFIX_EXPLANATIONS. The full original tag string is
     * placed in the `tag` field verbatim — no normalization or transformation.
     *
     * If the prefix is not found in TAG_PREFIX_EXPLANATIONS, a neutral fallback
     * string is used so that no tag present in the persisted data is silently dropped.
     *
     * @param  string[] $tags  Archetype tag strings from persisted profile data.
     * @return array<int, array{tag: string, explanation: string}>
     */
    private function mapArchetypeTags(array $tags): array
    {
        $records = [];

        foreach ($tags as $tag) {
            $tag = (string) $tag;

            $colonPos = strpos($tag, ':');
            $prefix   = ($colonPos !== false) ? substr($tag, 0, $colonPos) : $tag;

            if (isset(self::TAG_PREFIX_EXPLANATIONS[$prefix])) {
                $explanation = self::TAG_PREFIX_EXPLANATIONS[$prefix];
            } else {
                $explanation = 'No explanation is mapped for this archetype tag prefix.';
            }

            $records[] = [
                'tag'         => $tag,
                'explanation' => $explanation,
            ];
        }

        return $records;
    }

    /**
     * Map an array of marketing hook records to explanation records.
     *
     * Each hook record contains a `trait` key and a `value`. The `trait` key is
     * looked up in HOOK_TRAIT_EXPLANATIONS. Both `trait` and `value` are placed
     * in the output verbatim — no normalization or transformation of either field.
     *
     * If the trait key is not found in HOOK_TRAIT_EXPLANATIONS, a neutral fallback
     * string is used so that no hook present in the persisted data is silently dropped.
     *
     * @param  array $hooks  Marketing hook records from persisted profile data.
     * @return array<int, array{trait: string, value: string, explanation: string}>
     */
    private function mapMarketingHooks(array $hooks): array
    {
        $records = [];

        foreach ($hooks as $hook) {
            $hook  = (array) $hook;
            $trait = (string) ($hook['trait'] ?? '');
            $value = (string) ($hook['value'] ?? '');

            if (isset(self::HOOK_TRAIT_EXPLANATIONS[$trait])) {
                $explanation = self::HOOK_TRAIT_EXPLANATIONS[$trait];
            } else {
                $explanation = 'No explanation is mapped for this marketing hook trait key.';
            }

            $records[] = [
                'trait'       => $trait,
                'value'       => $value,
                'explanation' => $explanation,
            ];
        }

        return $records;
    }
}
