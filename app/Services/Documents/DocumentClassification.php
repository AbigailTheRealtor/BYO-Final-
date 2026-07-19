<?php

namespace App\Services\Documents;

/**
 * HI-05 — trusted document classification (design model).
 *
 * Every uploaded listing document falls into exactly one class. The class does
 * NOT by itself decide view/download — an owner or authorized listing agent may
 * always access their listing's documents — but it governs the *capability
 * model* the follow-up Document Access batch will enforce:
 *
 *   AI_READABLE        Buyer-facing due-diligence (seller/flood/lead-paint/HOA/
 *                      condo/survey/inspection/environmental disclosures). MAY be
 *                      exposed to Ask AI once document retrieval exists — but Ask
 *                      AI document ingestion/retrieval is NOT built yet, so this
 *                      capability is design-only today. View/download of the
 *                      original file still requires an approved request.
 *
 *   REQUEST_REQUIRED   General listing documents. Not auto-AI-readable; a buyer
 *                      or buyer's agent must request access to view/download.
 *
 *   ALWAYS_RESTRICTED  Sensitive personal/transaction documents (government IDs,
 *                      bank statements, tax returns, wire instructions, proof of
 *                      funds, signed contracts, signature pages). Never
 *                      automatically available to Ask AI or to any non-owner;
 *                      explicit authorization only.
 *
 * The two capabilities are deliberately distinct (§2 of the product direction):
 *   - view_download_access — receive the original file
 *   - ai_query_access      — let Ask AI answer questions grounded in the document
 * The second is NOT implied by the first, and vice-versa.
 */
final class DocumentClassification
{
    public const AI_READABLE        = 'ai_readable';
    public const REQUEST_REQUIRED   = 'request_required';
    public const ALWAYS_RESTRICTED  = 'always_restricted';

    public const ALL = [
        self::AI_READABLE,
        self::REQUEST_REQUIRED,
        self::ALWAYS_RESTRICTED,
    ];

    /** Capability names (§2). */
    public const CAP_VIEW_DOWNLOAD = 'view_download_access';
    public const CAP_AI_QUERY      = 'ai_query_access';

    /**
     * Whether a document of this class MAY (by classification) be exposed to Ask
     * AI — subject to the runtime gates in ListingDocumentAccessService and,
     * ultimately, the not-yet-built retrieval enforcement. Only AI_READABLE
     * qualifies; REQUEST_REQUIRED and ALWAYS_RESTRICTED never auto-qualify.
     */
    public static function allowsAiQuery(string $classification): bool
    {
        return $classification === self::AI_READABLE;
    }

    public static function isValid(string $classification): bool
    {
        return in_array($classification, self::ALL, true);
    }
}
