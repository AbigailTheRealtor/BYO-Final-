<?php

namespace App\Http\Livewire\Concerns;

/**
 * Phase 9D — Search Areas (Buyer / Tenant "Location Preferences" blob).
 *
 * Shared load / persist / discrete-mirror plumbing for the Search Areas map widget,
 * extracted from the Create Buyer/Tenant Offer components (BuyerOfferListing etc.) so
 * the Hire Buyer/Tenant Agent components can reuse the *identical* behaviour instead of
 * forking a second implementation.
 *
 * Storage (unchanged from the Create-Offer side):
 *   - `location_dna_preferences` meta — the full Search Areas / Location DNA JSON blob
 *     (cities, zip_codes, neighborhoods, counties, state, polygons, radius_searches,
 *     flexible_location, location_notes). The map widget is the single editing surface.
 *   - Discrete `state` / `counties` / `cities` meta — MIRRORED out of the blob on save so
 *     Ask AI, the match engine, filtering, and public listing display keep working. The
 *     map blob is authoritative; the discrete keys are derived.
 *
 * Host contract: the consuming component must declare the public `$state`, `$counties`,
 * and `$cities` props (all four Hire components + the four Offer components do). Reads are
 * `property_exists`-guarded so the trait is safe to mix into components that omit one.
 *
 * Pairs with {@see \App\Http\Livewire\OfferListing\Concerns\HasImportantPlaces} (the
 * additive Important Places rows, stored in their own `important_places_json` meta key).
 */
trait HasSearchAreas
{
    /** Decoded Location DNA / Search Areas blob handed to the map partial for prefill. */
    public $existingLocationDna = [];

    /** Raw blob JSON bridged from the map widget (`wire:model.defer`). */
    public $location_dna_preferences_json = '';

    /**
     * Load the `location_dna_preferences` blob into the component + partial prefill array,
     * merging legacy discrete `cities` / `zipCodes` / `state` / `counties` meta into the
     * in-memory blob (non-empty guards) so records saved before the map widget tracked those
     * fields still pre-populate their tags. The DB blob is NOT mutated here — only on an
     * explicit save.
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

        // Legacy `zipCodes` meta → in-memory blob `zip_codes` when the blob lacks ZIPs.
        //
        // WHY THIS EXISTS, GIVEN THE CITIES BLOCK ABOVE ALREADY DID THE SAME THING FOR CITIES
        // -----------------------------------------------------------------------------------
        // Cities had this backfill from the start; ZIPs never did. That asymmetry was harmless
        // while the blob was only a prefill source, and stops being harmless the moment a
        // workflow joins `HasGeographyCascade::ZIP_MIRROR_WORKFLOWS`: the cascade hydrates its
        // ZIP selection from `zip_codes` in THIS array and then mirrors the projection back over
        // the host's `$zipCodes` property, which is what the legacy meta is written from. With no
        // ZIPs in the blob the cascade hydrates nothing, projects nothing, and the legacy list is
        // overwritten with `[]` on the next save — silently, since nothing compares the copies.
        //
        // So this is the precondition for enabling any further ZIP-mirroring workflow, not a
        // change of behaviour for the ones running today. It also closes the same gap for Buyer,
        // whose blob is likewise missing ZIPs on records that predate the map widget.
        //
        // IN-MEMORY ONLY, exactly like the cities block: the stored blob is not rewritten here.
        // The value reaches storage only through an explicit save, via the JS bridge that
        // re-serialises this array — so loading a record and navigating away changes nothing.
        //
        // IDEMPOTENT by the emptiness guard: a blob that already carries ZIPs is left alone, so
        // repeated loads converge on the same array and a blob value never loses to a stale
        // mirror. `zip_codes` is the blob's key; `zipCodes` is the legacy meta's. They differ.
        if (empty($ldna['zip_codes'] ?? [])) {
            $legacyZipsRaw = $auction->info('zipCodes');
            if ($legacyZipsRaw) {
                $legacyZips = is_string($legacyZipsRaw)
                    ? (json_decode($legacyZipsRaw, true) ?? [])
                    : (array) $legacyZipsRaw;

                // Cast rather than require `is_string` as the cities filter does, because a ZIP is
                // the one label that legacy data plausibly stored as a NUMBER — `[33701]` rather
                // than `["33701"]`. `GeographySelectionHydrator::labels()` accepts strings only
                // and silently drops anything else, so an int-typed ZIP would not even reach the
                // preserved-labels path that exists to keep unresolvable history. Casting here is
                // what makes this backfill lossless for the records most in need of it.
                $legacyZips = array_values(array_filter(array_map(
                    fn($z) => is_scalar($z) ? trim((string) $z) : '',
                    is_array($legacyZips) ? $legacyZips : []
                ), fn($z) => $z !== ''));

                if (!empty($legacyZips)) {
                    $ldna['zip_codes'] = array_values(array_unique($legacyZips));
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

    /**
     * Persist the Search Areas blob and mirror the discrete `state` / `counties` / `cities`
     * meta out of it (read by Ask AI, matching, filtering, public display). Runs on both the
     * draft and submit paths.
     */
    protected function saveSearchAreas($auction): void
    {
        $this->hydrateDiscreteLocationFromBlob();

        $auction->saveMeta('location_dna_preferences', $this->location_dna_preferences_json);

        if (property_exists($this, 'counties')) {
            $auction->saveMeta('counties', json_encode($this->counties));
        }
        if (property_exists($this, 'state')) {
            $auction->saveMeta('state', $this->state);
        }

        $ldnaDecoded = json_decode($this->location_dna_preferences_json ?? '', true);
        $auction->saveMeta('cities', json_encode($ldnaDecoded['cities'] ?? []));
    }
}
