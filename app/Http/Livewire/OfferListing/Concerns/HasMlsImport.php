<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgeLookupResult;
use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsListingPrefillService;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\LocationDna\LocationDnaGeocodeService;
use Illuminate\Support\Facades\Log;

trait HasMlsImport
{
    public bool   $showImportModal   = false;
    public string $importUrlInput    = '';
    public string $importRawText     = '';
    public array  $importPreviewData = [];
    public string $importError       = '';
    public bool   $importSuccess     = false;

    /**
     * The MLS number typed into the "Import by MLS #" input.
     *
     * Entirely separate from $importUrlInput / $importRawText: this is the
     * Bridge OData lookup, which needs no URL, no pasted text, and no MLS login.
     * The two mechanisms share only the preview/apply machinery below.
     */
    public string $importMlsNumber = '';

    /**
     * True when the applied import came from Bridge AND carried a usable
     * coordinate pair.
     *
     * This is the "do not geocode" signal. Bridge published this property's own
     * coordinate, so sending its address to a geocoder afterwards would spend a
     * request to obtain a worse answer than the one we already hold — and, in
     * the case where the user declined the coordinate rows, would silently
     * substitute a geocoded guess for the MLS's own figure.
     *
     * Both geocoding entry points consult it. See mlsGeocodeAddress() callers.
     */
    public bool $mlsBridgeCoordinatesAvailable = false;

    /**
     * Full parsed MLS payload (JSON) held across the preview → apply lifecycle.
     *
     * Populated in importListingFromUrl() with the raw service result.
     * Consumed (and cleared) in applyImportedFields() to build the snapshot.
     * Livewire public prop so it survives the round-trip between the two steps.
     */
    public string $mlsParsedDataJson = '';

    /**
     * The finalised MLS import snapshot (JSON) for this listing.
     *
     * Built in applyImportedFields() after the user confirms which fields to apply.
     * Persisted immediately to meta if the listing already exists (listingId set),
     * and also kept here so saveAllMetadata() can persist it on the next save if
     * the listing is brand-new (listingId was null at apply time).
     *
     * Call $this->saveSnapshotMeta($auction) at the end of saveAllMetadata() in
     * each component to ensure the snapshot is always written to meta.
     */
    public string $mlsImportSnapshotJson = '';

    /**
     * Which import mechanism the user picked in the modal.
     *
     *   ''      — the chooser is on screen; no mechanism picked yet.
     *   'link'  — the Listing Link / raw-text importer's inputs are on screen.
     *
     * There is deliberately no 'stellar' value. Picking Stellar MLS navigates
     * away to the quick-import page, so there is no state to remember for it —
     * a stored 'stellar' would only ever describe a component the user has
     * already left.
     *
     * The empty default is what keeps the Buyer and Tenant forms working
     * untouched: they open the modal with $set('showImportModal', true) and
     * never call startMlsImport(), so they arrive here with '' and no chooser
     * available, which the blade renders as the inputs — exactly as before.
     */
    public string $importMethod = '';

    // ─── Entry point ─────────────────────────────────────────────────────────

    /**
     * What the "Import from MLS Listing" button on this form does when clicked.
     *
     * ONE BUTTON, THEN THE USER CHOOSES.
     * ----------------------------------
     * Opening the modal is all this does. Which importer runs is the user's
     * decision, taken in the chooser the modal renders — not a decision taken
     * for them here from a flag.
     *
     * That is a correction. This method used to redirect straight to the
     * quick-import page whenever that flow was available, which made the
     * Listing Link importer unreachable on Seller and Landlord: the only button
     * that had ever opened it now went somewhere else. Stellar MLS is an
     * ADDITIONAL door, not a replacement one. Anyone without Stellar
     * credentials still needs the link importer, and it is not gated on Bridge
     * for exactly that reason.
     *
     * With no chooser to show — Buyer, Tenant, or Seller/Landlord with the
     * quick-import flow off — the modal opens directly on the link importer's
     * inputs, which is byte-for-byte what this button did before quick import
     * existed.
     */
    public function startMlsImport()
    {
        $this->importError = '';

        // No chooser worth showing when there is only one thing behind it.
        $this->importMethod = $this->importMethodChoiceAvailable() ? '' : 'link';

        $this->showImportModal = true;

        return null;
    }

    // ─── Choosing an import method ───────────────────────────────────────────

    /**
     * Does this component have more than one import mechanism to offer?
     *
     * Only the quick-import roles do. Everywhere else there is exactly one
     * importer, and a "choose a method" screen listing a single method is a
     * click that teaches the user nothing.
     */
    public function importMethodChoiceAvailable(): bool
    {
        return $this->mlsQuickImportAvailable();
    }

    /**
     * The user picked Stellar MLS — hand them to the quick-import flow.
     *
     * Availability is re-asked here rather than trusted from the render that
     * drew the card. A rendered modal outlives a config change, and the answer
     * that matters is the one that holds at click time. If the flow went away
     * in between, the user lands on the link importer with an explanation
     * rather than on a 404.
     */
    public function chooseStellarMlsImport()
    {
        if (! $this->mlsQuickImportAvailable()) {
            $this->importMethod = 'link';
            $this->importError  = 'Stellar MLS import is not available right now. '
                                . 'You can still import from a public listing link below.';

            return null;
        }

        return redirect()->route($this->mlsQuickImportRouteName());
    }

    /**
     * The user picked Listing Link — show the URL / raw-text inputs.
     *
     * Never gated. This importer reads a public web page; it holds no Bridge
     * credentials and needs none, which is the whole point of offering it
     * beside the Stellar path.
     */
    public function chooseListingLinkImport(): void
    {
        $this->importMethod = 'link';
        $this->importError  = '';
    }

    /**
     * The user picked Create Manually — close the modal and leave them on the
     * form, which is already the manual path and needs nothing set up.
     */
    public function chooseManualListing(): void
    {
        $this->closeImportModal();
    }

    /**
     * Back to the chooser from a picked method.
     *
     * Clears whatever the abandoned method had typed or fetched so returning to
     * it later starts clean; a stale preview from the link importer must not be
     * sitting there if the user comes back and picks Stellar.
     *
     * A no-op where no chooser exists, so the back link cannot strand a Buyer or
     * Tenant on a screen their component never renders.
     */
    public function backToImportMethodChoice(): void
    {
        if (! $this->importMethodChoiceAvailable()) {
            return;
        }

        $this->importMethod      = '';
        $this->importPreviewData = [];
        $this->importError       = '';
        $this->importUrlInput    = '';
        $this->importRawText     = '';
        $this->importMlsNumber   = '';
        $this->mlsParsedDataJson = '';
    }

    /**
     * Is the shortened MLS creation path reachable from this component?
     *
     * Delegates to {@see MlsQuickImportService::availableForRole()} rather than
     * re-reading the two flags here. One reader means the button and the page it
     * leads to cannot disagree — a button that navigates to a 404 is worse than
     * no button.
     */
    public function mlsQuickImportAvailable(): bool
    {
        if ($this->mlsQuickImportRouteName() === null) {
            return false;
        }

        return app(MlsQuickImportService::class)
            ->availableForRole($this->resolveImportRole());
    }

    /**
     * The quick-import route for this component's role, or null if it has none.
     *
     * Buyer and Tenant listings describe search criteria across many areas, not
     * one property, so there is nothing for an MLS number to build and no route
     * exists. Returning null here is what keeps those two forms on the legacy
     * modal regardless of how the flags are set.
     */
    public function mlsQuickImportRouteName(): ?string
    {
        return match ($this->resolveImportRole()) {
            'seller'   => 'offer.listing.seller.quick-import',
            'landlord' => 'offer.listing.landlord.quick-import',
            default    => null,
        };
    }

    // ─── Modal control ───────────────────────────────────────────────────────

    public function closeImportModal(): void
    {
        $this->showImportModal   = false;
        $this->importMethod      = '';
        $this->importPreviewData = [];
        $this->importError       = '';
        $this->importUrlInput    = '';
        $this->importRawText     = '';
        $this->importMlsNumber   = '';
        $this->importSuccess     = false;
        $this->mlsParsedDataJson = '';
    }

    // ─── Feature gate ────────────────────────────────────────────────────────

    /**
     * Whether the Bridge MLS # import is available on this component.
     *
     * Two conditions, both required: the master flag, and a role the feature is
     * actually built for. The blade asks this before rendering the input and
     * importListingByMlsNumber() asks it again before doing anything, so a
     * stale DOM, a replayed request or a hand-crafted Livewire call all land on
     * the same answer as the UI.
     *
     * Never gates the URL/text importer, which is not a Bridge feature and stays
     * available in every environment regardless of this flag.
     */
    public function mlsNumberImportAvailable(): bool
    {
        if (! config('mls_direct_import.prefill_enabled', false)) {
            return false;
        }

        $roles = (array) config('mls_direct_import.prefill_roles', []);

        return in_array($this->resolveImportRole(), $roles, true);
    }

    // ─── Step 1: fetch + parse ────────────────────────────────────────────────

    public function importListingFromUrl(): void
    {
        $url     = trim($this->importUrlInput);
        $rawText = trim($this->importRawText);

        if ($url === '' && $rawText === '') {
            $this->importError = 'Please enter a URL or paste some listing text before clicking Import.';
            return;
        }

        $this->importError       = '';
        $this->mlsParsedDataJson = '';

        /** @var MlsListingImportService $service */
        $service = app(MlsListingImportService::class);
        $result  = $service->import($url, $rawText ?: null);

        if (!$result['success']) {
            $this->importError = $result['error'];
            return;
        }

        // ── Store the full parsed payload for snapshot building at apply time ──
        // We record the source so the snapshot knows where the data came from.
        $source = ($url !== '') ? $url : 'raw_text';
        $this->mlsParsedDataJson = json_encode([
            'source'     => $source,
            'raw_fields' => $result['data'],
        ]);

        $preview = $this->buildImportPreview($result['data']);

        if (empty($preview)) {
            $this->importError = 'No mappable fields were found for this listing type. Try pasting the raw text directly.';
            return;
        }

        $this->importPreviewData = $preview;
        $this->importError       = '';
    }

    // ─── Step 1 (alternate): Bridge lookup by MLS number ─────────────────────

    /**
     * Find a listing in the Bridge/Stellar feed by its human-facing MLS number
     * and stage the factual fields for review.
     *
     * Deliberately produces exactly the same $importPreviewData the URL/text
     * importer produces, and then stops. The form is NOT written here — the user
     * still reviews the table and presses Apply Selected, which is the same
     * confirmation step the other importer has always had. An MLS number is easy
     * to mistype, and a wrong one that silently overwrote a half-completed form
     * would be unrecoverable.
     */
    public function importListingByMlsNumber(): void
    {
        // Re-checked server-side: the blade hides the input when this is false,
        // but a hidden input is not a closed door.
        if (! $this->mlsNumberImportAvailable()) {
            $this->importError = 'MLS # import is not available.';
            return;
        }

        $mlsNumber = trim($this->importMlsNumber);

        if ($mlsNumber === '') {
            $this->importError = 'Please enter an MLS # before clicking Find Listing.';
            return;
        }

        $this->importError       = '';
        $this->mlsParsedDataJson = '';

        /** @var BridgeListingLookupService $lookup */
        $lookup = app(BridgeListingLookupService::class);
        $result = $lookup->lookupByMlsNumber($mlsNumber);

        if (! $result->isFound()) {
            $this->importError = $this->mlsLookupErrorMessage($result, $mlsNumber);
            return;
        }

        /** @var MlsListingPrefillService $prefill */
        $prefill  = app(MlsListingPrefillService::class);
        $prefilled = $prefill->fromCandidate($result->candidate);

        if (! $prefilled['success']) {
            $this->importError = $prefilled['error'];
            return;
        }

        // Source is recorded as the MLS number rather than a URL so the stored
        // snapshot says where these facts actually came from.
        $this->mlsParsedDataJson = json_encode([
            'source'     => 'bridge_mls:' . $mlsNumber,
            'raw_fields' => $prefilled['data'],
        ]);

        // Remember whether Bridge gave us a real coordinate, so the apply step
        // knows not to geocode. MlsListingPrefillService emits the pair only
        // when both halves are present and usable.
        $this->mlsBridgeCoordinatesAvailable =
            isset($prefilled['data']['latitude'], $prefilled['data']['longitude']);

        $preview = $this->buildImportPreview($prefilled['data']);

        if (empty($preview)) {
            $this->importError = 'That MLS listing was found, but none of its details map to this listing type.';
            return;
        }

        $this->importPreviewData = $preview;
        $this->importError       = '';
    }

    /**
     * The message shown for a lookup that did not return a listing.
     *
     * The whole point of BridgeLookupResult is that these two sentences are not
     * interchangeable. "We couldn't find it" tells the user to check the number;
     * "we couldn't connect" tells them to try again later. Saying the first when
     * the second is true sends someone to re-check a number that was correct.
     *
     * Nothing provider-derived is interpolated — no status code, no response
     * body, no configuration value. The diagnostic detail is logged instead.
     */
    private function mlsLookupErrorMessage(BridgeLookupResult $result, string $mlsNumber): string
    {
        if ($result->isUnavailable()) {
            Log::warning('MLS # import: Bridge lookup unavailable', [
                'reason'     => $result->failureReason,
                'role'       => $this->resolveImportRole(),
                'mls_number' => $mlsNumber,
            ]);

            return 'We couldn\'t connect to the MLS data service right now. Please try again in a few minutes.';
        }

        if ($result->isInvalidInput()) {
            return 'Please enter an MLS # before clicking Find Listing.';
        }

        return 'We couldn\'t find a listing matching that MLS #. Please check the number and try again.';
    }

    // ─── Shared preview builder ──────────────────────────────────────────────

    /**
     * Turn a canonical-key array into the preview rows the modal renders.
     *
     * Shared verbatim by both importers, which is the point: the URL/text path
     * and the Bridge path differ only in how the canonical data was obtained,
     * and everything after that — what is shown, what is pre-checked, how an
     * existing value is flagged for overwrite — must not be able to drift apart
     * between them.
     *
     * Keys with no field-map entry for this role, or whose target property does
     * not exist on this component, are skipped. That is what keeps record
     * handles like mls_listing_key out of the review table while leaving them in
     * the underlying data for the apply step to persist.
     *
     * A third skip applies to type-gated fields: a Livewire property exists for
     * every property type, but the blade only renders an input for some of them,
     * so property_exists() alone would happily offer "Pool" while the user is
     * creating a Vacant Land listing and write a value into state they can never
     * see. See MlsFieldMap::propertyTypeApplicability().
     *
     * @param  array<string,mixed> $parsedData
     * @return list<array<string,mixed>>
     */
    private function buildImportPreview(array $parsedData): array
    {
        $role      = $this->resolveImportRole();
        $fieldMap  = MlsFieldMap::forRole($role);
        $labels    = MlsFieldMap::fieldLabels();
        $typeScope = MlsFieldMap::propertyTypeApplicability($role);

        // Whatever the user has chosen so far. Empty is the normal state when the
        // modal is opened before the type is picked, and it must stay permissive:
        // suppressing rows because nothing is selected yet would silently shrink
        // the import for anyone who imports first and classifies second.
        $currentType = property_exists($this, 'property_type')
            ? trim((string) $this->property_type)
            : '';

        $preview = [];

        foreach ($parsedData as $canonicalKey => $value) {
            if ($canonicalKey === 'listing_type_hint' || !isset($fieldMap[$canonicalKey])) {
                continue;
            }

            $propNameRaw = $fieldMap[$canonicalKey];
            $isArray     = str_starts_with($propNameRaw, '*');
            $propName    = $isArray ? ltrim($propNameRaw, '*') : $propNameRaw;

            // Only include fields that actually exist as public properties
            if (!property_exists($this, $propName)) {
                continue;
            }

            // …and whose input this property type actually renders. A key absent
            // from the scope map is applicable everywhere, so every canonical key
            // that predates the map behaves exactly as it did before.
            if (
                $currentType !== ''
                && isset($typeScope[$canonicalKey])
                && !in_array($currentType, $typeScope[$canonicalKey], true)
            ) {
                continue;
            }

            $existingValue = $this->{$propName};
            $hasExisting   = is_array($existingValue)
                ? !empty($existingValue)
                : ($existingValue !== '' && $existingValue !== null);

            $preview[] = [
                'canonical_key'      => $canonicalKey,
                'prop_name'          => $propName,
                'label'              => $labels[$canonicalKey] ?? ucfirst(str_replace('_', ' ', $canonicalKey)),
                'value'              => is_array($value) ? implode(', ', $value) : (string) $value,
                'is_array_prop'      => $isArray,
                'has_existing_value' => $hasExisting,
                'checked'            => true,
            ];
        }

        return $preview;
    }

    // ─── Step 2: apply selected fields ───────────────────────────────────────

    /**
     * @param  string[] $selected    Canonical field keys the user checked.
     * @param  string[] $overrideKeys  Subset of $selected for fields that already have a value.
     */
    public function applyImportedFields(array $selected, array $overrideKeys = []): void
    {
        $role     = $this->resolveImportRole();
        $fieldMap = MlsFieldMap::forRole($role);

        // Build a lookup from the preview data (which already resolved prop names)
        $previewByKey = [];
        foreach ($this->importPreviewData as $row) {
            $previewByKey[$row['canonical_key']] = $row;
        }

        // ── Pre-apply: capture all data needed for the snapshot ───────────────
        $parsedMeta      = $this->mlsParsedDataJson !== ''
            ? json_decode($this->mlsParsedDataJson, true)
            : [];
        $allParsedFields = $parsedMeta['raw_fields'] ?? [];
        $source          = $parsedMeta['source']     ?? 'unknown';

        $mappedFormFields   = [];
        $unsupportedFields  = [];

        foreach ($allParsedFields as $canonicalKey => $value) {
            if ($canonicalKey === 'listing_type_hint') {
                continue;
            }

            if (!isset($fieldMap[$canonicalKey])) {
                // Not in the field map for this role at all
                $unsupportedFields[$canonicalKey] = $value;
                continue;
            }

            $propNameRaw = $fieldMap[$canonicalKey];
            $propName    = ltrim($propNameRaw, '*');

            if (!property_exists($this, $propName)) {
                // In the map but no matching Livewire property
                $unsupportedFields[$canonicalKey] = $value;
            } else {
                $mappedFormFields[$canonicalKey] = $value;
            }
        }

        foreach ($selected as $canonicalKey) {
            if (!isset($previewByKey[$canonicalKey])) {
                continue;
            }

            $row      = $previewByKey[$canonicalKey];
            $propName = $row['prop_name'];
            $isArray  = $row['is_array_prop'];
            $rawValue = $row['value'];

            // Landlord residential routing: 'terms_of_lease' canonical key carries
            // DURATION values from MLS residential exports ('Month-to-Month', '1 Year',
            // '6 Months', etc.).  These belong in the 'desired_lease_length' prop whose
            // blade options are $residential_lease_term_options / $Commercial_lease_term_options.
            // The 'terms_of_lease' prop itself is for COMMERCIAL lease TYPES (Gross Lease,
            // Net Lease, etc.) and is only relevant when property_type = 'Commercial Property'.
            if ($canonicalKey === 'terms_of_lease' && $role === 'landlord'
                && ($this->property_type ?? '') === 'Residential Property'
                && property_exists($this, 'desired_lease_length')) {
                $propName = 'desired_lease_length';
                $isArray  = true;
            }

            if (!property_exists($this, $propName)) {
                continue;
            }

            // Seller-specific: 'furnished' MLS value merges into building_features
            // (not replaces), since seller stores furnishing status inside that JSON
            // array. This check MUST appear before the hasExisting guard so that an
            // already-populated building_features array is not skipped — we always
            // want to merge, never to replace or skip. 'Unfurnished' is intentionally
            // excluded because absence of the value implies unfurnished.
            if ($canonicalKey === 'furnished' && $propName === 'building_features') {
                // The rule itself lives in MlsFactVocabulary because the
                // quick-import writer needs the identical behaviour. Two
                // lookalike copies of "which furnishing values earn a feature
                // label, and does Unfurnished count" is exactly the drift this
                // branch would otherwise start.
                $this->building_features = \App\Support\Listing\MlsFactVocabulary::mergeFurnishedFeature(
                    $this->building_features,
                    $rawValue,
                );

                continue;
            }

            // Role-specific property_type normalization.
            // MLS text emits verbose forms like "Residential Property", "Commercial Property",
            // "Business Opportunity", "Income/Multifamily", "Vacant Land Sale".
            // Seller / buyer / tenant forms store the SHORT form without a " Property" suffix:
            //   'Residential', 'Commercial', 'Business', 'Income', 'Vacant Land'.
            // Landlord form options ARE the full forms: 'Residential Property', 'Commercial Property',
            // so MLS-captured values already match landlord — we still normalise edge cases.
            if ($canonicalKey === 'property_type') {
                $rawValue = static::normalizePropertyTypeForRole($rawValue, $role);
            }

            $existingValue = $this->{$propName};
            $hasExisting   = is_array($existingValue)
                ? !empty($existingValue)
                : ($existingValue !== '' && $existingValue !== null);

            // Skip already-filled fields unless the user explicitly confirmed override
            if ($hasExisting && !in_array($canonicalKey, $overrideKeys, true)) {
                continue;
            }

            if ($isArray) {
                // Split comma-separated string into array values
                $items = array_filter(array_map('trim', explode(',', $rawValue)));
                $this->{$propName} = array_values($items);
            } else {
                $this->{$propName} = $rawValue;
            }
        }

        // ── Build and store mls_address_raw ──────────────────────────────────
        // Assemble a raw address string from any address-related fields that were
        // present in the parsed data (regardless of whether the user applied them).
        $mls_address_raw = $this->buildMlsAddressRaw($allParsedFields);

        // ── Build the mls_import_snapshot ────────────────────────────────────
        $snapshot = [
            'imported_at'       => now()->toIso8601String(),
            'source'            => $source,
            'raw_fields'        => $allParsedFields,
            // TODO: normalized_fields is currently IDENTICAL to raw_fields.
            // It is a placeholder for a future normalization pass that should apply
            // platform crosswalk transformations to the raw parsed values before storing.
            // The following normalizations already happen DURING applyImportedFields()
            // (written directly to Livewire props) but are NOT yet captured here:
            //   1. property_type → normalizePropertyTypeForRole() (e.g. "Residential Property" → "Residential" for seller)
            //   2. terms_of_lease → re-routed to desired_lease_length for landlord + Residential Property
            //   3. furnished → merged into building_features array for seller
            // Future work: run these crosswalks on $allParsedFields BEFORE the apply
            // loop and store the result as normalized_fields, keeping raw_fields untouched.
            'normalized_fields' => $allParsedFields,
            'mapped_form_fields'  => $mappedFormFields,
            'unsupported_fields'  => $unsupportedFields,
            'mls_address_raw'     => $mls_address_raw,
        ];

        $this->mlsImportSnapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // ── Persist immediately if the listing already exists ─────────────────
        $listingId = property_exists($this, 'listingId') ? $this->listingId : null;
        if ($listingId) {
            $model = $this->resolveMlsModel($role, $listingId);
            if ($model) {
                $model->saveMeta('mls_import_snapshot', $this->mlsImportSnapshotJson);
                if ($mls_address_raw !== '') {
                    $model->saveMeta('mls_address_raw', $mls_address_raw);
                }
                $this->saveMlsListingKeyMeta($model, $allParsedFields);
            }
        }

        // ── Server-side geocoding after MLS import (seller / landlord only) ───
        // Requires an existing listing ID so coordinates can be persisted to EAV
        // meta immediately and avoid stale-state on the form.  On new/draft listings
        // (no ID yet) coordinates will be captured via Google Places autocomplete.
        //
        // A Bridge import that carried coordinates skips this entirely: the MLS
        // published this property's own position, and geocoding its address would
        // spend a request to replace a known coordinate with an inferred one.
        if ($listingId
            && ! $this->mlsBridgeCoordinatesAvailable
            && in_array($role, ['seller', 'landlord'])
            && property_exists($this, 'property_lat')
            && ($this->property_lat === '' || $this->property_lat === null)
        ) {
            $this->mlsGeocodeAddress($role, $listingId);
        }

        $this->importPreviewData = [];
        $this->mlsParsedDataJson = '';
        $this->importSuccess     = true;
        $this->showImportModal   = false;

        // Notify the browser so Select2 multi-select elements (which use wire:ignore
        // and therefore are not re-rendered by Livewire's DOM diff) can be rehydrated
        // with the newly applied property values.
        $this->dispatchBrowserEvent('mlsApplied');
    }

    // ─── Snapshot persistence helper (call from saveAllMetadata) ─────────────

    /**
     * Persist the MLS import snapshot and raw address to the listing's meta table.
     *
     * Call $this->saveSnapshotMeta($auction) at the end of saveAllMetadata() in
     * each OfferListing component.  This ensures that:
     *   - listings imported before their first save (listingId was null at apply time)
     *     still get the snapshot written when the draft/listing is eventually saved.
     *   - re-saves after import never overwrite a snapshot with an empty string.
     */
    protected function saveSnapshotMeta($auction): void
    {
        if ($this->mlsImportSnapshotJson !== '') {
            $auction->saveMeta('mls_import_snapshot', $this->mlsImportSnapshotJson);

            // Also persist mls_address_raw from inside the snapshot
            $decoded = json_decode($this->mlsImportSnapshotJson, true);
            $raw     = $decoded['mls_address_raw'] ?? '';
            if ($raw !== '') {
                $auction->saveMeta('mls_address_raw', $raw);
            }

            // The RESO ListingKey, for a listing whose import happened before it
            // had an ID. Same write as the apply-time path; this is the branch
            // that catches a brand-new draft.
            $this->saveMlsListingKeyMeta($auction, $decoded['raw_fields'] ?? []);
        }

        // ── Save-time geocoding fallback ──────────────────────────────────────
        // When an MLS import was applied before the listing had an ID (new draft),
        // the applyImportedFields geocoding path was skipped.  Now that the model
        // has a persisted ID, attempt geocoding if property_lat is still empty.
        $this->mlsGeocodeSaveTimeFallback($auction);
    }

    /**
     * Persist the Bridge record's RESO ListingKey as listing meta.
     *
     * WHY THIS ONE LINE MATTERS
     * -------------------------
     * {@see \App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter}
     * — rung 2 of the coordinate ladder that already runs on every Seller and
     * Landlord save — looks a property up in `bridge_properties` by
     * `listing_key` and by nothing else, deliberately: matching a feed record by
     * address similarity would attach some other property's coordinate to this
     * listing while reporting success.
     *
     * Before this feature nothing in production ever wrote that key, so the rung
     * returned `no_mls_listing_key` on every real save and the ladder fell
     * through to the network geocoder. Writing it here is what activates a rung
     * that has been present, correct and silent all along — no new coordinate
     * mechanism, no second persistence path, one meta key.
     *
     * Only ever written from a Bridge import; the URL/text parser has no
     * ListingKey to offer, and an absent key correctly leaves the rung silent.
     *
     * @param  array<string,mixed> $parsedFields canonical-key data from the import
     */
    private function saveMlsListingKeyMeta(object $model, array $parsedFields): void
    {
        $listingKey = trim((string) ($parsedFields['mls_listing_key'] ?? ''));

        if ($listingKey === '' || !method_exists($model, 'saveMeta')) {
            return;
        }

        $model->saveMeta('mls_listing_key', $listingKey);
    }

    /**
     * Geocode the address at save time if: the listing's MLS import populated address
     * fields but property_lat has not yet been set (e.g. new draft, no ID at import
     * time).  Writes directly to the model meta so the value is persisted atomically.
     *
     * Never runs for a Bridge import that carried coordinates. In that case the
     * listing also now carries `mls_listing_key`, so the coordinate ladder's
     * Bridge rung resolves the MLS's own published coordinate on this very save
     * — reaching for a geocoder as well would be spending a request to second-
     * guess the authoritative source.
     */
    private function mlsGeocodeSaveTimeFallback(object $auction): void
    {
        try {
            if ($this->mlsBridgeCoordinatesAvailable) {
                return;
            }

            $role = $this->resolveImportRole();
            if (!in_array($role, ['seller', 'landlord'])) {
                return;
            }

            // Only geocode when property_lat is missing on both the model and the component.
            $existingLat = data_get($auction->get, 'property_lat');
            if (!empty($existingLat)) {
                return;
            }
            if (!property_exists($this, 'property_lat') || !empty($this->property_lat)) {
                return;
            }

            // Geocode with the real, persisted auction ID.
            $this->mlsGeocodeAddress($role, $auction->id);

            // Persist immediately (saveAllMetadata may have already run its meta saves).
            if (!empty($this->property_lat)) {
                $auction->saveMeta('property_lat', $this->property_lat);
            }
            if (!empty($this->property_lng)) {
                $auction->saveMeta('property_lng', $this->property_lng);
            }
        } catch (\Throwable $e) {
            // Silent failure — geocoding is best-effort.
        }
    }

    // ─── Geocoding helper ─────────────────────────────────────────────────────

    /**
     * After MLS address fields are applied to Livewire props, attempt to geocode
     * the address and write property_lat / property_lng / google_place_id back to
     * the component.  If the listing already has an ID, also persists immediately
     * to the model's EAV meta so the values survive without requiring a manual save.
     *
     * Runs only when property_lat is empty (i.e. Google Places was not used) and
     * the role is seller or landlord.  Failures are silent — geocoding is best-effort.
     */
    private function mlsGeocodeAddress(string $role, ?int $listingId): void
    {
        try {
            $address = '';
            $city    = '';
            $state   = '';
            $county  = '';
            $zip     = '';

            if (property_exists($this, 'address'))         $address = (string) ($this->address ?? '');
            if (property_exists($this, 'property_city'))   $city    = (string) ($this->property_city ?? '');
            if (property_exists($this, 'property_state'))  $state   = (string) ($this->property_state ?? '');
            if (property_exists($this, 'property_county')) $county  = (string) ($this->property_county ?? '');
            if (property_exists($this, 'property_zip'))    $zip     = (string) ($this->property_zip ?? '');

            // When individual city/state fields are blank (e.g. MLS import mapped only the
            // raw address string), try to parse them from mls_address_raw.  The raw string
            // is assembled as "Street, City, State ZIP" by buildMlsAddressRaw().
            if ($city === '' || $state === '') {
                $rawAddress = '';
                if (property_exists($this, 'mls_preview') && is_array($this->mls_preview)) {
                    $rawAddress = (string) ($this->mls_preview['mls_address_raw'] ?? '');
                }
                if ($rawAddress === '' && $listingId) {
                    $mlsFallbackModel = $this->resolveMlsModel($role, $listingId);
                    if ($mlsFallbackModel && method_exists($mlsFallbackModel, 'getMeta')) {
                        $rawAddress = (string) ($mlsFallbackModel->getMeta('mls_address_raw') ?? '');
                    }
                }
                if ($rawAddress !== '') {
                    // "123 Main St, Tampa, FL 33601" or "Tampa, FL 33601" or "Tampa, FL"
                    if (preg_match('/,\s*([^,]+),\s*([A-Z]{2})\s+([\d]{5}(?:-\d{4})?)\s*$/i', $rawAddress, $m)) {
                        if ($city  === '') $city  = trim($m[1]);
                        if ($state === '') $state = strtoupper(trim($m[2]));
                        if ($zip   === '') $zip   = trim($m[3]);
                    } elseif (preg_match('/,\s*([^,]+),\s*([A-Z]{2})\s*$/i', $rawAddress, $m)) {
                        if ($city  === '') $city  = trim($m[1]);
                        if ($state === '') $state = strtoupper(trim($m[2]));
                    }
                }
            }

            // Last resort: if address is blank but city/state were parsed, skip.
            // If address contains commas (full address in one field), still pass it through.
            if ($address === '' || $city === '' || $state === '') {
                return;
            }

            $listingTypeMap = [
                'seller'   => 'seller_agent_auction',
                'landlord' => 'landlord_agent_auction',
            ];
            $listingType = $listingTypeMap[$role] ?? null;
            if ($listingType === null) {
                return;
            }

            $service = app(LocationDnaGeocodeService::class);
            $result  = $service->geocodeForListing($listingType, (int) $listingId, [
                'address' => $address,
                'city'    => $city,
                'state'   => $state,
                'county'  => $county,
                'zip'     => $zip,
            ]);

            if (!$result['success']) {
                return;
            }

            $lat = $result['lat'] !== null ? (string) $result['lat'] : '';
            $lng = $result['lng'] !== null ? (string) $result['lng'] : '';

            if ($lat === '' || $lng === '') {
                return;
            }

            if (property_exists($this, 'property_lat')) $this->property_lat = $lat;
            if (property_exists($this, 'property_lng')) $this->property_lng = $lng;

            // Google Geocoding API returns place_id on each result; store it when available.
            $placeId = $result['place_id'] ?? null;
            if ($placeId && property_exists($this, 'google_place_id') && empty($this->google_place_id)) {
                $this->google_place_id = $placeId;
            }

            if ($listingId) {
                $model = $this->resolveMlsModel($role, $listingId);
                if ($model) {
                    $model->saveMeta('property_lat', $lat);
                    $model->saveMeta('property_lng', $lng);
                    if ($placeId) {
                        $model->saveMeta('google_place_id', $placeId);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silent failure — geocoding is best-effort; never break the import flow.
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Assemble a human-readable "Street, City, State ZIP" string from parsed MLS
     * address fields.  Returns an empty string if no address components are present.
     *
     * The URL/text parser emits a street-only `address`, so the city/state/ZIP
     * tail is appended to it. Bridge instead emits RESO `UnparsedAddress`, which
     * is already the whole line — appending to that would produce
     * "123 Main St, Tampa, FL 33601, Tampa, FL 33601". addressAlreadyContains()
     * suppresses the duplicate tail without attempting to take the address
     * apart; nothing here parses or splits an address.
     */
    private function buildMlsAddressRaw(array $parsedFields): string
    {
        $parts   = [];
        $address = trim((string) ($parsedFields['address'] ?? ''));

        if ($address !== '') {
            $parts[] = $address;
        }

        $city  = trim((string) ($parsedFields['city']  ?? ''));
        $state = trim((string) ($parsedFields['state'] ?? ''));
        $zip   = trim((string) ($parsedFields['zip']   ?? ''));

        // Already a complete address line — leave it exactly as the feed gave it.
        if ($address !== ''
            && $city !== ''
            && $this->addressAlreadyContains($address, $city)
            && ($state === '' || $this->addressAlreadyContains($address, $state))
        ) {
            return $address;
        }

        $cityStateZip = implode(', ', array_filter([$city, $state]));
        if ($zip !== '') {
            $cityStateZip = $cityStateZip !== '' ? "$cityStateZip $zip" : $zip;
        }

        if ($cityStateZip !== '') {
            $parts[] = $cityStateZip;
        }

        return implode(', ', $parts);
    }

    /**
     * Case-insensitive whole-word containment test.
     *
     * Word-bounded on purpose: a substring test would find "FL" inside
     * "FLAGLER" and conclude a state was already present when it was not.
     */
    private function addressAlreadyContains(string $address, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return false;
        }

        return (bool) preg_match(
            '/(?<![A-Za-z0-9])' . preg_quote($needle, '/') . '(?![A-Za-z0-9])/i',
            $address
        );
    }

    /**
     * Resolve the underlying Eloquent model instance for a given role + listingId.
     * Returns null if listingId is null or the record cannot be found.
     */
    private function resolveMlsModel(string $role, int|string $listingId): ?object
    {
        $modelClass = match ($role) {
            'seller'   => \App\Models\SellerAgentAuction::class,
            'landlord' => \App\Models\LandlordAgentAuction::class,
            'buyer'    => \App\Models\BuyerAgentAuction::class,
            'tenant'   => \App\Models\TenantAgentAuction::class,
            default    => null,
        };

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::find($listingId);
    }

    /**
     * Map a raw MLS "Property Type" string to the canonical value expected by
     * the given role's form.
     *
     * Landlord form options: 'Residential Property', 'Commercial Property'
     * Seller / buyer / tenant options: 'Residential', 'Commercial', 'Business',
     *                                   'Income', 'Vacant Land'
     */
    /**
     * Translate a source property type into this role's BYO vocabulary.
     *
     * Delegates to {@see \App\Support\Listing\PropertyTypeVocabulary}, which
     * MLS Quick Import also uses. The rules are unchanged — they were moved
     * there so both import paths translate identically, after quick import was
     * found carrying raw RESO values ("Residential Lease") into Blade conditions
     * that compare against BYO words ("Residential Property") and silently
     * hiding whole conditional sections as a result.
     */
    private static function normalizePropertyTypeForRole(string $value, string $role): string
    {
        return \App\Support\Listing\PropertyTypeVocabulary::forRole($value, $role);
    }

    private function resolveImportRole(): string
    {
        $userType = property_exists($this, 'user_type') ? (string) $this->user_type : '';

        if (in_array($userType, ['seller', 'buyer', 'landlord', 'tenant'], true)) {
            return $userType;
        }

        // Fallback: derive from class name
        $class = class_basename(static::class);
        foreach (['Seller', 'Buyer', 'Landlord', 'Tenant'] as $role) {
            if (str_contains($class, $role)) {
                return strtolower($role);
            }
        }

        return 'seller';
    }
}
