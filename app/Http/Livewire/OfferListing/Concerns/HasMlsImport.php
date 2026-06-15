<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsFieldMap;

trait HasMlsImport
{
    public bool   $showImportModal   = false;
    public string $importUrlInput    = '';
    public string $importRawText     = '';
    public array  $importPreviewData = [];
    public string $importError       = '';
    public bool   $importSuccess     = false;

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

    // ─── Modal control ───────────────────────────────────────────────────────

    public function closeImportModal(): void
    {
        $this->showImportModal   = false;
        $this->importPreviewData = [];
        $this->importError       = '';
        $this->importUrlInput    = '';
        $this->importRawText     = '';
        $this->importSuccess     = false;
        $this->mlsParsedDataJson = '';
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

        $role     = $this->resolveImportRole();
        $fieldMap = MlsFieldMap::forRole($role);
        $labels   = MlsFieldMap::fieldLabels();

        $preview = [];

        foreach ($result['data'] as $canonicalKey => $value) {
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

        if (empty($preview)) {
            $this->importError = 'No mappable fields were found for this listing type. Try pasting the raw text directly.';
            return;
        }

        $this->importPreviewData = $preview;
        $this->importError       = '';
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
                $furnishedVal = strtolower(trim($rawValue));
                if (in_array($furnishedVal, ['furnished', 'turnkey', 'partial', 'negotiable'], true)) {
                    $label    = ucfirst($furnishedVal);
                    $existing = is_array($this->building_features) ? $this->building_features : [];
                    if (!in_array($label, $existing, true)) {
                        $this->building_features = array_merge($existing, [$label]);
                    }
                }
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
            }
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
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Assemble a human-readable "Street, City, State ZIP" string from parsed MLS
     * address fields.  Returns an empty string if no address components are present.
     */
    private function buildMlsAddressRaw(array $parsedFields): string
    {
        $parts = [];

        if (!empty($parsedFields['address'])) {
            $parts[] = trim((string) $parsedFields['address']);
        }

        $city  = trim((string) ($parsedFields['city']  ?? ''));
        $state = trim((string) ($parsedFields['state'] ?? ''));
        $zip   = trim((string) ($parsedFields['zip']   ?? ''));

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
    private static function normalizePropertyTypeForRole(string $value, string $role): string
    {
        $v     = trim($value);
        $lower = strtolower($v);

        if ($role === 'landlord') {
            // Landlord blade uses "Residential Property" / "Commercial Property".
            // MLS output already matches, but handle short-form edge cases too.
            if (str_contains($lower, 'commercial'))  return 'Commercial Property';
            if (str_contains($lower, 'residential')) return 'Residential Property';
            return $v;
        }

        // Seller, buyer, tenant: use short-form values (no " Property" suffix).
        if (str_contains($lower, 'residential')   || str_contains($lower, 'single family')
            || str_contains($lower, 'condominium') || str_contains($lower, 'condo')
            || str_contains($lower, 'townhome')    || str_contains($lower, 'townhouse')
            || str_contains($lower, 'mobile home')) {
            return 'Residential';
        }

        // "Business Opportunity" → 'Business' (must come before 'commercial' check
        // because some MLS exports say "Business, Commercial").
        if (str_contains($lower, 'business')) {
            return 'Business';
        }

        if (str_contains($lower, 'commercial')) {
            return 'Commercial';
        }

        if (str_contains($lower, 'income') || str_contains($lower, 'multifamily')
            || str_contains($lower, 'multi-family') || str_contains($lower, 'multi family')) {
            return 'Income';
        }

        if (str_contains($lower, 'vacant') || str_contains($lower, 'land')) {
            return 'Vacant Land';
        }

        return $v; // already-normalized or unrecognised — pass through
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
