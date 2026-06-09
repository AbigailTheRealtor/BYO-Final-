<?php

namespace App\Services\ListingImport;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;

/**
 * Generates a machine-readable MLS import coverage report.
 *
 * The universe of rows is fieldInventory() — a complete, form-scoped list of
 * every MLS field across all 7 Stellar MLS forms (Residential, Rental, Vacant
 * Land, Income, Commercial Sale, Commercial Lease, Business Opportunity).
 *
 * Entries with canonical_key = null appear as unmapped/unsafe rows, surfacing
 * commercial, vacant-land, income, and business-specific gaps that would be
 * invisible if the universe were derived only from parsedKeys ∪ fieldMapKeys.
 *
 * All downstream columns are derived at report-generation time:
 *   • Parsed           — live grep of MlsListingImportService::parseFields() source
 *   • Current Mapping  — MlsFieldMap::forRole() (live)
 *   • Property Exists  — property_exists() against Livewire component classes (live)
 *   • Form Field Exists— wire:model grep across Create/Edit tab blade files (live)
 *   • Safe To Import   — Parsed=Y AND PropExists=Y AND FormField=Y per role
 *
 * Required column contract:
 *   MLS Form | MLS Section | MLS Field Label | Canonical Import Key |
 *   Current Mapping | Target Property | Property Exists (Y/N) |
 *   Form Field Exists (Y/N) | Safe To Import (Y/N) |
 *   Normalization Required (Y/N) | Notes
 */
class MlsCoverageReporter
{
    private const PARSER_SRC = __DIR__ . '/MlsListingImportService.php';

    private const BLADE_DIRS = [
        'seller'   => 'offer-seller-tabs',
        'landlord' => 'offer-landlord-tabs',
        'buyer'    => 'offer-buyer-tabs',
        'tenant'   => 'offer-tenant-tabs',
    ];

    private const COMPONENT_CLASSES = [
        'seller'   => SellerOfferListing::class,
        'landlord' => LandlordOfferListing::class,
        'buyer'    => BuyerOfferListing::class,
        'tenant'   => TenantOfferListing::class,
    ];

    // ─── Entry point ─────────────────────────────────────────────────────────

    /**
     * Generate the coverage report and write it to $outputPath.
     *
     * @param  string|null $outputPath  Absolute path to write the .md file.
     *                                  Defaults to storage/logs/mls_import_coverage_report.md
     * @return string  The path the report was written to.
     */
    public static function generate(?string $outputPath = null): string
    {
        $outputPath = $outputPath ?? storage_path('logs/mls_import_coverage_report.md');

        $parsedKeys    = self::deriveParsedKeys();
        $fieldMaps     = self::deriveFieldMaps();
        $bladeBindings = self::deriveBladeBindings();
        $baseKeys      = MlsFieldMap::universalBaseKeys();

        $rows    = self::buildRows($parsedKeys, $fieldMaps, $bladeBindings, $baseKeys);
        $content = self::renderMarkdown($rows);

        file_put_contents($outputPath, $content);

        return $outputPath;
    }

    // ─── Source derivation ───────────────────────────────────────────────────

    /**
     * Parse the actual parseFields() source to extract every canonical key
     * that the parser writes to $data. Never stale — reads live source code.
     */
    private static function deriveParsedKeys(): array
    {
        $src = file_get_contents(self::PARSER_SRC);

        preg_match_all('/\$data\[\'([a-z_]+)\'\]\s*=/', $src, $m);
        $keys = array_values(array_unique($m[1] ?? []));

        // Signal-only internal keys consumed but stripped before export
        $internalOnly = ['rental_rate_type'];

        return array_values(array_filter($keys, fn ($k) => !in_array($k, $internalOnly, true)));
    }

    /**
     * Build field maps for all four roles and pre-compute property existence
     * via property_exists() against the actual Livewire component classes.
     *
     * Returns: ['seller' => ['canonical_key' => ['prop' => '...', 'exists' => bool]], ...]
     */
    private static function deriveFieldMaps(): array
    {
        $result = [];

        foreach (self::COMPONENT_CLASSES as $role => $class) {
            $rawMap        = MlsFieldMap::forRole($role);
            $result[$role] = [];

            foreach ($rawMap as $canonicalKey => $propName) {
                $cleanProp = ltrim($propName, '*');
                $result[$role][$canonicalKey] = [
                    'prop'   => $cleanProp,
                    'raw'    => $propName,
                    'exists' => property_exists($class, $cleanProp),
                ];
            }
        }

        return $result;
    }

    /**
     * Glob all tab blade files for each role and extract every wire:model binding.
     * Reads live blade files — never stale.
     *
     * Returns: ['seller' => ['prop1', 'prop2', ...], ...]
     */
    private static function deriveBladeBindings(): array
    {
        $bindings = [];

        foreach (self::BLADE_DIRS as $role => $dir) {
            $deepFiles = glob(resource_path("views/livewire/offer-listing/{$dir}/**/*.blade.php")) ?: [];
            $flatFiles = glob(resource_path("views/livewire/offer-listing/{$dir}/*.blade.php"))   ?: [];
            $files     = array_unique(array_merge($deepFiles, $flatFiles));

            $props = [];
            foreach ($files as $file) {
                $content = file_get_contents($file);
                preg_match_all('/wire:model(?:\.\w+)*="([^"]+)"/', $content, $m);
                $props = array_merge($props, $m[1] ?? []);
            }

            $bindings[$role] = array_values(array_unique($props));
        }

        return $bindings;
    }

    // ─── Row construction ─────────────────────────────────────────────────────

    /**
     * Build one report row per fieldInventory() entry.
     *
     * The universe is fieldInventory() — a complete, form-scoped field list.
     * Entries with canonical_key = null appear as unmapped/unsafe rows.
     * This ensures commercial, vacant-land, income, and business-specific
     * fields that have no parser branch are still visible in the report.
     *
     * Each row now includes two additional derived columns:
     *   • previewed          — Y if the canonical key is in the universal base list;
     *                          these fields populate the preview without requiring
     *                          property type to be selected first.
     *   • reason_not_mapped  — short diagnostic string for every Safe=N row;
     *                          empty string for Safe=Y rows.
     *
     * @param  string[] $baseKeys  From MlsFieldMap::universalBaseKeys().
     */
    private static function buildRows(
        array $parsedKeys,
        array $fieldMaps,
        array $bladeBindings,
        array $baseKeys = []
    ): array {
        $rows = [];

        foreach (self::fieldInventory() as $item) {
            $canonicalKey = $item['canonical_key'];
            $normRequired = $item['norm_required'] ?? false;
            $itemNotes    = $item['notes'] ?? '';

            // ── No parser branch exists for this MLS field ───────────────────
            if ($canonicalKey === null) {
                $rows[] = [
                    'mls_form'          => $item['form'],
                    'mls_section'       => $item['section'],
                    'mls_field_label'   => $item['mls_label'],
                    'canonical_key'     => null,
                    'current_mapping'   => '—',
                    'target_property'   => '—',
                    'property_exists'   => '—',
                    'form_field_exists' => '—',
                    'safe'              => 'N',
                    'norm_required'     => 'N',
                    'previewed'         => 'N',
                    'reason_not_mapped' => 'missing_from_parser',
                    'notes'             => $itemNotes ?: 'MLS field present on form — no parser branch; no app field',
                ];
                continue;
            }

            // ── Has a canonical key — derive live columns ─────────────────────
            $isParsed = in_array($canonicalKey, $parsedKeys, true);

            $mappingParts    = [];
            $targetParts     = [];
            $propExistsBits  = [];
            $formFieldBits   = [];
            $safeRoles       = [];

            foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
                $initial = strtoupper($role[0]);
                $roleMap = $fieldMaps[$role];

                if (!isset($roleMap[$canonicalKey])) {
                    continue;
                }

                $propName   = $roleMap[$canonicalKey]['prop'];
                $propExists = $roleMap[$canonicalKey]['exists'];
                $formExists = in_array($propName, $bladeBindings[$role] ?? [], true);

                $mappingParts[]   = "{$initial}:{$propName}";
                $targetParts[]    = $propName;
                $propExistsBits[] = "{$initial}:" . ($propExists ? 'Y' : 'N');
                $formFieldBits[]  = "{$initial}:" . ($formExists ? 'Y' : 'N');

                if ($isParsed && $propExists && $formExists) {
                    $safeRoles[] = $initial;
                }
            }

            $currentMapping = $mappingParts  ? implode(', ', $mappingParts)                 : '—';
            $targetProperty = $targetParts   ? implode(' / ', array_unique($targetParts))   : '—';
            $propExistsCol  = $propExistsBits ? implode(', ', $propExistsBits)               : '—';
            $formFieldCol   = $formFieldBits  ? implode(', ', $formFieldBits)                : '—';
            $safeCol        = $safeRoles      ? 'Y (' . implode(',', $safeRoles) . ')'      : 'N';
            $notes          = $itemNotes ?: self::fieldNotes($canonicalKey, $isParsed);

            $previewedCol      = in_array($canonicalKey, $baseKeys, true) ? 'Y' : 'N';
            $reasonNotMapped   = self::deriveReasonNotMapped(
                $canonicalKey,
                $isParsed,
                $mappingParts,
                $propExistsBits,
                $formFieldBits,
                $safeRoles
            );

            $rows[] = [
                'mls_form'          => $item['form'],
                'mls_section'       => $item['section'],
                'mls_field_label'   => $item['mls_label'],
                'canonical_key'     => $canonicalKey,
                'current_mapping'   => $currentMapping,
                'target_property'   => $targetProperty,
                'property_exists'   => $propExistsCol,
                'form_field_exists' => $formFieldCol,
                'safe'              => $safeCol,
                'norm_required'     => $normRequired ? 'Y' : 'N',
                'previewed'         => $previewedCol,
                'reason_not_mapped' => $reasonNotMapped,
                'notes'             => $notes,
            ];
        }

        usort($rows, fn ($a, $b) =>
            [$a['mls_form'], $a['mls_section'], $a['mls_field_label']]
            <=>
            [$b['mls_form'], $b['mls_section'], $b['mls_field_label']]
        );

        return $rows;
    }

    /**
     * Derive the "Reason if not mapped" value for a single row.
     *
     * Priority order (from task spec):
     *   1. missing_from_parser      — no parser branch
     *   2. missing_from_field_map   — parsed but no fieldMap entry for any role
     *   3. property_missing         — in fieldMap but no Livewire property found
     *   4. no_form_binding          — property exists but no wire:model binding
     *   5. intentionally_excluded   — known documented exclusion (lowest priority)
     *
     * Rows that ARE safe for at least one role return an empty string.
     *
     * @param  string[] $mappingParts    e.g. ['S:maximum_budget', 'B:maximum_budget']
     * @param  string[] $propExistsBits  e.g. ['S:Y', 'L:N']
     * @param  string[] $formFieldBits   e.g. ['S:Y', 'L:Y']
     * @param  string[] $safeRoles       roles for which all three conditions are met
     */
    private static function deriveReasonNotMapped(
        string $canonicalKey,
        bool $isParsed,
        array $mappingParts,
        array $propExistsBits,
        array $formFieldBits,
        array $safeRoles
    ): string {
        // Safe for at least one role — no reason needed
        if (!empty($safeRoles)) {
            return '';
        }

        // Priority 1: not in parser output
        if (!$isParsed) {
            return 'missing_from_parser';
        }

        // Priority 2: parsed but no field map entry for any role
        if (empty($mappingParts)) {
            // Known intentional exclusions surface as intentionally_excluded
            $intentional = ['mls_number', 'application_fee', 'listing_type_hint'];
            if (in_array($canonicalKey, $intentional, true)) {
                return 'intentionally_excluded';
            }
            return 'missing_from_field_map';
        }

        // Priority 3: in field map, but no Livewire property exists on any mapped role
        $anyPropExists = false;
        foreach ($propExistsBits as $bit) {
            if (str_ends_with($bit, ':Y')) {
                $anyPropExists = true;
                break;
            }
        }
        if (!$anyPropExists) {
            return 'property_missing';
        }

        // Priority 4: property exists on at least one role, but no wire:model found
        $anyFormExists = false;
        foreach ($formFieldBits as $bit) {
            if (str_ends_with($bit, ':Y')) {
                $anyFormExists = true;
                break;
            }
        }
        if (!$anyFormExists) {
            return 'no_form_binding';
        }

        // Priority 5: intentionally excluded (parsed, mapped, prop and/or form exist,
        // but still not safe — likely a semantic exclusion documented elsewhere)
        $intentional = ['mls_number', 'application_fee', 'listing_type_hint'];
        if (in_array($canonicalKey, $intentional, true)) {
            return 'intentionally_excluded';
        }

        // Fallback: mixed-role partial coverage (some roles safe via $safeRoles above,
        // which was already caught; reaching here means partial infra gap)
        return 'missing_from_field_map';
    }

    // ─── Markdown rendering ──────────────────────────────────────────────────

    private static function renderMarkdown(array $rows): string
    {
        $now = date('Y-m-d H:i:s');

        $md  = "# MLS Import Coverage Report\n\n";
        $md .= "Generated: {$now}\n\n";
        $md .= "## Legend\n";
        $md .= "- **Canonical Import Key** — internal key emitted by `MlsListingImportService::parseFields()`; `—` means no parser branch exists.\n";
        $md .= "- **Current Mapping** — live `MlsFieldMap::forRole()` entries (S=Seller, L=Landlord, B=Buyer, T=Tenant).\n";
        $md .= "- **Target Property** — Livewire public property the import writes to.\n";
        $md .= "- **Property Exists (Y/N)** — `property_exists()` result per role (live, from component class).\n";
        $md .= "- **Form Field Exists (Y/N)** — `wire:model=\"propName\"` found in a Create/Edit tab blade file (live grep).\n";
        $md .= "- **Safe To Import (Y/N)** — Parsed=Y AND Property Exists=Y AND Form Field Exists=Y for that role.\n";
        $md .= "- **Normalization Required (Y/N)** — `MlsNormalizer::normalize()` applies a non-trivial transformation.\n";
        $md .= "- **Previewed (Y/N)** — Y if the canonical key is in `MlsFieldMap::universalBaseKeys()`; these fields populate the import preview without requiring property type to be selected first.\n";
        $md .= "- **Reason if not mapped** — for Safe=N rows: `missing_from_parser`, `missing_from_field_map`, `property_missing`, `no_form_binding`, or `intentionally_excluded`. Empty for Safe=Y rows.\n\n";

        $header = '| MLS Form | MLS Section | MLS Field Label | Canonical Import Key | '
                . 'Current Mapping | Target Property | Property Exists (Y/N) | '
                . 'Form Field Exists (Y/N) | Safe To Import (Y/N) | '
                . 'Normalization Required (Y/N) | Previewed (Y/N) | Reason if not mapped | Notes |';

        $separator = '|---|---|---|---|---|---|---|---|---|---|---|---|---|';

        $md .= $header . "\n" . $separator . "\n";

        foreach ($rows as $r) {
            $keyCell = $r['canonical_key'] !== null ? '`' . $r['canonical_key'] . '`' : '—';

            $md .= sprintf(
                "| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
                self::escape($r['mls_form']),
                self::escape($r['mls_section']),
                self::escape($r['mls_field_label']),
                $keyCell,
                self::escape($r['current_mapping']),
                self::escape($r['target_property']),
                $r['property_exists'],
                $r['form_field_exists'],
                $r['safe'],
                $r['norm_required'],
                $r['previewed'],
                self::escape($r['reason_not_mapped']),
                self::escape($r['notes'])
            );
        }

        $md .= "\n" . self::rejectedMappingsSection() . "\n";

        return $md;
    }

    private static function escape(string $s): string
    {
        return str_replace('|', '\\|', $s);
    }

    // ─── Complete MLS form field inventory ───────────────────────────────────

    /**
     * Complete field inventory across all 7 Stellar MLS forms.
     *
     * Each entry represents one (form × field) pair.  Entries with
     * canonical_key = null are MLS fields that exist on the form but have
     * no parser branch and no app target yet — they appear as unmapped/unsafe
     * rows in the report, surfacing coverage gaps.
     *
     * Fields shared across multiple forms are listed once per form so that
     * sorting by form produces a complete, self-contained section per form.
     *
     * @return array<int,array{form:string,section:string,mls_label:string,canonical_key:string|null,norm_required:bool,notes:string}>
     */
    private static function fieldInventory(): array
    {
        // ── Shared field groups ───────────────────────────────────────────────
        // Reused across forms to avoid repetition.  Each group returns the
        // form-independent fields with canonical_key set.

        $address = fn (string $form) => [
            ['form' => $form, 'section' => 'ADDRESS', 'mls_label' => 'Street Address',    'canonical_key' => 'address',  'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'ADDRESS', 'mls_label' => 'City',              'canonical_key' => 'city',     'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'ADDRESS', 'mls_label' => 'State',             'canonical_key' => 'state',    'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'ADDRESS', 'mls_label' => 'Zip Code',          'canonical_key' => 'zip',      'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'ADDRESS', 'mls_label' => 'County',            'canonical_key' => 'county',   'norm_required' => false, 'notes' => ''],
        ];

        $mlsId = fn (string $form) => [
            ['form' => $form, 'section' => 'LISTING INFORMATION', 'mls_label' => 'MLS #', 'canonical_key' => 'mls_number', 'norm_required' => false, 'notes' => 'Parsed; not mapped — no Livewire property on any component (see Rejected Mapping Candidates)'],
        ];

        $taxLegal = fn (string $form) => [
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Tax ID / Parcel ID',          'canonical_key' => 'tax_id',             'norm_required' => false, 'notes' => 'App property is parcel_id; see Rejected Mapping Candidates'],
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Tax Year',                    'canonical_key' => 'tax_year',           'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Annual Property Taxes',       'canonical_key' => 'annual_taxes',       'norm_required' => false, 'notes' => 'App property is annual_property_taxes'],
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Legal Description',           'canonical_key' => 'legal_description',  'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Additional Parcels Y/N',      'canonical_key' => 'additional_parcels', 'norm_required' => true,  'notes' => ''],
            ['form' => $form, 'section' => 'TAX / LEGAL', 'mls_label' => 'Total Number of Parcels',     'canonical_key' => 'total_parcel_count', 'norm_required' => false, 'notes' => ''],
        ];

        $floodZone = fn (string $form) => [
            ['form' => $form, 'section' => 'FLOOD ZONE', 'mls_label' => 'Flood Zone Code',  'canonical_key' => 'flood_zone_code',  'norm_required' => true,  'notes' => 'Valid values: X, AE, A, AH, AO, VE, V, D — normalizer uppercases'],
            ['form' => $form, 'section' => 'FLOOD ZONE', 'mls_label' => 'Flood Zone Date',  'canonical_key' => 'flood_zone_date',  'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'FLOOD ZONE', 'mls_label' => 'Flood Zone Panel', 'canonical_key' => 'flood_zone_panel', 'norm_required' => false, 'notes' => ''],
        ];

        $hoaCdd = fn (string $form) => [
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'Association Y/N',           'canonical_key' => 'has_hoa',                   'norm_required' => true,  'notes' => 'Normalizer coerces Yes/Y/TRUE → "yes", No/N/FALSE → "no"'],
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'Association Name',          'canonical_key' => 'association_name',          'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'Association Fee',           'canonical_key' => 'association_fee_amount',    'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'Association Fee Frequency', 'canonical_key' => 'association_fee_frequency', 'norm_required' => true,  'notes' => 'Normalizer: Monthly/Quarterly/Annually/Semi-Annually → lowercase'],
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'CDD Y/N',                  'canonical_key' => 'has_cdd',                   'norm_required' => true,  'notes' => 'Normalizer coerces Yes/Y/TRUE → "yes", No/N/FALSE → "no"'],
            ['form' => $form, 'section' => 'HOA / CDD', 'mls_label' => 'CDD Annual Amount',         'canonical_key' => 'annual_cdd_fee',            'norm_required' => false, 'notes' => ''],
        ];

        $remarks = fn (string $form) => [
            ['form' => $form, 'section' => 'REMARKS', 'mls_label' => 'Public Remarks', 'canonical_key' => 'description', 'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'REMARKS', 'mls_label' => 'Directions',     'canonical_key' => 'directions',  'norm_required' => false, 'notes' => ''],
        ];

        $waterfront = fn (string $form) => [
            ['form' => $form, 'section' => 'WATERFRONT', 'mls_label' => 'Waterfront Y/N', 'canonical_key' => 'waterfront',   'norm_required' => true,  'notes' => ''],
            ['form' => $form, 'section' => 'WATERFRONT', 'mls_label' => 'Water Access',   'canonical_key' => 'water_access', 'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'WATERFRONT', 'mls_label' => 'Water View',     'canonical_key' => 'water_view',   'norm_required' => false, 'notes' => ''],
        ];

        $lotFields = fn (string $form) => [
            ['form' => $form, 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Lot Dimensions',  'canonical_key' => 'lot_dimensions', 'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Lot Acreage',     'canonical_key' => 'lot_size_acres', 'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Lot Size (Sq Ft)', 'canonical_key' => 'lot_size_sqft', 'norm_required' => false, 'notes' => ''],
            ['form' => $form, 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Zoning',          'canonical_key' => 'zoning',         'norm_required' => false, 'notes' => ''],
        ];

        // ── Merge all entries ─────────────────────────────────────────────────
        $entries = [];
        $merge   = function (array ...$groups) use (&$entries): void {
            foreach ($groups as $g) {
                foreach ($g as $row) {
                    $entries[] = $row;
                }
            }
        };

        // ═══════════════════════════════════════════════════════════════════
        // 1. RESIDENTIAL (Seller / Buyer)
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Residential'),
            $mlsId('Residential'),
            [
                ['form' => 'Residential', 'section' => 'LISTING INFORMATION', 'mls_label' => 'List Price', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => 'Seller→maximum_budget (Desired Sale Price on Sale Terms tab); Buyer→maximum_budget (budget cap)'],
            ],
            [
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Bedrooms',    'canonical_key' => 'bedrooms',    'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Bathrooms',   'canonical_key' => 'bathrooms',   'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Heated Sq Ft', 'canonical_key' => 'heated_sqft', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Year Built',  'canonical_key' => 'year_built',  'norm_required' => false, 'notes' => 'Buyer map omits — no property on BuyerOfferListing'],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Pool',        'canonical_key' => 'pool',        'norm_required' => true,  'notes' => ''],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Garage',      'canonical_key' => 'garage',      'norm_required' => true,  'notes' => ''],
                ['form' => 'Residential', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Carport',     'canonical_key' => 'carport',     'norm_required' => true,  'notes' => ''],
            ],
            $lotFields('Residential'),
            [
                ['form' => 'Residential', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Air Conditioning',  'canonical_key' => 'air_conditioning',  'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Heating',           'canonical_key' => 'heating',           'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Interior Features', 'canonical_key' => 'interior_features', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Residential', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Appliances',        'canonical_key' => 'appliances',        'norm_required' => false, 'notes' => ''],
            ],
            $waterfront('Residential'),
            $taxLegal('Residential'),
            $floodZone('Residential'),
            $hoaCdd('Residential'),
            $remarks('Residential'),
            [
                ['form' => 'Residential', 'section' => 'DERIVED', 'mls_label' => '(Derived from rental signals — not an MLS field)', 'canonical_key' => 'listing_type_hint', 'norm_required' => false, 'notes' => 'Set to "rental" or "sale"; not an MLS field'],
            ]
        );

        // ═══════════════════════════════════════════════════════════════════
        // 2. RENTAL (Landlord / Tenant)
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Rental'),
            $mlsId('Rental'),
            [
                ['form' => 'Rental', 'section' => 'LISTING INFORMATION', 'mls_label' => 'Monthly Rent', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => 'Landlord→desired_rental_amount; Tenant price omitted — semantically wrong direction'],
            ],
            [
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Bedrooms',    'canonical_key' => 'bedrooms',    'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Bathrooms',   'canonical_key' => 'bathrooms',   'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Heated Sq Ft', 'canonical_key' => 'heated_sqft', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Year Built',  'canonical_key' => 'year_built',  'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Pool',        'canonical_key' => 'pool',        'norm_required' => true,  'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Garage',      'canonical_key' => 'garage',      'norm_required' => true,  'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Carport',     'canonical_key' => 'carport',     'norm_required' => true,  'notes' => ''],
                ['form' => 'Rental', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Furnished',   'canonical_key' => 'furnished',   'norm_required' => true,  'notes' => ''],
            ],
            $lotFields('Rental'),
            [
                ['form' => 'Rental', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Air Conditioning',  'canonical_key' => 'air_conditioning',  'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Heating',           'canonical_key' => 'heating',           'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Interior Features', 'canonical_key' => 'interior_features', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'INTERIOR / SYSTEMS', 'mls_label' => 'Appliances',        'canonical_key' => 'appliances',        'norm_required' => false, 'notes' => ''],
            ],
            $waterfront('Rental'),
            $taxLegal('Rental'),
            $floodZone('Rental'),
            $hoaCdd('Rental'),
            [
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Available Date',            'canonical_key' => 'available_date',           'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Minimum Security Deposit',  'canonical_key' => 'minimum_security_deposit', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Lease Amount Frequency',   'canonical_key' => 'lease_amount_frequency',   'norm_required' => true,  'notes' => ''],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Terms of Lease',           'canonical_key' => 'terms_of_lease',           'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Tenant Pays',              'canonical_key' => 'tenant_pays',              'norm_required' => false, 'notes' => ''],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Application Fee',          'canonical_key' => 'application_fee',          'norm_required' => false, 'notes' => 'Parsed; not mapped for Landlord — property absent on LandlordOfferListing'],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Rent Includes',            'canonical_key' => 'rent_includes',            'norm_required' => false, 'notes' => ''],
                // Rental-specific fields present on MLS form but not yet parsed
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Pets Allowed',             'canonical_key' => null, 'norm_required' => false, 'notes' => 'MLS field present — no parser branch; no app field'],
                ['form' => 'Rental', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Minimum Lease (Months)',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'MLS field present — no parser branch; no app field'],
            ],
            $remarks('Rental')
        );

        // ═══════════════════════════════════════════════════════════════════
        // 3. VACANT LAND
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Vacant Land'),
            $mlsId('Vacant Land'),
            [
                ['form' => 'Vacant Land', 'section' => 'LISTING INFORMATION', 'mls_label' => 'List Price', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => ''],
            ],
            $lotFields('Vacant Land'),
            [
                // Vacant Land specific — no canonical key yet
                ['form' => 'Vacant Land', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Lot Features',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Vacant Land field — cleared/filled/wooded; no parser branch'],
                ['form' => 'Vacant Land', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Road Surface Type',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Vacant Land field — no parser branch'],
                ['form' => 'Vacant Land', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Utilities Available', 'canonical_key' => null, 'norm_required' => false, 'notes' => 'Vacant Land field — water/sewer/electric; no parser branch'],
                ['form' => 'Vacant Land', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Topography',          'canonical_key' => null, 'norm_required' => false, 'notes' => 'Vacant Land field — no parser branch'],
            ],
            $waterfront('Vacant Land'),
            $taxLegal('Vacant Land'),
            $floodZone('Vacant Land'),
            $hoaCdd('Vacant Land'),
            $remarks('Vacant Land')
        );

        // ═══════════════════════════════════════════════════════════════════
        // 4. INCOME (Multi-Family)
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Income'),
            $mlsId('Income'),
            [
                ['form' => 'Income', 'section' => 'LISTING INFORMATION', 'mls_label' => 'List Price', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => ''],
            ],
            [
                ['form' => 'Income', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Year Built',     'canonical_key' => 'year_built',  'norm_required' => false, 'notes' => ''],
                ['form' => 'Income', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Heated Sq Ft',   'canonical_key' => 'heated_sqft', 'norm_required' => false, 'notes' => ''],
                // Income specific — no canonical key yet
                ['form' => 'Income', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Number of Units', 'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — multi-family unit count; no parser branch'],
                ['form' => 'Income', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Unit Types',      'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — 1BR/2BR mix; no parser branch'],
            ],
            $lotFields('Income'),
            [
                // Income financial metrics — no canonical key yet
                ['form' => 'Income', 'section' => 'FINANCIAL', 'mls_label' => 'Net Operating Income (NOI)', 'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — no app field or parser branch'],
                ['form' => 'Income', 'section' => 'FINANCIAL', 'mls_label' => 'Annual Gross Income',       'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — no app field or parser branch'],
                ['form' => 'Income', 'section' => 'FINANCIAL', 'mls_label' => 'Annual Expenses',           'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — no app field or parser branch'],
                ['form' => 'Income', 'section' => 'FINANCIAL', 'mls_label' => 'Cap Rate',                  'canonical_key' => null, 'norm_required' => false, 'notes' => 'Income field — no app field or parser branch'],
            ],
            $taxLegal('Income'),
            $floodZone('Income'),
            $hoaCdd('Income'),
            $remarks('Income')
        );

        // ═══════════════════════════════════════════════════════════════════
        // 5. COMMERCIAL SALE
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Commercial Sale'),
            $mlsId('Commercial Sale'),
            [
                ['form' => 'Commercial Sale', 'section' => 'LISTING INFORMATION', 'mls_label' => 'List Price', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => ''],
            ],
            [
                ['form' => 'Commercial Sale', 'section' => 'PROPERTY DETAILS', 'mls_label' => 'Year Built',         'canonical_key' => 'year_built',  'norm_required' => false, 'notes' => ''],
                // Commercial Sale specific — no canonical key yet
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Building Size (Sq Ft)', 'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Number of Bays',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Number of Dock Doors',  'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Ceiling Height (Ft)',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Office Area (Sq Ft)',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Parking Spaces',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
            ],
            $lotFields('Commercial Sale'),
            [
                ['form' => 'Commercial Sale', 'section' => 'FINANCIAL', 'mls_label' => 'Net Operating Income (NOI)', 'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
                ['form' => 'Commercial Sale', 'section' => 'FINANCIAL', 'mls_label' => 'Cap Rate',                   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — no app field or parser branch'],
            ],
            $waterfront('Commercial Sale'),
            $taxLegal('Commercial Sale'),
            $floodZone('Commercial Sale'),
            $hoaCdd('Commercial Sale'),
            $remarks('Commercial Sale')
        );

        // ═══════════════════════════════════════════════════════════════════
        // 6. COMMERCIAL LEASE
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Commercial Lease'),
            $mlsId('Commercial Lease'),
            [
                ['form' => 'Commercial Lease', 'section' => 'LISTING INFORMATION', 'mls_label' => 'Monthly Rent / Lease Rate', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => 'Landlord→desired_rental_amount'],
            ],
            [
                // Commercial Lease specific — no canonical key yet
                ['form' => 'Commercial Lease', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Building Size (Sq Ft)',  'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
                ['form' => 'Commercial Lease', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Office Area (Sq Ft)',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
                ['form' => 'Commercial Lease', 'section' => 'BUILDING DETAILS', 'mls_label' => 'Parking Spaces',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
            ],
            [
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Available Date',         'canonical_key' => 'available_date', 'norm_required' => false, 'notes' => ''],
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Tenant Pays',            'canonical_key' => 'tenant_pays',    'norm_required' => false, 'notes' => ''],
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Rent Includes',          'canonical_key' => 'rent_includes',  'norm_required' => false, 'notes' => ''],
                // Commercial Lease specific — no canonical key yet
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Lease Rate Type',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial field — NNN/Gross/Modified Gross; no parser branch'],
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Minimum Lease Term',     'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Rent Rate (per Sq Ft)',  'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
                ['form' => 'Commercial Lease', 'section' => 'RENTAL / LEASE', 'mls_label' => 'Build-Out Allowance',    'canonical_key' => null, 'norm_required' => false, 'notes' => 'Commercial Lease field — no app field or parser branch'],
            ],
            $taxLegal('Commercial Lease'),
            $floodZone('Commercial Lease'),
            $hoaCdd('Commercial Lease'),
            $remarks('Commercial Lease')
        );

        // ═══════════════════════════════════════════════════════════════════
        // 7. BUSINESS OPPORTUNITY
        // ═══════════════════════════════════════════════════════════════════
        $merge(
            $address('Business Opportunity'),
            $mlsId('Business Opportunity'),
            [
                ['form' => 'Business Opportunity', 'section' => 'LISTING INFORMATION', 'mls_label' => 'Asking Price', 'canonical_key' => 'price', 'norm_required' => false, 'notes' => ''],
            ],
            [
                // Business Opportunity specific — all no canonical key yet
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Business Type',         'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Annual Revenue',        'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Annual Net Income',     'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Inventory Included Y/N','canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Number of Employees',   'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Seller Financing Y/N',  'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
                ['form' => 'Business Opportunity', 'section' => 'BUSINESS DETAILS', 'mls_label' => 'Lease Type',            'canonical_key' => null, 'norm_required' => false, 'notes' => 'Business Opportunity field — no app field or parser branch'],
            ],
            $taxLegal('Business Opportunity'),
            $remarks('Business Opportunity')
        );

        return $entries;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Fallback notes for canonical-key rows that have no item-level notes.
     * Only used when fieldInventory() leaves the notes field empty.
     */
    private static function fieldNotes(string $key, bool $isParsed): string
    {
        if (!$isParsed && $key !== 'listing_type_hint') {
            return 'Parser branch absent — not yet parsed';
        }

        return match ($key) {
            'mls_number'              => 'Parsed; not mapped — no Livewire property on any component (see Rejected Mapping Candidates)',
            'application_fee'         => 'Parsed; not mapped for Landlord — property absent on LandlordOfferListing',
            'price'                   => 'Seller→maximum_budget (Desired Sale Price, Sale Terms tab); Landlord→desired_rental_amount; Buyer→maximum_budget; Tenant price omitted',
            'flood_zone_code'         => 'Select field; valid values: X, AE, A, AH, AO, VE, V, D — normalizer uppercases',
            'tax_id'                  => 'App property is parcel_id (not tax_id) — see Rejected Mapping Candidates',
            'annual_taxes'            => 'App property is annual_property_taxes (includes "property" in name)',
            'listing_type_hint'       => 'Derived: set to "rental" or "sale" from rental signals; not an MLS field',
            'has_hoa'                 => 'Normalizer coerces Yes/Y/TRUE → "yes", No/N/FALSE → "no"',
            'has_cdd'                 => 'Normalizer coerces Yes/Y/TRUE → "yes", No/N/FALSE → "no"',
            'association_fee_frequency' => 'Normalizer: Monthly/Quarterly/Annually/Semi-Annually → lowercase',
            default                   => '',
        };
    }

    // ─── Rejected Mapping Candidates ─────────────────────────────────────────

    private static function rejectedMappingsSection(): string
    {
        return implode("\n", [
            '## Rejected Mapping Candidates',
            '',
            'Mappings evaluated and explicitly rejected. Documented here to prevent',
            're-introduction without deliberate review.',
            '',
            '| Canonical Key | Rejected Target Property | Rejected For Role(s) | Reason |',
            '|---|---|---|---|',
            '| `mls_number` | `mls_number` | All | No Livewire property named `mls_number` exists on any component. Parsed but not imported. |',
            '| `application_fee` | `application_fee` | Landlord | Property does not exist on `LandlordOfferListing`. Parsed but not mapped. |',
            '| `year_built` | `year_built` | Buyer | Property does not exist on `BuyerOfferListing`. |',
            '| `price` | `desired_rental_amount` | Tenant | MLS listing price is the landlord\'s asking rent, not a tenant\'s desired amount — semantically wrong direction. |',
            '| `tax_id` (canonical) | `tax_id` (property name) | All | App property is `parcel_id`, not `tax_id`. Using the wrong name silently skips the form field. |',
        ]);
    }
}
