<?php

namespace App\Services\Documents;

use App\Models\AcceptedBidSummary;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * HI-05 — the single authorization hook for listing documents.
 *
 * Every view/download and every (future) Ask AI query must pass through here.
 * Authorization is derived ONLY from trusted listing relationships — the
 * listing's own user_id and AcceptedBidSummary agent assignment — never from a
 * request-supplied user id, path, or listing reference.
 *
 * B1.4 access model (the Request Documents workflow is a later batch):
 *   - view/download  → listing owner OR authorized listing agent only. Ordinary
 *                      prospective users are refused; once the follow-up ships
 *                      they will use Request Documents to obtain access.
 *   - ai_query       → capability computed for the AI-readable class on a
 *                      published listing. DESIGN-ONLY: nothing ingests or
 *                      retrieves document content yet, so no live Ask AI path
 *                      consumes this. It exists so the follow-up can enforce it.
 *
 * Draft / unpublished listings: their documents are never reachable by ordinary
 * users because view/download already requires owner/agent. The owner (and an
 * authorized agent) may still reach their own draft's documents.
 */
class ListingDocumentAccessService
{
    /** listingType => AcceptedBidSummary.listing_type values that assign an agent to it. */
    private const AGENT_ASSIGNMENT_TYPES = [
        'seller' => ['seller', 'seller_agent'],
    ];

    public function resolveListing(string $listingType, int $listingId): ?Model
    {
        $model = ListingDocumentCatalog::modelFor($listingType);
        if ($model === null) {
            return null;
        }

        return $model::query()->find($listingId);
    }

    /**
     * May this user receive the original document file (view or download)?
     *
     * The rule (in order):
     *   1. listing owner or authorized listing agent → allowed (any document);
     *   2. otherwise, an authenticated user viewing a PUBLICLY VISIBLE listing
     *      may access a document classified AI_READABLE — the seven buyer-facing
     *      due-diligence categories only;
     *   3. everything else → denied (guests, draft/unpublished/archived listings,
     *      and REQUEST_REQUIRED / ALWAYS_RESTRICTED documents).
     *
     * Rule 2 is an INTERIM compatibility allowance: today those disclosures are
     * downloadable by anyone who can open a published listing (they sit on the
     * public disk). It preserves that buyer/agent visibility while still killing
     * guest, enumerable, draft, and public-URL access. The future Document Access
     * batch REPLACES this broad authenticated access with explicit
     * request / approval / revocation controls.
     */
    public function canViewDownload(?Authenticatable $user, string $listingType, int $listingId, string $documentKey): bool
    {
        if ($user === null || ! ListingDocumentCatalog::has($documentKey)) {
            return false;
        }

        $listing = $this->resolveListing($listingType, $listingId);
        if ($listing === null) {
            return false;
        }

        // (1) owner or authorized listing agent — any document.
        if ($this->isOwner($user, $listing) || $this->isAuthorizedAgent($user, $listingType, $listingId)) {
            return true;
        }

        // (2) interim gated-viewer — AI-readable due-diligence on a visible listing.
        return ListingDocumentCatalog::classificationFor($documentKey) === DocumentClassification::AI_READABLE
            && $this->isPubliclyVisible($listing);
    }

    /**
     * Document-key-independent gate used by both catalogued documents and the
     * indexed additional-documents. In the B1.4 foundation only the listing
     * owner or an authorized listing agent may receive any document file; the
     * Request Documents workflow (a later batch) will widen this to approved
     * requesters.
     */
    public function canAccessListingDocuments(?Authenticatable $user, string $listingType, int $listingId): bool
    {
        if ($user === null) {
            return false;
        }

        $listing = $this->resolveListing($listingType, $listingId);
        if ($listing === null) {
            return false;
        }

        return $this->isOwner($user, $listing)
            || $this->isAuthorizedAgent($user, $listingType, $listingId);
    }

    /**
     * DESIGN-ONLY capability check (§2). Whether Ask AI *would be permitted* to
     * answer questions grounded in this document. Not consumed by any live path
     * yet — Ask AI has no document ingestion/retrieval. Owner/agent always
     * qualify; other authenticated users qualify only for an AI-readable
     * document on a published listing.
     */
    public function canAiQuery(?Authenticatable $user, string $listingType, int $listingId, string $documentKey): bool
    {
        if ($user === null) {
            return false;
        }

        $classification = ListingDocumentCatalog::classificationFor($documentKey);
        if ($classification === null) {
            return false;
        }

        $listing = $this->resolveListing($listingType, $listingId);
        if ($listing === null) {
            return false;
        }

        if ($this->isOwner($user, $listing) || $this->isAuthorizedAgent($user, $listingType, $listingId)) {
            return true;
        }

        return DocumentClassification::allowsAiQuery($classification)
            && $this->isPubliclyVisible($listing);
    }

    /**
     * A listing whose documents may be surfaced to non-owner viewers: approved,
     * not draft, and not archived — mirroring SellerOfferListingController::view's
     * public-visibility gate. Missing flags default to not-visible / false.
     */
    public function isPubliclyVisible(Model $listing): bool
    {
        return filter_var($listing->is_approved ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! filter_var($listing->is_draft ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! filter_var($listing->is_archived ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function isOwner(Authenticatable $user, Model $listing): bool
    {
        return (int) ($listing->user_id ?? 0) === (int) $user->getAuthIdentifier();
    }

    private function isAuthorizedAgent(Authenticatable $user, string $listingType, int $listingId): bool
    {
        $types = self::AGENT_ASSIGNMENT_TYPES[$listingType] ?? [];
        if ($types === []) {
            return false;
        }

        return AcceptedBidSummary::whereIn('listing_type', $types)
            ->where('listing_id', $listingId)
            ->where('agent_user_id', $user->getAuthIdentifier())
            ->exists();
    }
}
