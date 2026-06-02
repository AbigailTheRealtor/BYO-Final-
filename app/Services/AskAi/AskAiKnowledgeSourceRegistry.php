<?php

namespace App\Services\AskAi;

/**
 * AskAiKnowledgeSourceRegistry — Approved Knowledge Source Registry
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Authoritative registry of all approved Ask AI data sources.
 * Provides a single source of truth for which knowledge sources exist, their
 * metadata, and whether a given source key is valid. All other Ask AI stack
 * components must consult this registry to determine source eligibility.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiKnowledgeSourceRegistry
{
    /**
     * All approved Ask AI knowledge source definitions.
     *
     * Each entry carries:
     *   key                      — canonical source identifier
     *   label                    — human-readable display name
     *   description              — what this source contains
     *   version_key              — version constant for this source's data shape
     *   allowed_for_question_types — question type keys that may consume this source
     */
    private const SOURCES = [
        'listing' => [
            'key'         => 'listing',
            'label'       => 'Listing',
            'description' => 'Core listing record fields including title, type, location, and status.',
            'version_key' => 'ASK_AI_CONTEXT_V1',
            'allowed_for_question_types' => [
                'property_standout',
                'suited_audience',
                'marketing_angles',
                'missing_data',
            ],
        ],

        'property_intelligence' => [
            'key'         => 'property_intelligence',
            'label'       => 'Property Intelligence',
            'description' => 'Structured property DNA intelligence including strengths, highlights, positioning, target audiences, personality tags, and property story.',
            'version_key' => 'PROPERTY_INTELLIGENCE_V1',
            'allowed_for_question_types' => [
                'property_standout',
                'suited_audience',
                'marketing_angles',
            ],
        ],

        'location_intelligence' => [
            'key'         => 'location_intelligence',
            'label'       => 'Location Intelligence',
            'description' => 'Location DNA data including lifestyle scores, lifestyle categories, location narrative, and geocode status.',
            'version_key' => 'LIFESTYLE_V1',
            'allowed_for_question_types' => [
                'property_standout',
                'suited_audience',
                'marketing_angles',
            ],
        ],

        'buyer_avatar' => [
            'key'         => 'buyer_avatar',
            'label'       => 'Buyer Avatar',
            'description' => 'Buyer DNA avatar profile including avatar type, motivations, narrative, preferences, personality tags, and readiness score.',
            'version_key' => 'BUYER_AVATAR_V1',
            'allowed_for_question_types' => [
                'buyer_tenant_match',
            ],
        ],

        'tenant_avatar' => [
            'key'         => 'tenant_avatar',
            'label'       => 'Tenant Avatar',
            'description' => 'Tenant DNA avatar profile including avatar type, motivations, narrative, preferences, personality tags, and avatar version.',
            'version_key' => 'TENANT_AVATAR_V1',
            'allowed_for_question_types' => [
                'buyer_tenant_match',
            ],
        ],

        'compatibility' => [
            'key'         => 'compatibility',
            'label'       => 'Compatibility',
            'description' => 'Listing compatibility score between a demand listing and a supply listing, including overall score, sub-scores, highlights, warnings, and narrative.',
            'version_key' => 'BYA_COMPAT_V1',
            'allowed_for_question_types' => [
                'buyer_tenant_match',
                'compatibility_signals',
            ],
        ],

        'offer_analysis' => [
            'key'         => 'offer_analysis',
            'label'       => 'Offer Analysis',
            'description' => 'Accepted bid summary and offer analysis data for a listing, including bid identifiers, summary HTML, and signature records.',
            'version_key' => 'OFFER_ANALYSIS_V1',
            'allowed_for_question_types' => [
                'missing_data',
            ],
        ],

        'governance_documents' => [
            'key'         => 'governance_documents',
            'label'       => 'Governance Documents',
            'description' => 'Platform governance documents and policy references used to ground educational and compliance-related responses.',
            'version_key' => 'GOVERNANCE_DOCS_V1',
            'allowed_for_question_types' => [
                'educational',
            ],
        ],
    ];

    /**
     * Return all registered source definitions, keyed by source key.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        return self::SOURCES;
    }

    /**
     * Return true when the given source key is a registered, approved source.
     *
     * @param  string $source  The source key to check.
     * @return bool
     */
    public function isApproved(string $source): bool
    {
        return array_key_exists($source, self::SOURCES);
    }

    /**
     * Return the source definition for the given key, or null when not found.
     *
     * @param  string $source  The source key to look up.
     * @return array|null
     */
    public function getSource(string $source): ?array
    {
        return self::SOURCES[$source] ?? null;
    }

    /**
     * Return the version_key string for the given source, or null when not found.
     *
     * @param  string $source  The source key to look up.
     * @return string|null
     */
    public function requiredVersionKey(string $source): ?string
    {
        return self::SOURCES[$source]['version_key'] ?? null;
    }
}
