<?php

namespace App\Support\Listing;

use App\Services\Listing\ListingWorkflowResolver;
use Illuminate\Support\Facades\Auth;

/**
 * The one boundary every "resume this listing id" path crosses before it hydrates anything.
 *
 * FOUR QUESTIONS, ALL OF THEM, IN ORDER
 * -------------------------------------
 *   1. OWNER    — does the caller own this row?
 *   2. ROLE     — is it in the table this screen actually edits?
 *   3. WORKFLOW — is it the product this screen belongs to?
 *   4. DRAFT    — if the route resumes a draft, is it actually a draft?
 *
 * Before this existed, resume paths asked question 1 and stopped. That was enough to
 * stop one user reaching another's listing and not enough for anything else: an Offer
 * Listing draft, a published listing, and a draft from a different role's table were all
 * "owned by you", so all three were accepted and hydrated into whichever wizard asked.
 *
 * THIS DOES NOT REPLACE THE EXISTING AUTHORIZATION.
 * `ResolvesOwnedAuction`, `assertCanManageAuction()` and the controllers' own ownership
 * checks stay exactly where they are and keep their own behaviour. Workflow identity is
 * an ADDITIONAL boundary layered on top. The owner check is repeated here rather than
 * delegated because this guard must be safe to call on a path that has no other check —
 * a guard whose correctness depends on the caller having already done half the job is
 * not a guard.
 *
 * FAILS CLOSED, WITH NO EXCEPTIONS AND NO PER-ROUTE VARIATION.
 * Wrong owner, wrong role, wrong workflow, an unclassified workflow, an ambiguous one, a
 * conflicting one, or a published row handed to a draft-resume route all produce the same
 * answer: no listing.
 *
 * An earlier revision of this class carried one calibrated exception: an UNCLASSIFIED row
 * was ACCEPTED on edit routes, on the argument that a row making no product claim cannot
 * be the wrong product. That argument was withdrawn, and deliberately. The four
 * `*_agent_auctions` tables are SHARED, so an unstamped row is not a row without a
 * product — it is a row whose product cannot be proven. It belongs to exactly one of the
 * two, and accepting it on both products' edit routes guarantees that one of those two
 * acceptances hands another product's record to this wizard. Missing evidence must never
 * be resolved by asking which route happened to be dialled: ownership proves who, it does
 * not prove what.
 *
 * The cost is accepted: a genuinely unclassifiable historical row is unreachable through
 * both products until a product-neutral administrative recovery path exists. Stranding a
 * row is recoverable. Serving one product's record into the other's wizard — and hard
 * deleting it from there, meta and all — is not.
 *
 * @see \App\Console\Commands\ListingsWorkflowInventory to enumerate rows in that state.
 */
final class ListingResumeGuard
{
    // Why a resume was refused. Surfaced for logging and tests; never shown raw to users.
    public const DENY_MISSING        = 'missing';
    public const DENY_UNKNOWN_ROLE   = 'unknown_role';
    public const DENY_UNKNOWN_WORKFLOW = 'unknown_workflow';
    public const DENY_ROLE_MISMATCH  = 'role_mismatch';
    public const DENY_NOT_OWNER      = 'not_owner';
    public const DENY_NOT_DRAFT      = 'not_draft';
    public const DENY_WORKFLOW       = 'workflow_mismatch';
    public const DENY_UNCLASSIFIED   = 'workflow_unclassified';
    public const DENY_AMBIGUOUS      = 'workflow_ambiguous';
    public const DENY_CONFLICTING    = 'workflow_conflicting';

    /** Set by the last resolve() call on this request. Read by callers that log. */
    private static ?string $lastDenyReason = null;

    public static function lastDenyReason(): ?string
    {
        return self::$lastDenyReason;
    }

    /**
     * The listing this caller may resume, or null.
     *
     * @param  class-string  $modelClass         the model THIS screen edits
     * @param  mixed         $id                 the client-supplied listing id
     * @param  string        $expectedWorkflow   ListingWorkflow::HIRE_AGENT|OFFER_LISTING
     * @param  string|null   $expectedRole       the role the ROUTE named, when it named one
     * @param  bool          $mustBeDraft        true for draft-resume routes
     * @param  int|null      $userId             defaults to the authenticated user
     *
     * NOTE: there is deliberately NO parameter for relaxing the workflow rule. The
     * policy is uniform, so a caller has nothing to choose, and a flag would be an
     * invitation to re-open the hole one call site at a time. Its absence from both
     * signatures is asserted directly by StrictUnclassifiedPolicyTest.
     */
    public static function resolve(
        string $modelClass,
        $id,
        string $expectedWorkflow,
        ?string $expectedRole = null,
        bool $mustBeDraft = true,
        ?int $userId = null
    ): ?object {
        self::$lastDenyReason = null;

        if (! ListingWorkflow::isValid($expectedWorkflow)) {
            return self::deny(self::DENY_UNKNOWN_WORKFLOW);
        }

        $modelRole = ListingWorkflow::roleForModelClass($modelClass);

        if ($modelRole === null) {
            // The screen named a class that is not one of the four auction models.
            return self::deny(self::DENY_UNKNOWN_ROLE);
        }

        // The route's role and the screen's model must agree BEFORE anything is loaded.
        // This is the check that makes /hire/agent/auction/tenant/{sellerDraftId} a
        // refusal rather than a table search: the role in the URL is authoritative, and
        // if it does not match the table this screen edits there is nothing to look up.
        if ($expectedRole !== null) {
            if (! ListingWorkflow::isValidRole($expectedRole)) {
                return self::deny(self::DENY_UNKNOWN_ROLE);
            }

            if ($expectedRole !== $modelRole) {
                return self::deny(self::DENY_ROLE_MISMATCH);
            }
        }

        $userId = $userId ?? Auth::id();

        if (empty($userId) || $id === null || $id === '') {
            return self::deny(self::DENY_NOT_OWNER);
        }

        // Owner-scoped from the first query. The id is client input; it never selects a
        // row on its own.
        $auction = $modelClass::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($auction === null) {
            // Covers both "no such row" and "not yours" with one indistinguishable
            // answer, deliberately — the difference is not the caller's business.
            return self::deny(self::DENY_MISSING);
        }

        if ($mustBeDraft && ! self::isDraft($auction)) {
            return self::deny(self::DENY_NOT_DRAFT);
        }

        $classification = app(ListingWorkflowResolver::class)->classify($auction);

        if ($classification->isConflicting()) {
            return self::deny(self::DENY_CONFLICTING);
        }

        if ($classification->isAmbiguous()) {
            return self::deny(self::DENY_AMBIGUOUS);
        }

        if ($classification->isUnclassified()) {
            // NO EVIDENCE IS NOT NEUTRAL EVIDENCE — REFUSED ON EVERY ROUTE.
            //
            // An earlier revision accepted an unclassified row here whenever the caller
            // was an edit route, reasoning that a row making no product claim cannot be
            // "the wrong product". The tables are SHARED, which is what breaks that
            // reasoning: the row does have a product, we simply cannot prove which. It
            // belongs to exactly one of Hire Agent and Create Offer Listing, so accepting
            // it on BOTH products' edit routes guarantees one of the two acceptances is
            // wrong — and the same row was then hard-deletable, meta and all, from
            // whichever wizard the user happened to be standing in.
            //
            // Ownership proves WHO. It does not prove WHAT. Nothing else here does
            // either, so the answer is no.
            //
            // This does strand genuinely unclassifiable historical rows. That is the
            // accepted trade, and it is why the inventory command exists rather than a
            // per-product escape hatch: recovery must be product-neutral and deliberate.
            return self::deny(self::DENY_UNCLASSIFIED);
        }

        if (! $classification->is($expectedWorkflow)) {
            return self::deny(self::DENY_WORKFLOW);
        }

        return $auction;
    }

    /**
     * As {@see self::resolve()} but aborts instead of returning null.
     *
     * 404 for every refusal, including the ownership ones: a resume route that answered
     * 403 for "someone else's listing" and 404 for "no such listing" would confirm which
     * ids exist. The existing 403-based ownership checks elsewhere are untouched — this
     * is about what THIS guard reveals, not a change to their behaviour.
     */
    public static function resolveOrFail(
        string $modelClass,
        $id,
        string $expectedWorkflow,
        ?string $expectedRole = null,
        bool $mustBeDraft = true,
        ?int $userId = null
    ): object {
        $auction = self::resolve($modelClass, $id, $expectedWorkflow, $expectedRole, $mustBeDraft, $userId);

        if ($auction === null) {
            abort(404);
        }

        return $auction;
    }

    /**
     * Is this row a draft?
     *
     * `is_draft` is cast to bool on the four models, but historical rows store the
     * string 'true'/'false' in some columns, so the truthy set is spelled out rather
     * than trusted to a loose comparison — `(bool) 'false'` is true.
     */
    private static function isDraft(object $auction): bool
    {
        $value = $auction->getAttribute('is_draft');

        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private static function deny(string $reason): ?object
    {
        self::$lastDenyReason = $reason;

        return null;
    }
}
