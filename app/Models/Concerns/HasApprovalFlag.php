<?php

namespace App\Models\Concerns;

use App\Support\Listing\ListingFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * `is_approved`, read by its meaning — for the four role listing models.
 *
 * WHY THIS EXISTS
 * ---------------
 * The four role models cast `is_approved` to `boolean`, and Eloquent's boolean
 * cast is `(bool) $value`. On PostgreSQL, `seller_agent_auctions` and
 * `buyer_agent_auctions` hold the flag in a varchar column, which keeps whatever
 * string a writer sent — and the Hire/Tenant draft path wrote the literal
 * 'false' between 2026-01-20 and 2026-03-31. `(bool) 'false'` is true, so such a
 * row read as APPROVED to every reader of the model: the Agent page and both
 * hubs, the public-visibility gates, document access and Ask AI.
 *
 * Approval is the same kind of flag as `is_sold`, stored in the same mixed
 * representations, so it gets the same answer: {@see ListingFlag}. The accessor
 * receives the RAW stored value. Handing ListingFlag the value after a boolean
 * cast would change nothing — the cast has already turned 'false' into true.
 *
 * It fails closed on purpose: a row holding 'false' passes no approval or
 * public-visibility gate anywhere, and no reader special-cases it back. A
 * read-only production count on 2026-09-11 found no such row.
 *
 * WRITES ARE UNCHANGED, WITH ONE NECESSARY EXCEPTION
 * --------------------------------------------------
 * Nothing is normalised on the way in; a writer's value is stored exactly as
 * before. What moves is how Eloquent decides whether an assignment is a change
 * at all. That comparison went through the same `(bool)` cast, so 'false' and
 * true were "equal" — an approval assigned to a 'false' row was silently never
 * written, which, now that the row reads as not approved, would leave it pending
 * for ever. Equivalence is therefore judged by the same contract as the read.
 * For every value on which `(bool)` and ListingFlag agree — every value a current
 * writer produces — the outcome is identical to before; in particular a Buyer
 * wizard's 'true' still does not rewrite a stored '1'.
 *
 * Reads never rewrite stored data. There is no migration and no normalisation pass.
 */
trait HasApprovalFlag
{
    public function getIsApprovedAttribute($value): bool
    {
        return ListingFlag::isTrue($value);
    }

    /**
     * Eloquent's own comparison for every other attribute; for `is_approved`,
     * two stored values are the same when they mean the same thing.
     *
     * @param  string  $key
     * @return bool
     */
    public function originalIsEquivalent($key)
    {
        if ($key !== 'is_approved') {
            return parent::originalIsEquivalent($key);
        }

        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original  = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        }

        if (is_null($attribute)) {
            return false;
        }

        return ListingFlag::isTrue($attribute) === ListingFlag::isTrue($original);
    }

    /**
     * Rows whose RAW stored `is_approved` is approved — the query-side reading of
     * the contract the accessor applies, from the same list.
     *
     * `where('is_approved', true)` is not that reading on a varchar column: PHP
     * true binds as 1 and matches '1' only, so a Buyer row the publish path
     * stored as 'true' — approved to the accessor — was dropped from queue,
     * profile and matching queries. ListingFlag::TRUE_VALUES binds as '1' and
     * 'true'; on a boolean column PostgreSQL reads every one of them as true.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('is_approved'), ListingFlag::TRUE_VALUES);
    }

    /**
     * The exact complement of approved(): '0', 'false', '' and anything else,
     * and NULL — which `whereNotIn` alone would drop. `where('is_approved', false)`
     * matches only '0' on a varchar column.
     */
    public function scopeNotApproved(Builder $query): Builder
    {
        $column = $query->qualifyColumn('is_approved');

        return $query->where(function (Builder $q) use ($column) {
            $q->whereNotIn($column, ListingFlag::TRUE_VALUES)->orWhereNull($column);
        });
    }
}
