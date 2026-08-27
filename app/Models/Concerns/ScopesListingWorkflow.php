<?php

namespace App\Models\Concerns;

use App\Support\Listing\ListingWorkflow;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL-level narrowing by product, for the four shared `*_agent_auctions` tables.
 *
 * READ THIS BEFORE USING IT AS A COMPLETE FILTER — IT IS NOT ONE.
 * ---------------------------------------------------------------
 * `forWorkflow()` is a deliberately conservative PRE-filter. It removes rows the
 * resolver could not possibly classify as the requested workflow, and it lets
 * everything else through for {@see \App\Services\Listing\ListingWorkflowResolver} to
 * decide in PHP. It is a superset of the true answer, never a subset.
 *
 * WHY NOT EXPRESS THE WHOLE RULE IN SQL?
 * Because the rule involves normalising three storage eras — a native column, an EAV
 * row whose absence `info()` reports as `false`, blank strings that mean "absent", and
 * a truthiness fold for the quick-import flag. Reimplementing all of that in a query
 * builder would mean two copies of the classification logic that must agree forever,
 * and the entire reason the resolver exists is that four surfaces previously each had
 * their own copy and two of them disagreed. One rule, in PHP, asked by everybody.
 *
 * So: narrow here for the index, decide there for the truth. Callers should use the
 * helpers on {@see \App\Http\Livewire\Concerns\BelongsToListingWorkflow}, which do both
 * halves in the right order. Do not "optimise away" the PHP pass.
 */
trait ScopesListingWorkflow
{
    /**
     * Narrow to rows that COULD belong to $workflow.
     *
     * Excludes only the certainties: a row whose native column names the other product
     * is either resolved to that product or conflicting, and neither outcome can be
     * $workflow. Rows with a NULL/absent column are kept — they may still resolve via
     * the EAV stamp or provenance, and that decision is the resolver's to make.
     */
    public function scopeForWorkflow(Builder $query, string $workflow): Builder
    {
        if (! ListingWorkflow::isValid($workflow)) {
            // An unrecognised workflow matches nothing rather than everything. A typo
            // must not silently widen a picker back to both products.
            return $query->whereRaw('1 = 0');
        }

        if (! ListingWorkflow::columnAvailable(static::class)) {
            // Pre-migration schema: no column to narrow on. Everything goes to PHP.
            return $query;
        }

        $other = array_values(array_filter(
            ListingWorkflow::ALL,
            static fn ($w) => $w !== $workflow
        ));

        return $query->where(function (Builder $q) use ($other) {
            $q->whereNull(ListingWorkflow::COLUMN)
              ->orWhereNotIn(ListingWorkflow::COLUMN, $other);
        });
    }

    /**
     * Rows carrying no native workflow identity — the backfill and inventory work list.
     */
    public function scopeWorkflowUnresolved(Builder $query): Builder
    {
        if (! ListingWorkflow::columnAvailable(static::class)) {
            return $query;
        }

        return $query->whereNull(ListingWorkflow::COLUMN);
    }
}
