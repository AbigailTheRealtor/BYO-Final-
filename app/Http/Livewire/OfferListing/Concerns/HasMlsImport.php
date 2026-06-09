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

            if (!property_exists($this, $propName)) {
                continue;
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
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
