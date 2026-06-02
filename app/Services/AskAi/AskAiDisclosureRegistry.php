<?php

namespace App\Services\AskAi;

/**
 * AskAiDisclosureRegistry — Ask AI Disclosure Registry
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Authoritative registry of all Ask AI disclosure definitions.
 * Provides a single source of truth for which disclosures exist, their metadata,
 * and whether a given disclosure key is valid. All future Ask AI response phases
 * must source disclosures exclusively from this registry.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database read or write (query, save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiDisclosureRegistry
{
    /**
     * All registered Ask AI disclosure definitions.
     *
     * Each entry carries:
     *   key         — canonical disclosure identifier
     *   label       — human-readable display name
     *   description — what this disclosure communicates to users
     */
    private const DISCLOSURES = [
        'GENERAL_EDUCATIONAL_INFORMATION' => [
            'key'         => 'GENERAL_EDUCATIONAL_INFORMATION',
            'label'       => 'General Educational Information',
            'description' => 'Responses are provided for general educational purposes only and do not constitute professional advice of any kind.',
        ],

        'PROPERTY_INTELLIGENCE_DISCLOSURE' => [
            'key'         => 'PROPERTY_INTELLIGENCE_DISCLOSURE',
            'label'       => 'Property Intelligence Disclosure',
            'description' => 'Property intelligence data is derived from structured analysis and may not reflect current market conditions or actual property characteristics.',
        ],

        'LOCATION_INTELLIGENCE_DISCLOSURE' => [
            'key'         => 'LOCATION_INTELLIGENCE_DISCLOSURE',
            'label'       => 'Location Intelligence Disclosure',
            'description' => 'Location intelligence scores and narratives are generated from aggregated data sources and are not guarantees of neighborhood quality or suitability.',
        ],

        'COMPATIBILITY_DISCLOSURE' => [
            'key'         => 'COMPATIBILITY_DISCLOSURE',
            'label'       => 'Compatibility Disclosure',
            'description' => 'Compatibility scores are algorithmic estimates based on stated preferences and structured data. They do not guarantee a successful match or transaction outcome.',
        ],

        'LIMITED_DATA_DISCLOSURE' => [
            'key'         => 'LIMITED_DATA_DISCLOSURE',
            'label'       => 'Limited Data Disclosure',
            'description' => 'Responses may be based on incomplete or user-submitted data. Always verify information through independent sources before making decisions.',
        ],

        'NO_BROKERAGE_ADVICE' => [
            'key'         => 'NO_BROKERAGE_ADVICE',
            'label'       => 'No Brokerage Advice',
            'description' => 'Nothing in this response constitutes real estate brokerage advice. Consult a licensed real estate professional for guidance on your specific transaction.',
        ],

        'NO_LEGAL_ADVICE' => [
            'key'         => 'NO_LEGAL_ADVICE',
            'label'       => 'No Legal Advice',
            'description' => 'Nothing in this response constitutes legal advice. Consult a licensed attorney for guidance on legal matters related to your property transaction.',
        ],

        'NO_TAX_ADVICE' => [
            'key'         => 'NO_TAX_ADVICE',
            'label'       => 'No Tax Advice',
            'description' => 'Nothing in this response constitutes tax advice. Consult a qualified tax professional for guidance on tax implications of your property transaction.',
        ],

        'NO_LENDING_ADVICE' => [
            'key'         => 'NO_LENDING_ADVICE',
            'label'       => 'No Lending Advice',
            'description' => 'Nothing in this response constitutes mortgage or lending advice. Consult a licensed lender or mortgage professional for financing guidance.',
        ],

        'NO_INVESTMENT_ADVICE' => [
            'key'         => 'NO_INVESTMENT_ADVICE',
            'label'       => 'No Investment Advice',
            'description' => 'Nothing in this response constitutes investment advice. Consult a licensed financial advisor before making real estate investment decisions.',
        ],

        'FAIR_HOUSING_DISCLOSURE' => [
            'key'         => 'FAIR_HOUSING_DISCLOSURE',
            'label'       => 'Fair Housing Disclosure',
            'description' => 'This platform is committed to fair housing principles. Responses do not consider, reference, or make inferences about protected class characteristics.',
        ],
    ];

    /**
     * Return all registered disclosure definitions, keyed by disclosure key.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        return self::DISCLOSURES;
    }

    /**
     * Return true when the given key is a registered disclosure.
     *
     * @param  string $key  The disclosure key to check.
     * @return bool
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, self::DISCLOSURES);
    }

    /**
     * Return the disclosure definition for the given key, or null when not found.
     *
     * @param  string $key  The disclosure key to look up.
     * @return array|null
     */
    public function get(string $key): ?array
    {
        return self::DISCLOSURES[$key] ?? null;
    }
}
