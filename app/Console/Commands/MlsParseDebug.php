<?php

namespace App\Console\Commands;

use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingImportService;
use Illuminate\Console\Command;

class MlsParseDebug extends Command
{
    protected $signature = 'mls:parse-debug
                            {--text= : Raw MLS listing text to parse}
                            {--url=  : Public MLS listing URL to fetch and parse}
                            {--role=seller : Role to use for field mapping (seller|buyer|landlord|tenant)}';

    protected $description = 'Dump MLS parser output for debugging — shows every canonical key extracted and its role-specific Livewire property mapping.';

    public function handle(MlsListingImportService $service): int
    {
        $text = $this->option('text') ?? '';
        $url  = $this->option('url')  ?? '';
        $role = strtolower(trim($this->option('role') ?? 'seller'));

        if ($text === '' && $url === '') {
            $this->error('You must provide at least one of --text="..." or --url="...".');
            return self::FAILURE;
        }

        if (!in_array($role, ['seller', 'buyer', 'landlord', 'tenant'], true)) {
            $this->error('Invalid --role value. Allowed: seller, buyer, landlord, tenant.');
            return self::FAILURE;
        }

        $this->line('');
        $this->info("Parsing MLS input for role: <comment>{$role}</comment>");
        $this->line('');

        $result = $service->import($url, $text !== '' ? $text : null);

        if (!$result['success']) {
            $this->error('Parser error: ' . $result['error']);
            return self::FAILURE;
        }

        $parsed  = $result['data'];
        $fieldMap = MlsFieldMap::forRole($role);
        $labels  = MlsFieldMap::fieldLabels();

        $mapped   = [];
        $unmapped = [];

        foreach ($parsed as $canonicalKey => $value) {
            $displayValue = is_array($value)
                ? implode(', ', $value)
                : (string) $value;

            $truncated = mb_strlen($displayValue) > 80
                ? mb_substr($displayValue, 0, 77) . '...'
                : $displayValue;

            $humanKey = $labels[$canonicalKey] ?? $canonicalKey;

            if (array_key_exists($canonicalKey, $fieldMap)) {
                $prop = $fieldMap[$canonicalKey];
                $mapped[] = [$humanKey, $canonicalKey, $truncated, $prop];
            } else {
                $unmapped[] = [$humanKey, $canonicalKey, $truncated];
            }
        }

        if (!empty($mapped)) {
            $this->info('Mapped fields (' . count($mapped) . '):');
            $this->table(
                ['Field Label', 'Canonical Key', 'Parsed Value', 'Livewire Property'],
                $mapped
            );
        } else {
            $this->warn('No fields were mapped for role "' . $role . '".');
        }

        if (!empty($unmapped)) {
            $this->line('');
            $this->warn('Unmapped / skipped in preview (' . count($unmapped) . '):');
            $this->table(
                ['Field Label', 'Canonical Key', 'Parsed Value'],
                $unmapped
            );
        }

        $totalParsed  = count($parsed);
        $totalMapped  = count($mapped);
        $totalSkipped = count($unmapped);

        $this->line('');
        $this->line(
            "<info>{$totalParsed} field(s) parsed</info>, " .
            "<info>{$totalMapped} mapped</info> for role <comment>{$role}</comment>, " .
            "<comment>{$totalSkipped} skipped</comment>."
        );

        return self::SUCCESS;
    }
}
