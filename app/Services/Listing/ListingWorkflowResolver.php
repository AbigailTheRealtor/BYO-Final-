<?php

namespace App\Services\Listing;

use App\Support\Listing\ListingWorkflow;

/**
 * THE single answer to "which product does this listing row belong to?".
 *
 * Every picker, guard, hub and backfill in the application asks this class and nothing
 * else. That is the entire design requirement: before this existed, four different
 * surfaces inferred the product four different ways — the Hire hub by excluding a meta
 * stamp *or* a list of "offer-ish" keys, the Offer hub by the mirror of that, the draft
 * pickers by not asking at all, and the resume routes by trusting whatever id arrived.
 * Two of those disagreed with each other, and the pickers agreed with nobody.
 *
 * EVIDENCE, IN PRECEDENCE ORDER
 * -----------------------------
 *   1. Native column `workflow_type`      — the durable SSOT (post-migration rows).
 *   2. Legacy EAV meta `workflow_type`    — the transitional stamp (wizard-saved rows).
 *   3. Deterministic legacy provenance    — inferred from a key only one product writes.
 *
 * Precedence decides which signal *names* the row. It does NOT let a higher signal
 * silence a lower one that disagrees: any two decisive signals pointing different ways
 * is a CONFLICT and fails closed. "Native wins, ignore the rest" would have quietly
 * papered over exactly the corruption this change exists to stop — a row stamped
 * hire_agent by a Hire wizard that resumed an Offer Listing draft still carries its
 * quick-import provenance, and that disagreement is the evidence, not noise.
 *
 * WHAT COUNTS AS DETERMINISTIC PROVENANCE
 * ---------------------------------------
 * Only keys exactly one product can write:
 *
 *   `mls_quick_import`  → OFFER_LISTING. Written solely by MlsQuickImportDraftWriter,
 *                         which is reachable only from Create Offer Listing.
 *   `service_type`      → HIRE_AGENT. Written by all eight Hire components and by
 *                         HireAgentDirectController; written by none of the eight Offer
 *                         Listing components, which have no service-type concept at all.
 *
 * DELIBERATELY EXCLUDED: the `OFFER_LISTING_META_KEYS` lists on the four Offer
 * controllers. They are a reasonable belt-and-braces filter for a hub, but they are not
 * sound as identity evidence — `listing_date` and `tenant_require` appear in those lists
 * and are written by BOTH products, so treating either as proof of Offer Listing would
 * misclassify ordinary Hire rows. Those lists stay where they are, doing the job they
 * already do; they do not get promoted into the resolver. See the checkpoint's
 * unresolved-questions section.
 */
class ListingWorkflowResolver
{
    /**
     * The version of the CLASSIFICATION RULES in this class. Bump on any semantic change.
     *
     * DESCRIPTIVE ONLY — NOTHING DEPENDS ON IT FOR CORRECTNESS.
     * -------------------------------------------------------
     * This constant used to be load-bearing: the 2026_08_27_000002 backfill migration
     * called this resolver and pinned this value, declining to run if the two disagreed.
     * That made a historical migration's meaning depend on a developer remembering to
     * bump a constant — detection by convention, which is not determinism.
     *
     * That migration now carries its own frozen classifier and does not reference this
     * class at all, so drifting these rules can no longer change what any shipped
     * migration does. This value survives as a human-readable marker for changelogs,
     * telemetry and inventory reports.
     *
     * You should still bump it when the rules below change semantically — adding,
     * removing or reweighting an evidence source; changing precedence or what counts as a
     * conflict; changing which values normalise to "absent"; changing the truthiness fold.
     * Comments, refactors and renames do not need a bump. But forgetting is now a
     * documentation lapse, not a data-integrity bug.
     *
     * IF YOU CHANGE THESE RULES AND EXISTING DATA NEEDS REVISITING, write a NEW migration
     * carrying its own frozen snapshot. Never edit a released one.
     */
    public const CLASSIFICATION_VERSION = '2026-08-27.1';

    /**
     * The workflow this row belongs to, or null when it cannot be determined safely.
     *
     * Null covers unclassified, ambiguous AND conflicting — every caller that only
     * needs a yes/no should treat null as "refuse". Callers needing the distinction
     * (the inventory command, error reporting) should use {@see self::classify()}.
     */
    public function resolve($auction): ?string
    {
        return $this->classify($auction)->workflow;
    }

    /** Does this row definitively belong to $workflow? Fails closed on any doubt. */
    public function matches($auction, string $workflow): bool
    {
        return $this->classify($auction)->is($workflow);
    }

    /**
     * Full classification, including why.
     */
    public function classify($auction): ListingWorkflowClassification
    {
        if (! is_object($auction)) {
            return ListingWorkflowClassification::unclassified();
        }

        $evidence = [];

        // ── 1. Native column ────────────────────────────────────────────────────
        $native = null;

        if (ListingWorkflow::columnAvailable(get_class($auction))) {
            $raw = $auction->getAttribute(ListingWorkflow::COLUMN);
            $raw = $this->normalise($raw);

            if ($raw !== null) {
                $evidence['native'] = $raw;

                if (! ListingWorkflow::isValid($raw)) {
                    // An unrecognised value in the SSOT column is never guessed past.
                    return ListingWorkflowClassification::ambiguous(
                        $evidence,
                        "native workflow_type holds unrecognised value [{$raw}]"
                    );
                }

                $native = $raw;
            }
        }

        // ── 2. Legacy EAV stamp ─────────────────────────────────────────────────
        $eav = null;
        $rawEav = $this->normalise($this->meta($auction, ListingWorkflow::META_KEY));

        if ($rawEav !== null) {
            $evidence['eav'] = $rawEav;

            if (! ListingWorkflow::isValid($rawEav)) {
                return ListingWorkflowClassification::ambiguous(
                    $evidence,
                    "EAV workflow_type holds unrecognised value [{$rawEav}]"
                );
            }

            $eav = $rawEav;
        }

        // ── 3. Deterministic legacy provenance ──────────────────────────────────
        $provenance = null;

        $quickImport = $this->normalise($this->meta($auction, ListingWorkflow::META_QUICK_IMPORT));
        $serviceType = $this->normalise($this->meta($auction, ListingWorkflow::META_SERVICE_TYPE));

        $saysOffer = $quickImport !== null && $this->isTruthy($quickImport);
        $saysHire  = $serviceType !== null;

        if ($saysOffer) {
            $evidence['provenance_quick_import'] = $quickImport;
        }

        if ($saysHire) {
            $evidence['provenance_service_type'] = $serviceType;
        }

        if ($saysOffer && $saysHire) {
            // A row carrying both products' fingerprints. This is precisely what the
            // cross-product resume bug produced: an imported Offer draft opened and
            // saved by a Hire wizard. Refuse it and report it; do not pick a side.
            return ListingWorkflowClassification::conflicting(
                $evidence,
                'row carries both MLS quick-import provenance (offer_listing) and '
                . 'service_type provenance (hire_agent)'
            );
        }

        if ($saysOffer) {
            $provenance = ListingWorkflow::OFFER_LISTING;
        } elseif ($saysHire) {
            $provenance = ListingWorkflow::HIRE_AGENT;
        }

        // ── Reconcile ───────────────────────────────────────────────────────────
        $signals = array_values(array_unique(array_filter(
            [$native, $eav, $provenance],
            static fn ($v) => $v !== null
        )));

        if ($signals === []) {
            return ListingWorkflowClassification::unclassified($evidence);
        }

        if (count($signals) > 1) {
            return ListingWorkflowClassification::conflicting(
                $evidence,
                'workflow signals disagree: ' . $this->describeDisagreement($native, $eav, $provenance)
            );
        }

        return ListingWorkflowClassification::resolved($signals[0], $evidence);
    }

    private function describeDisagreement(?string $native, ?string $eav, ?string $provenance): string
    {
        $parts = [];

        if ($native !== null) {
            $parts[] = "native={$native}";
        }
        if ($eav !== null) {
            $parts[] = "eav={$eav}";
        }
        if ($provenance !== null) {
            $parts[] = "provenance={$provenance}";
        }

        return implode(', ', $parts);
    }

    /**
     * Read one meta value, tolerating models that do not expose `info()`.
     *
     * Returns null for "absent". The four auction models' `info()` returns `false` when
     * a key is missing, which is why the normaliser below folds false to null rather
     * than treating it as a value.
     */
    private function meta($auction, string $key)
    {
        if (! method_exists($auction, 'info')) {
            return null;
        }

        try {
            return $auction->info($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fold every "absent" spelling this codebase uses into a real null.
     *
     * `info()` answers `false`; a column answers `null`; a blank meta row answers `''`.
     * All three mean the same thing and must not be mistaken for a value — an empty
     * string treated as present would classify every blank-meta row as ambiguous.
     */
    private function normalise($value): ?string
    {
        if ($value === null || $value === false) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isTruthy(string $value): bool
    {
        return ! in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
    }
}
