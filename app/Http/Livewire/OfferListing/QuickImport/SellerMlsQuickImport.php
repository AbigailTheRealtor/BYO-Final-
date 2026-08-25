<?php

namespace App\Http\Livewire\OfferListing\QuickImport;

use App\Http\Livewire\OfferListing\Concerns\SellerSaleTerms;
use App\Support\Listing\SellerSaleTermsOptions;

/**
 * Seller's shortened MLS creation path.
 *
 * Everything here is the Seller's half of the split the whole feature is built
 * on: MLS supplies the property facts, BidYourOffer asks how the seller wants to
 * transact. So this class contains no property questions at all — only the sale
 * terms the MLS does not answer.
 *
 * THE TERMS STEP IS THE MANUAL FLOW'S TERMS TAB.
 * ----------------------------------------------
 * Not a copy of it, not a subset of it, not a re-implementation that happens to
 * agree with it today. `canonicalTermsPartial()` names the very blade the manual
 * Seller Create and Edit screens render, and {@see SellerSaleTerms} supplies the
 * property surface it binds to. Labels, option vocabularies, help text,
 * conditional sections, defaults and persistence keys therefore cannot differ
 * between the two entry paths, because there is only one of each.
 *
 * WHAT THIS REPLACES
 * ------------------
 * This class used to declare questionSchema(): nineteen hand-copied fields with
 * their own labels and their own option lists. Its own docblock claimed the
 * financing vocabulary was "the canonical list … copied exactly", which is the
 * problem stated as if it were the solution — a copy is exactly the thing that
 * drifts. What that list could not express was worse than what it got wrong: it
 * had no conditional sections whatsoever, so a seller arriving through quick
 * import could not offer seller financing, an assumable loan, a lease option, a
 * lease purchase, an exchange, a crypto or NFT split, a balloon schedule, a
 * bidding-period reserve or buy-now price, or any Estimated Payment Assumption.
 * Those questions were not hidden from them; they did not exist on this path.
 *
 * Adding a Sale Terms field now means editing the canonical partial and
 * SellerSaleTerms. This class does not get a vote, which is the point.
 */
class SellerMlsQuickImport extends MlsQuickImportComponent
{
    use SellerSaleTerms;

    /**
     * The one canonical Sale Terms field the trait cannot declare.
     *
     * Manual Create defaults it to '' and manual Edit to '$'; a trait property
     * may not carry two initial values, so it stays with each host. This is a
     * creation flow, so it matches Create.
     * @see SellerSaleTerms
     */
    public $assignment_fee_type = '';

    public function role(): string
    {
        return 'seller';
    }

    /**
     * "Desired Sale Price" — the Seller's asking price.
     *
     * `maximum_budget` is the meta key behind that input, which reads oddly but
     * is the existing storage key and is deliberately not renamed here. See the
     * note on the same mapping in MlsFieldMap::seller().
     */
    public function priceField(): string
    {
        return 'maximum_budget';
    }

    /**
     * The canonical Seller Sale Terms tab — the same partial
     * offer-seller-listing.blade.php and offer-seller-listing-edit.blade.php
     * include.
     */
    public function canonicalTermsPartial(): ?string
    {
        return 'livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms';
    }

    /**
     * No quick-import-specific schema exists any more.
     *
     * Still declared because the base class requires it for the schema-driven
     * roles; returning an empty array is what states that Seller is not one of
     * them. A future edit that puts fields back in here is reintroducing the
     * duplicate this commit removed — put them in the canonical partial instead.
     */
    public function questionSchema(): array
    {
        return [];
    }

    /**
     * Seed the MLS list price into the canonical asking-price property.
     *
     * The manual tab binds $maximum_budget directly, so the seeded figure has to
     * land there rather than in the schema-driven $terms bag, or the input would
     * render empty and the seller would retype a number we already hold.
     */
    protected function applySeededPrice(string $seed): void
    {
        if ($this->maximum_budget === '' || $this->maximum_budget === null) {
            $this->maximum_budget = $seed;
        }
    }

    /**
     * Write the sale terms exactly as manual Create writes them.
     *
     * One routine, one set of keys, one set of transforms. This is what makes a
     * quick-imported listing and a manually created one indistinguishable to the
     * view page, search, matching, the bid wizards and Ask AI.
     */
    protected function persistCanonicalTerms(object $auction): void
    {
        // The financing select is a select2 the client drives, so what arrives
        // in $offered_financing is whatever the browser sent. Everything stored
        // must be a value the tab actually offered — the review screen and the
        // published page both present this field as a fixed vocabulary, and a
        // free-text entry there would render as though the seller had chosen it.
        //
        // Checked against the SAME list the tab rendered from, not a copy of it.
        $this->offered_financing = array_values(array_intersect(
            array_map('strval', (array) $this->offered_financing),
            SellerSaleTermsOptions::financingNames(),
        ));

        $this->saveSellerSaleTermsMeta($auction);
    }

    /**
     * Required before review.
     *
     * Deliberately only the asking price. The manual flow's publish rules
     * (SellerPublishValidation) impose no required rule on ANY sale-terms field
     * — the sole terms-adjacent requirement is auction_time for a Bidding Period
     * listing, which continueToTerms() already enforces on this path too.
     *
     * The price guard is kept because this path seeds it from the MLS record and
     * publishing a listing with no asking price is not a state the flow should
     * be able to reach. The previous additional requirement on "Financing You
     * Will Accept" is dropped: the manual flow does not demand it, and demanding
     * it here made quick import stricter than the screen it is meant to mirror.
     *
     * @return list<string>
     */
    protected function missingCanonicalTerms(): array
    {
        return trim((string) $this->maximum_budget) === ''
            ? ['Desired Sale Price']
            : [];
    }

    /**
     * The terms summary on the review step.
     *
     * Reads the canonical field set, so a term added to the tab appears here
     * without anyone remembering to add it. Empty answers are omitted — the
     * review screen states what the seller chose, not every question that
     * exists.
     *
     * @return array<string, string>
     */
    public function canonicalTermsReview(): array
    {
        $rows = [];

        foreach (static::sellerSaleTermsFields() as $field) {
            if ($field === 'showPaymentAssumptions') {
                continue; // a disclosure toggle, not an answer
            }

            $value = $this->{$field} ?? '';

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $rows[$this->humaniseTermField($field)] = $value;
        }

        return $rows;
    }

    /**
     * Field name → readable label for the review list.
     *
     * The canonical partial's labels live in markup rather than in a data
     * structure, so there is nothing to read them from; deriving the label from
     * the field name keeps the review honest about which stored key it is
     * showing instead of inventing a second label list that could disagree with
     * the tab.
     */
    private function humaniseTermField(string $field): string
    {
        $label = str_replace('_', ' ', $field);
        $label = preg_replace('/\bhoa\b/i', 'HOA', $label);
        $label = preg_replace('/\bnft\b/i', 'NFT', $label);
        $label = preg_replace('/\bpmi\b/i', 'PMI', $label);
        $label = preg_replace('/\bpct\b/i', '%', $label);

        return ucfirst($label);
    }
}
