<?php

namespace App\Http\Livewire\Concerns;

use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingResumeGuard;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Product-scoped draft enumeration, resume and deletion for the listing wizards.
 *
 * Every component that lists, resumes or deletes drafts uses these helpers instead of
 * hand-rolling `where('user_id', …)->where('is_draft', true)`. Eight components had
 * that pair copied by hand, none of them mentioned the product, and because both
 * products share one table per role, each was enumerating and deleting the other's work.
 *
 * A component opts in by declaring what it is:
 *
 *     protected const LISTING_WORKFLOW = ListingWorkflow::OFFER_LISTING;
 *     protected const LISTING_ROLE     = 'seller';
 *
 * A component serving several roles at runtime (TenantAgentAuction) omits LISTING_ROLE
 * and the trait reads `$this->user_type` — validated, with no fallback. An unrecognised
 * role yields no model, and every helper then refuses rather than guessing; the old
 * `default => tenant` shape is exactly how a seller id ended up hydrating tenant data.
 *
 * TWO-PHASE, ALWAYS: SQL narrows, the resolver decides.
 * See {@see \App\Models\Concerns\ScopesListingWorkflow} for why the whole rule is not
 * pushed into SQL. Nothing here may filter on the column alone.
 */
trait BelongsToListingWorkflow
{
    /** Which product this screen belongs to. */
    protected function listingWorkflow(): string
    {
        if (defined(static::class . '::LISTING_WORKFLOW')) {
            return constant(static::class . '::LISTING_WORKFLOW');
        }

        throw new \LogicException(static::class . ' must define LISTING_WORKFLOW.');
    }

    /**
     * Which role's table this screen edits, or null when it cannot be determined.
     *
     * Null is a real answer and callers must treat it as a refusal — never as "use the
     * default role".
     */
    protected function listingWorkflowRole(): ?string
    {
        $role = defined(static::class . '::LISTING_ROLE')
            ? constant(static::class . '::LISTING_ROLE')
            : ($this->user_type ?? null);

        return ListingWorkflow::isValidRole($role) ? $role : null;
    }

    /** @return class-string|null */
    protected function listingWorkflowModelClass(): ?string
    {
        return ListingWorkflow::modelClassForRole($this->listingWorkflowRole());
    }

    /**
     * Owner + draft-scoped query, SQL-narrowed to this product.
     *
     * Still a superset — finish with the resolver via {@see self::workflowDrafts()}.
     */
    protected function workflowDraftQuery()
    {
        $modelClass = $this->listingWorkflowModelClass();

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()
            ->where('user_id', Auth::id())
            ->where('is_draft', true)
            ->forWorkflow($this->listingWorkflow());
    }

    /**
     * This user's drafts FOR THIS PRODUCT, newest first.
     *
     * The resolver pass is what makes the answer exact. Rows it cannot classify —
     * unclassified, ambiguous, conflicting — appear in neither product's picker. That
     * matches the resume guard, deliberately: a draft you can see but cannot open would
     * be a worse experience than one that is simply not offered, and a draft that opens
     * into the wrong wizard is the bug being fixed.
     */
    protected function workflowDrafts()
    {
        $query = $this->workflowDraftQuery();

        if ($query === null) {
            return collect();
        }

        $resolver = app(ListingWorkflowResolver::class);
        $workflow = $this->listingWorkflow();

        return $query->latest('id')->get()
            ->filter(fn ($auction) => $resolver->matches($auction, $workflow))
            ->values();
    }

    protected function workflowHasDrafts(): bool
    {
        return $this->workflowDrafts()->isNotEmpty();
    }

    /**
     * Resolve a client-supplied listing id for resume, or null.
     *
     * Runs BEFORE any hydration. Owner, role, workflow and — for draft-resume routes —
     * draft state, all four.
     *
     * @param  string|null  $assignedRole  the role the ROUTE named, when it named one
     */
    protected function resumableListing($id, bool $mustBeDraft = true, ?string $assignedRole = null)
    {
        $modelClass = $this->listingWorkflowModelClass();

        if ($modelClass === null) {
            return null;
        }

        $auction = ListingResumeGuard::resolve(
            $modelClass,
            $id,
            $this->listingWorkflow(),
            $assignedRole ?? $this->listingWorkflowRole(),
            $mustBeDraft
            // No workflow policy is expressed here, by design. The guard has exactly one
            // rule and no way to ask it for another — see ListingResumeGuard.
        );

        if ($auction === null) {
            Log::info('[listing_resume] refused', [
                'component' => static::class,
                'listing_id' => $id,
                'workflow'  => $this->listingWorkflow(),
                'role'      => $this->listingWorkflowRole(),
                'must_be_draft' => $mustBeDraft,
                'reason'    => ListingResumeGuard::lastDenyReason(),
                'user_id'   => Auth::id(),
            ]);
        }

        return $auction;
    }

    /**
     * The ids this screen is allowed to delete — computed BEFORE any DELETE is issued.
     *
     * This ordering is the fix. `deleteAllDrafts()` previously plucked ids by
     * `user_id` + `is_draft`, then hard-deleted the matching `*_metas` rows with a raw
     * `DB::table()` call. Because both products share the table, a Hire "Delete All
     * Drafts" destroyed the user's Offer Listing drafts and their meta, and the raw
     * delete meant no Eloquent event could have intercepted it. Scoping the SELECT is
     * therefore the only place the boundary can hold — there is no observer to fall
     * back on, by design of the code being fixed.
     *
     * @return array<int>
     */
    protected function workflowDeletableDraftIds(): array
    {
        return $this->workflowDrafts()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Delete one draft, only if it belongs to this user AND this product AND is a draft.
     *
     * THE SAME RULE AS EVERY OTHER PATH — including for UNCLASSIFIED rows.
     * An earlier revision made this the one place an unstamped row could be destroyed, on
     * the grounds that the user had named the id so it was a claim rather than a sweep.
     * That was wrong twice over. The id is client input, so "the user named it" is not
     * evidence of anything; and this method HARD-DELETES the row and every one of its meta
     * rows. Being handed an id is the weakest possible warrant for the most destructive
     * operation on this trait, and because the tables are shared, the row destroyed may
     * well have been the other product's.
     *
     * A user with genuinely unclassifiable drafts therefore cannot clear them from here.
     * That is the accepted cost of the uniform rule; recovery has to be product-neutral,
     * and {@see \App\Console\Commands\ListingsWorkflowInventory} is how such rows are found.
     *
     * @return bool true when a row was deleted
     */
    protected function workflowDeleteDraft($id): bool
    {
        $modelClass = $this->listingWorkflowModelClass();

        if ($modelClass === null) {
            return false;
        }

        $auction = ListingResumeGuard::resolve(
            $modelClass,
            $id,
            $this->listingWorkflow(),
            $this->listingWorkflowRole(),
            true
        );

        if ($auction === null) {
            Log::info('[listing_draft_delete] refused', [
                'component'  => static::class,
                'listing_id' => $id,
                'workflow'   => $this->listingWorkflow(),
                'reason'     => ListingResumeGuard::lastDenyReason(),
                'user_id'    => Auth::id(),
            ]);

            return false;
        }

        return $this->purgeListingRows($modelClass, [(int) $auction->id]) > 0;
    }

    /**
     * Delete every draft this screen owns FOR THIS PRODUCT. Never the other product's.
     *
     * @return int rows deleted
     */
    protected function workflowDeleteAllDrafts(): int
    {
        $modelClass = $this->listingWorkflowModelClass();

        if ($modelClass === null) {
            return 0;
        }

        $ids = $this->workflowDeletableDraftIds();

        if ($ids === []) {
            return 0;
        }

        return $this->purgeListingRows($modelClass, $ids);
    }

    /**
     * Delete the given listing rows and their meta.
     *
     * The meta table and its foreign key are read off the model's own `meta()` relation
     * rather than spelled out, so the four roles' differing column names
     * (`seller_agent_auction_id`, `tenant_agent_auction_id`, …) cannot drift out of sync
     * with a hardcoded string here.
     *
     * @param  class-string  $modelClass
     * @param  array<int>    $ids  ALREADY workflow-scoped by the caller
     */
    private function purgeListingRows(string $modelClass, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $model    = new $modelClass();
        $relation = $model->meta();
        $metaTable = $relation->getRelated()->getTable();
        $foreignKey = $relation->getForeignKeyName();

        $deleted = DB::transaction(function () use ($modelClass, $ids, $metaTable, $foreignKey) {
            DB::table($metaTable)->whereIn($foreignKey, $ids)->delete();

            return $modelClass::query()->whereIn('id', $ids)->delete();
        });

        // Smart Tag rows are addressed by (listing_type, listing_id) with no foreign
        // key, by the house convention for multi-source tables, and the delete above
        // is a query-builder mass delete that fires no model events — so there is no
        // observer that could do this and it has to be an explicit call.
        //
        // AFTER the transaction, deliberately. The purger opens its own transaction,
        // and running it inside this one would let a Smart Tag failure roll back a
        // deletion the user asked for. Orphaned tag rows are inert — nothing reads
        // them and the resolver ignores rows it cannot parse — while a draft that
        // reappears because its tags could not be cleaned up is a real defect.
        //
        // Buyer, Tenant and Hire model classes are skipped inside purgeSilently():
        // this trait serves all four roles and both products, and Smart Tags attach
        // to Seller and Landlord Offer Listings only.
        \App\Services\SmartTags\SmartTagLifecycle::tryPurge($modelClass, $ids);

        // Seeker Smart Tag preferences — what a Buyer/Tenant Offer Listing ASKED FOR —
        // are addressed the same way and need the same explicit cleanup, for the same
        // reason: this mass delete fires no model events. A separate call, not part of
        // the lifecycle purge above, because that one is gated by the DERIVATION flags
        // and this one must not be: preferences written while the seeker feature was on
        // must still be removed when their draft is deleted with it off. Seller and
        // Landlord classes resolve to no seeker subject and are skipped inside. Never
        // throws, and runs after the transaction for the reason given above.
        \App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter::tryPurgeDeleted($modelClass, $ids);

        return $deleted;
    }
}
