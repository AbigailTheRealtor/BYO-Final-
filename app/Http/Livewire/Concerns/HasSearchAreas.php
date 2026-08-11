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
     * Merge legacy discrete `cities` / `zipCodes` meta into a decoded Location DNA blob.
     *
     * WHY THIS IS A METHOD RATHER THAN INLINE IN loadSearchAreas()
     * ------------------------------------------------------------
     * Two families need this rule and only one of them can call `loadSearchAreas()`. The Hire
     * components load through that method and get the backfill for free. The Create Offer
     * components decode the blob INLINE — their load paths are interleaved with role-specific
     * hydration and a distinct 9B-2 prefill, so routing them through `loadSearchAreas()` wholesale
     * would change four components' behaviour at once. Extracting the rule lets both callers share
     * one implementation without either adopting the other's surrounding logic.
     *
     * It takes the blob and returns the merged blob rather than mutating `$this`, so a caller can
     * apply it at whatever point in its own load sequence is correct without inheriting an
     * assignment to `$existingLocationDna` it may not want yet.
     *
     * PURE WITH RESPECT TO STORAGE. Nothing here writes. The merged value reaches the database only
     * through an explicit save, via the JS bridge that re-serialises this array — so loading a
     * record and navigating away changes nothing.
     *
     * IDEMPOTENT by the emptiness guards: a blob that already carries a key is left alone, so
     * repeated loads converge and a real blob value never loses to a stale legacy mirror.
     *
     * THE PARAMETER IS DELIBERATELY UNTYPED, AND THE GUARD BELOW IS WHY
     * -----------------------------------------------------------------
     * Every caller builds this argument as `$raw ? (json_decode($raw, true) ?? []) : []`, and that
     * expression does NOT guarantee an array: a stored blob holding a JSON scalar — `5`, `"text"`,
     * `true` — decodes to a scalar, and `?? []` only catches null. Declaring `array $ldna` would
     * turn that into a TypeError at the boundary, i.e. a hard failure on exactly the malformed
     * records this method exists to rescue, and it would do so on the shipped Hire surfaces that
     * reach here through `loadSearchAreas()`.
     *
     * Normalising to `[]` rather than passing the scalar through is what the rest of the stack
     * already does with such a value — `$blob['state'] ?? ''` on an int yields null either way —
     * and it matches the guard in `HasGeographyCascade::applyGeographyCascadeToPayload()`. It also
     * removes a latent fatal that predates this method: the old inline code would raise "Cannot use
     * a scalar value as an array" the moment it tried to assign `$ldna['cities']`.
     *
     * @param  mixed  $ldna  decoded `location_dna_preferences`; anything non-array is treated as {}
     * @return array<string, mixed>  the blob with legacy values merged in
     */
    protected function mergeLegacyGeographyIntoBlob($ldna, $auction): array
    {
        if (! is_array($ldna)) {
            $ldna = [];
        }

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
        // workflow's tab renders the cascade: the cascade hydrates its ZIP selection from
        // `zip_codes` in THIS array and projects all four geography keys back out on save. With no
        // ZIPs in the blob it hydrates nothing and projects an empty list over whatever the blob
        // held — silently, since nothing compares the copies.
        //
        // It is sharper still for a workflow in `HasGeographyCascade::ZIP_MIRROR_WORKFLOWS`, where
        // the projection is also mirrored back over the host's `$zipCodes` property and from there
        // into the legacy meta the property was loaded from.
        //
        // So this is the precondition for rendering the cascade on any surface that owns legacy ZIP
        // state, not a change of behaviour for the ones running today.
        //
        // IDEMPOTENT by the emptiness guard. `zip_codes` is the blob's key; `zipCodes` is the
        // legacy meta's. They differ, and that is not a typo.
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

        return $ldna;
    }

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

        $ldna = $this->mergeLegacyGeographyIntoBlob($ldna, $auction);

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
