<?php

namespace App\Console\Commands;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Services\ListingImport\MlsCoverageReporter;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingImportService;
use Illuminate\Console\Command;

/**
 * audit:mls-import
 *
 * Runs all 7 MLS fixture files through the parser, checks for label-bleed,
 * verifies key expected values, and emits a per-fixture field accounting table
 * plus a mapping coverage summary.
 *
 * Per-fixture accounting categories (mutually exclusive, sum to total extracted):
 *   displayed            — in role map AND component property exists → reaches preview
 *   blocked              — in role map BUT property missing → silently dropped by property_exists guard
 *   intentionally_excluded — not in any role map, documented exclusion (mls_number, lot_size_sqft, etc.)
 *   unmapped_gap         — not in any role map, no documented justification → potential wiring gap
 *
 * Exit codes:
 *   0 — all fixtures PASS, no bleed detected
 *   1 — one or more fixtures FAIL
 */
class MlsImportAuditCommand extends Command
{
    protected $signature = 'audit:mls-import
                            {--fixture=* : Run only the named fixture(s) (e.g. --fixture=residential)}
                            {--role=both : Role map to check: seller, landlord, or both}
                            {--no-coverage : Skip the coverage report}
                            {--no-table : Skip the per-fixture field accounting table}';

    protected $description = 'Audit MLS parser against all fixture files — reports PASS/FAIL per fixture, detects label-bleed, shows per-fixture field accounting, and emits a mapping coverage summary.';

    private const FIXTURES = [
        'residential',
        'rental',
        'vacant_land',
        'commercial_lease',
        'commercial_sale',
        'income',
        'business_opportunity',
    ];

    /**
     * Expected values per fixture, as an ordered list of checks.
     *
     * Each entry: [ canonical_key, check_type, expected_value ]
     *   check_type: 'equals' | 'contains' | 'not_contains'
     *
     * Using a list (not a keyed array) prevents PHP from silently overwriting
     * duplicate keys when the same canonical field needs multiple assertions.
     */
    private const FIXTURE_CHECKS = [
        'residential' => [
            ['city',                     'equals',      'Tampa'],
            ['sewer',                    'contains',    'Public Sewer'],
            ['sewer',                    'not_contains','Public Sewer Utilities'],
            ['utilities',                'contains',    'BB/HS Internet Available'],
            ['utilities',                'contains',    'Cable Available'],
            ['furnished',                'equals',      'unfurnished'],
            ['association_name',         'contains',    'Sunridge HOA'],
            ['additional_parcels',       'equals',      'no'],
            ['tax_id',                   'equals',      '19-30-17-45612-000-1410'],
            ['flood_insurance_required', 'equals',      'no'],
        ],
        'rental' => [
            ['city',                     'equals',      'St. Petersburg'],
            ['lease_amount_frequency',   'equals',      'monthly'],
            ['minimum_security_deposit', 'equals',      '2800'],
            ['tenant_pays',              'contains',    'Electricity'],
            ['additional_parcels',       'equals',      'no'],
            ['flood_insurance_required', 'equals',      'yes'],
        ],
        'vacant_land' => [
            ['city',               'equals', 'Dade City'],
            ['additional_parcels', 'equals', 'no'],
        ],
        'commercial_lease' => [
            ['city',                      'equals',   'Fort Myers'],
            ['association_name',          'contains', 'Executive Commerce Park Association'],
            ['additional_parcels',        'equals',   'no'],
            ['association_fee_frequency', 'equals',   'monthly'],
        ],
        'commercial_sale' => [
            ['city',               'equals', 'Sarasota'],
            ['additional_parcels', 'equals', 'yes'],
        ],
        'income' => [
            ['city',                    'equals', 'Clearwater'],
            ['additional_parcels',      'equals', 'no'],
            ['has_special_assessments', 'equals', 'yes'],
        ],
        'business_opportunity' => [
            ['city',                    'equals', 'Clearwater'],
            ['additional_parcels',      'equals', 'no'],
            ['has_special_assessments', 'equals', 'yes'],
            ['flood_insurance_required','equals', 'no'],
        ],
    ];

    /**
     * Canonical keys that the parser emits but are intentionally absent from all
     * role maps, with the documented reason why they are not imported.
     * These reach the "extracted but not mapped" path in HasMlsImport and are
     * silently discarded by design — not a gap.
     */
    private const INTENTIONALLY_EXCLUDED = [
        'mls_number'   => 'no form field exists for this role',
        'lot_size_sqft'=> 'forms use lot_size_acres; sqft variant not accepted',
        'directions'   => 'not a listing attribute; navigation-only field',
        'application_fee' => 'not on SellerOfferListing or LandlordOfferListing',
        'heating'      => 'alias emits as heating_fuel; never reaches $data directly',
        'listing_type_hint' => 'internal signal consumed by hint logic; stripped before export',
    ];

    /**
     * Patterns that indicate a value contains a residual label fragment (bleed).
     * Values matching any of these are flagged as FAIL regardless of expectations.
     */
    private const BLEED_PATTERNS = [
        '/\b(?:Tax|Total|Lot|Rooms?|CDD|Flood|Minimum|Fireplace|Association|Exterior\s+Information)\s*:/i',
        '/Y\/N\s*:\s*(?:Yes|No)\b.{10,}/i',   // "Y/N:No Total Number…" — suffix after boolean
    ];

    public function handle(MlsListingImportService $service): int
    {
        $role     = strtolower($this->option('role') ?? 'both');
        $only     = (array) $this->option('fixture');
        $fixtures = count($only) ? array_intersect(self::FIXTURES, $only) : self::FIXTURES;

        if (empty($fixtures)) {
            $this->error('No matching fixtures found. Valid names: ' . implode(', ', self::FIXTURES));
            return self::FAILURE;
        }

        $roles = match ($role) {
            'seller'   => ['seller'],
            'landlord' => ['landlord'],
            default    => ['seller', 'landlord'],
        };

        $overallPass = true;

        foreach ($fixtures as $name) {
            $path = base_path("tests/fixtures/mls/{$name}.txt");
            if (!file_exists($path)) {
                $this->warn("  SKIP  {$name} — fixture file not found at {$path}");
                continue;
            }

            $rawText = file_get_contents($path);
            $result  = $service->import('', $rawText);

            if (!$result['success']) {
                $this->error("  FAIL  [{$name}] — import() returned failure: " . ($result['error'] ?? 'unknown'));
                $overallPass = false;
                continue;
            }

            $data         = $result['data'];
            $checks       = self::FIXTURE_CHECKS[$name] ?? [];
            $fixturePass  = true;
            $failMessages = [];

            // ── Bleed detection: scan every parsed value ──────────────────────
            foreach ($data as $key => $value) {
                $str = is_array($value) ? implode(', ', $value) : (string) $value;
                foreach (self::BLEED_PATTERNS as $pattern) {
                    if (preg_match($pattern, $str)) {
                        $failMessages[] = "  ✗ BLEED [{$key}]: " . mb_substr($str, 0, 80);
                        $fixturePass    = false;
                        break;
                    }
                }
            }

            // ── Explicit expectation checks (list — no duplicate-key risk) ────
            foreach ($checks as [$key, $type, $expected]) {
                if (!array_key_exists($key, $data)) {
                    $failMessages[] = "  ✗ MISSING [{$key}]: field not parsed";
                    $fixturePass    = false;
                    continue;
                }

                $actual = is_array($data[$key]) ? implode(', ', $data[$key]) : (string) $data[$key];

                if ($type === 'equals' && $actual !== $expected) {
                    $failMessages[] = "  ✗ [{$key}] expected='{$expected}' actual='" . mb_substr($actual, 0, 60) . "'";
                    $fixturePass    = false;
                }

                if ($type === 'contains' && stripos($actual, $expected) === false) {
                    $failMessages[] = "  ✗ [{$key}] expected to contain '{$expected}' actual='" . mb_substr($actual, 0, 60) . "'";
                    $fixturePass    = false;
                }

                if ($type === 'not_contains' && stripos($actual, $expected) !== false) {
                    $failMessages[] = "  ✗ [{$key}] must NOT contain '{$expected}' actual='" . mb_substr($actual, 0, 60) . "'";
                    $fixturePass    = false;
                }
            }

            $label = $fixturePass ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line("  {$label}  [{$name}]  (" . count($data) . " fields extracted)");

            if (!$fixturePass) {
                $overallPass = false;
                foreach ($failMessages as $msg) {
                    $this->line("        {$msg}");
                }
            } else {
                foreach (['city', 'price', 'tax_id', 'additional_parcels', 'flood_insurance_required'] as $k) {
                    if (isset($data[$k])) {
                        $v = is_array($data[$k]) ? implode(', ', $data[$k]) : $data[$k];
                        $this->line("        <fg=gray>{$k}: " . mb_substr($v, 0, 60) . '</>');
                    }
                }
            }

            // ── Per-fixture field accounting table ────────────────────────────
            if (!$this->option('no-table')) {
                $this->outputFieldTable($name, $data, $roles);
            }
        }

        $this->newLine();

        // ── Coverage report ───────────────────────────────────────────────────
        if (!$this->option('no-coverage')) {
            $this->outputCoverageReport();
        }

        if ($overallPass) {
            $this->line('<fg=green>✓ All fixtures PASS — 0 Parser FAILs detected.</>');
            return self::SUCCESS;
        }

        $this->error('✗ One or more fixtures FAILED — see details above.');
        return self::FAILURE;
    }

    // ─── Per-fixture field accounting table ───────────────────────────────────

    /**
     * Emit a compact table showing every field the parser extracted for this
     * fixture, classified into exactly one of four mutually exclusive categories
     * (categories always sum to the total extracted count — no silent disappearance):
     *
     *   displayed            — in role map AND component property exists → reaches preview
     *   blocked              — in role map BUT property missing → dropped by property_exists guard
     *   intentionally_excluded — not in any role map; documented exclusion (see INTENTIONALLY_EXCLUDED)
     *   unmapped_gap         — not in any role map; no documented reason → potential wiring gap
     */
    private function outputFieldTable(string $fixtureName, array $data, array $roles): void
    {
        $componentClasses = [
            'seller'   => SellerOfferListing::class,
            'landlord' => LandlordOfferListing::class,
        ];

        $roleMaps = [];
        foreach ($roles as $r) {
            $roleMaps[$r] = MlsFieldMap::forRole($r);
        }

        $counts = [
            'displayed'             => 0,
            'blocked'               => 0,
            'intentionally_excluded'=> 0,
            'unmapped_gap'          => 0,
        ];

        $rows = [];

        foreach ($data as $canonicalKey => $value) {
            // listing_type_hint is an internal signal stripped before export — always excluded
            if ($canonicalKey === 'listing_type_hint') {
                $counts['intentionally_excluded']++;
                continue;
            }

            $displayValue = is_array($value)
                ? implode(', ', $value)
                : (string) $value;
            $displayValue = mb_substr($displayValue, 0, 45);

            // Classify per role: 'displayed' | 'blocked' | 'not_in_map'
            $roleStatus = [];
            foreach ($roles as $r) {
                $map = $roleMaps[$r];
                if (!isset($map[$canonicalKey])) {
                    $roleStatus[$r] = 'not_in_map';
                    continue;
                }
                $propName = ltrim($map[$canonicalKey], '*');
                $class    = $componentClasses[$r] ?? null;
                $roleStatus[$r] = ($class && property_exists($class, $propName))
                    ? 'displayed'
                    : 'blocked';
            }

            // Overall category: best status across roles (displayed > blocked > not_in_map)
            $hasDisplayed = in_array('displayed', $roleStatus, true);
            $hasBlocked   = in_array('blocked', $roleStatus, true);
            $allNotInMap  = !$hasDisplayed && !$hasBlocked;

            if ($hasDisplayed) {
                $category = 'displayed';
            } elseif ($hasBlocked) {
                $category = 'blocked';
            } elseif (array_key_exists($canonicalKey, self::INTENTIONALLY_EXCLUDED)) {
                $category = 'intentionally_excluded';
            } else {
                $category = 'unmapped_gap';
            }

            $counts[$category]++;

            // Build role flag display: S:✓ L:✓ | S:- L:✓ etc.
            $roleFlags = [];
            foreach ($roles as $r) {
                $flag = match ($roleStatus[$r]) {
                    'displayed' => '✓',
                    'blocked'   => '⚠',
                    default     => '-',
                };
                $roleFlags[] = strtoupper(substr($r, 0, 1)) . ':' . $flag;
            }

            $rows[] = [
                'key'      => $canonicalKey,
                'value'    => $displayValue,
                'roles'    => implode('  ', $roleFlags),
                'category' => $category,
            ];
        }

        if (empty($rows)) {
            return;
        }

        $total = array_sum($counts);

        $this->line('');
        $this->line("        <fg=cyan>Field accounting [{$fixtureName}] — {$total} fields extracted, all categorised:</>"); 
        $this->line('        ' . str_pad('Canonical Key', 30) . str_pad('Value (truncated)', 47) . str_pad('Roles', 18) . 'Category');
        $this->line('        ' . str_repeat('─', 108));

        foreach ($rows as $row) {
            if ($row['category'] === 'listing_type_hint') {
                continue;  // internal; never in $rows (filtered above)
            }
            $catColor = match ($row['category']) {
                'displayed'              => 'green',
                'blocked'                => 'yellow',
                'intentionally_excluded' => 'gray',
                'unmapped_gap'           => 'red',
                default                  => 'white',
            };
            $line = '        '
                . str_pad($row['key'], 30)
                . str_pad($row['value'], 47)
                . str_pad($row['roles'], 18)
                . "<fg={$catColor}>{$row['category']}</>";
            $this->line($line);
        }

        $this->line('        ' . str_repeat('─', 108));

        // Summary line — categories must sum to total (no silent disappearance)
        $parts = [];
        $parts[] = "<fg=green>displayed: {$counts['displayed']}</>";
        if ($counts['blocked'] > 0) {
            $parts[] = "<fg=yellow>blocked (no prop): {$counts['blocked']}</>";
        }
        if ($counts['intentionally_excluded'] > 0) {
            $parts[] = "<fg=gray>intentionally_excluded: {$counts['intentionally_excluded']}</>";
        }
        if ($counts['unmapped_gap'] > 0) {
            $parts[] = "<fg=red>unmapped_gap: {$counts['unmapped_gap']}</>";
        }
        $parts[] = "total: {$total}";

        $this->line('        ' . implode('  ', $parts));
        $this->line('');
    }

    // ─── Coverage report ──────────────────────────────────────────────────────

    /**
     * Parse the MlsCoverageReporter markdown output and emit a clean summary.
     *
     * The Safe column is at fixed cell index 8 (0-based, after stripping the
     * leading and trailing empty elements produced by exploding on "|").
     * Safe values start with "Y"; unsafe values start with "N".
     *
     * This avoids the double-count bug that broad str_contains('| Y |') causes
     * when "Y" appears in other columns (e.g. PropExists "S:Y, L:Y, T:Y").
     */
    private function outputCoverageReport(): void
    {
        $this->line('<fg=cyan>── Coverage Report ──────────────────────────────────────────────────────</>');

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'mls_audit_coverage_') . '.md';
            MlsCoverageReporter::generate($tmpFile);
            $lines = file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            unlink($tmpFile);

            $safe    = 0;
            $unsafe  = 0;
            $other   = 0;
            $total   = 0;
            $gapRows = [];

            foreach ($lines as $line) {
                // Only process table data rows (start with "|", not header/separator)
                if (!str_starts_with($line, '|')) {
                    continue;
                }
                // Skip the column-separator row (| --- | --- | ...)
                if (str_starts_with($line, '| --')) {
                    continue;
                }
                // Skip header rows (contain column titles)
                if (str_contains($line, 'Safe To Import') || str_contains($line, 'MLS Form')) {
                    continue;
                }

                // Parse cells — split on "|", drop leading/trailing empty strings from outer pipes
                $parts = explode('|', $line);
                array_shift($parts);
                array_pop($parts);
                $cells = array_map('trim', $parts);

                // Cell index 8 is the "Safe To Import" column.
                // Safe rows start with "Y"; unsafe rows start with "N".
                $safeCell = $cells[8] ?? '';

                $total++;

                if (str_starts_with($safeCell, 'Y')) {
                    $safe++;
                } elseif (str_starts_with($safeCell, 'N')) {
                    $unsafe++;
                    // Record gap row for display: [label, key, reason]
                    $gapRows[] = [
                        'label'  => $cells[2] ?? '?',
                        'key'    => $cells[3] ?? '?',
                        'reason' => $cells[11] ?? '',
                    ];
                } else {
                    $other++;
                }
            }

            $this->line("  Total rows:             {$total}");
            $this->line("  Safe (fully wired):     <fg=green>{$safe}</> / {$total}");
            $this->line("  Not safe (gaps):        <fg=yellow>{$unsafe}</> / {$total}");
            if ($other > 0) {
                $this->line("  Other (no safe cell):   {$other} / {$total}");
            }

            if (count($gapRows) > 0) {
                $this->line("  First 10 gap rows:");
                foreach (array_slice($gapRows, 0, 10) as $gap) {
                    $reason = $gap['reason'] !== '' ? " ({$gap['reason']})" : '';
                    $this->line("    [{$gap['key']}] — {$gap['label']}{$reason}");
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Coverage report failed: ' . $e->getMessage());
        }

        $this->newLine();
    }
}
