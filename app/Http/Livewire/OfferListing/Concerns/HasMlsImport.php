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

    // ─── Modal control ───────────────────────────────────────────────────────

    public function closeImportModal(): void
    {
        $this->showImportModal   = false;
        $this->importPreviewData = [];
        $this->importError       = '';
        $this->importUrlInput    = '';
        $this->importRawText     = '';
        $this->importSuccess     = false;
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

        $this->importError = '';

        /** @var MlsListingImportService $service */
        $service = app(MlsListingImportService::class);
        $result  = $service->import($url, $rawText ?: null);

        if (!$result['success']) {
            $this->importError = $result['error'];
            return;
        }

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

        $this->importPreviewData = [];
        $this->importSuccess     = true;
        $this->showImportModal   = false;

        // Notify the browser so Select2 multi-select elements (which use wire:ignore
        // and therefore are not re-rendered by Livewire's DOM diff) can be rehydrated
        // with the newly applied property values.
        $this->dispatchBrowserEvent('mlsApplied');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
