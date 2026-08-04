<?php

namespace App\Http\Livewire\Concerns;

/**
 * Phase 9D — Search Areas (Buyer / Tenant "Location Preferences" blob). LOAD-SIDE ONLY.
 *
 * Shared load / prefill plumbing for the Search Areas map widget, used by the four Hire
 * Buyer/Tenant Agent components. The four Offer components deliberately do NOT use this
 * trait — they carry their own inline copies (FINDING 2B-2, still open).
 *
 * THE SAVE SIDE IS GONE — REMOVED BY THE G1f CLOSEOUT
 * ---------------------------------------------------
 * This trait used to carry `saveSearchAreas()`, which wrote the `location_dna_preferences`
 * blob verbatim and mirrored the discrete `state` / `counties` / `cities` meta out of it.
 * G1f-1 … G1f-6 migrated all eight workflow components to
 * {@see \App\Services\LocationDna\Persistence\LocationDnaPersistenceService}, after which
 * that method had ZERO callers and was dead code that still contained a canonical write.
 *
 * It is removed rather than left in place, because a dead method holding a canonical write
 * kept this file counted as a §21 direct writer and gave a future component an easy way to
 * silently reacquire the pre-consolidation semantics. All Location DNA WRITING now happens
 * through the canonical writer, and only there.
 *
 * The behaviour it used to have was defective in ways the canonical writer fixes, and those
 * defects were characterised before the migration so parity could be proved. Their corrected
 * counterparts live in the G1f migration suites and in
 * `G1f1LocationDnaPersistenceServiceTest`; the obsolete characterisations were retired with
 * the method.
 *
 * WHAT REMAINS, AND WHY
 * ---------------------
 *   - `loadSearchAreas()` — UNCHANGED, and still live: four production call sites, one per
 *     Hire component. This is why the trait is retained rather than deleted.
 *   - `hydrateDiscreteLocationFromBlob()` — UNCHANGED and deliberately KEPT even though the
 *     trait no longer calls it. It is the reference implementation the four inline Offer
 *     copies are compared against by
 *     {@see \Tests\Unit\Spatial\SearchAreasWidgetContractTest} (FINDING 2B-2, the open 5→1
 *     consolidation). Removing it would silently close an unresolved finding, which is a
 *     separate decision from retiring a dead writer.
 *
 * Storage read on the load side:
 *   - `location_dna_preferences` meta — the full Search Areas / Location DNA JSON blob.
 *   - Legacy discrete `state` / `counties` / `cities` meta — merged into the in-memory blob
 *     for prefill only. The DB is never written here.
 *
 * Host contract: the consuming component must declare the public `$state`, `$counties`, and
 * `$cities` props. Reads are `property_exists`-guarded so the trait is safe to mix into
 * components that omit one.
 *
 * Pairs with {@see \App\Http\Livewire\OfferListing\Concerns\HasImportantPlaces}.
 */
trait HasSearchAreas
{
    /** Decoded Location DNA / Search Areas blob handed to the map partial for prefill. */
    public $existingLocationDna = [];

    /** Raw blob JSON bridged from the map widget (`wire:model.defer`). */
    public $location_dna_preferences_json = '';

    /**
     * Load the `location_dna_preferences` blob into the component + partial prefill array,
     * merging legacy discrete `cities` / `state` / `counties` meta into the in-memory blob
     * (non-empty guards) so records saved before the map widget tracked those fields still
     * pre-populate their tags. The DB blob is NOT mutated here — only on an explicit save.
     */
    protected function loadSearchAreas($auction): void
    {
        $ldnaRaw = $auction->info('location_dna_preferences');
        $ldna    = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? []) : [];

        // Legacy `cities` meta → in-memory blob when the blob lacks cities.
        if (empty($ldna['cities'] ?? [])) {
            $legacyCitiesRaw = $auction->info('cities');
            if ($legacyCitiesRaw) {
                $legacyCities = is_string($legacyCitiesRaw)
                    ? (json_decode($legacyCitiesRaw, true) ?? [])
                    : (array) $legacyCitiesRaw;
                $legacyCities = array_values(array_filter(
                    $legacyCities,
                    fn($c) => is_string($c) && trim($c) !== ''
                ));
                if (!empty($legacyCities)) {
                    $ldna['cities'] = $legacyCities;
                }
            }
        }

        $this->existingLocationDna           = $ldna;
        $this->location_dna_preferences_json = $ldnaRaw ?? '';

        // 9B-2 prefill: seed the blob's Preferred State / counties from the discrete meta
        // when the blob lacks them, so the map partial pre-populates. In-memory only; the
        // JS bridge carries the merged blob back on save.
        if (property_exists($this, 'state')
            && empty($this->existingLocationDna['state'] ?? '')
            && !empty($this->state)
        ) {
            $this->existingLocationDna['state'] = $this->state;
        }
        if (property_exists($this, 'counties')
            && empty($this->existingLocationDna['counties'] ?? [])
            && !empty($this->counties)
        ) {
            $this->existingLocationDna['counties'] = array_values(array_filter(
                (array) $this->counties,
                fn($c) => is_string($c) && trim($c) !== ''
            ));
        }
    }

    /**
     * Mirror the Search Areas blob's state / counties into the discrete `$state` / `$counties`
     * props. Call before validation (the discrete Acceptable State/Counties inputs were
     * removed — the blob is the editing surface) and again before the discrete meta write.
     * Non-empty guards preserve backward compatibility — an empty blob value never wipes an
     * existing discrete value.
     */
    protected function hydrateDiscreteLocationFromBlob(): void
    {
        $ldna = json_decode($this->location_dna_preferences_json ?? '', true);
        if (!is_array($ldna)) {
            return;
        }
        if (property_exists($this, 'state') && trim((string) ($ldna['state'] ?? '')) !== '') {
            $this->state = trim((string) $ldna['state']);
        }
        if (property_exists($this, 'counties') && !empty($ldna['counties'] ?? [])) {
            $this->counties = array_values(array_filter(
                (array) $ldna['counties'],
                fn($c) => is_string($c) && trim($c) !== ''
            ));
        }
    }

}
