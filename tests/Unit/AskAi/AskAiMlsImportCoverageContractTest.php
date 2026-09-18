<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingPrefillService;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Support\AskAi\AskAiFieldDisposition as D;
use PHPUnit\Framework\TestCase;

/**
 * The import half of the four-way contract: every meta key MLS quick import can write for a
 * Seller or Landlord listing is either read by Ask AI, or named — with a reason — as
 * deliberately not read. Silence fails, naming the key.
 *
 * The writable set is DERIVED from the import's own authorities, never restated:
 * MlsFieldMap::forRole() targets of MlsListingPrefillService::ALLOWED_FIELDS (the Tier-1
 * facts), plus every META_* key MlsQuickImportDraftWriter declares (provenance, sync, and the
 * Tier-2 details blob). A key added to either side lands here automatically.
 *
 * Measured before this contract existed, the landlord context read none of seven landlord
 * form inputs import also writes (pool, garage, carport, total acreage, floor covering,
 * waterfront feet, lease available date), and nothing in Ask AI read the Tier-2 blob.
 */
class AskAiMlsImportCoverageContractTest extends TestCase
{
    public function test_every_meta_key_quick_import_can_write_is_read_or_dispositioned(): void
    {
        $silent = [];

        foreach (['seller', 'landlord'] as $role) {
            $read = $this->readByContext($role);
            foreach ($this->importWritable($role) as $metaKey => $origin) {
                if (isset($read[$metaKey])
                    || array_key_exists($metaKey, D::IMPORT_META_READ_ELSEWHERE)
                    || $this->declaredUnread($role, $metaKey)) {
                    continue;
                }
                $silent[] = "{$role}: {$metaKey} ({$origin})";
            }
        }

        $this->assertSame([], $silent,
            "MLS quick import can write these keys, and Ask AI neither reads them nor says why. Add a "
            . "CANONICAL_SOURCE_MAP entry, or AskAiFieldDisposition::IMPORT_META_NOT_READ with a reason:\n"
            . implode("\n", $silent));
    }

    public function test_every_import_disposition_names_a_key_import_can_actually_write(): void
    {
        $writable = array_merge($this->importWritable('seller'), $this->importWritable('landlord'));
        $stale    = [];

        foreach (array_merge(D::IMPORT_META_NOT_READ, D::IMPORT_META_READ_ELSEWHERE) as $entry => $reason) {
            [$role, $metaKey] = str_contains($entry, '.') ? explode('.', $entry, 2) : [null, $entry];
            $scope = $role === null ? $writable : $this->importWritable($role);
            if (!array_key_exists($metaKey, $scope)) {
                $stale[] = "{$entry} — import no longer writes it";
            }
            if (trim($reason) === '') {
                $stale[] = "{$entry} — no reason";
            }
        }

        $this->assertSame([], $stale, implode("\n", $stale));
    }

    public function test_no_key_is_both_read_and_declared_unread(): void
    {
        $contradictions = [];
        foreach (['seller', 'landlord'] as $role) {
            foreach (array_keys($this->readByContext($role)) as $metaKey) {
                if ($this->declaredUnread($role, $metaKey)) {
                    $contradictions[] = "{$role}: {$metaKey} is read, yet IMPORT_META_NOT_READ says it is not";
                }
            }
        }

        $this->assertSame([], $contradictions, implode("\n", $contradictions));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, string> meta key => origin */
    private function importWritable(string $role): array
    {
        $map      = MlsFieldMap::forRole($role);
        $writable = [];

        foreach (array_unique(array_values(MlsListingPrefillService::ALLOWED_FIELDS)) as $importKey) {
            // The projection merges `furnished` into the SELLER's building_features list only.
            if ($importKey === 'furnished') {
                if ($role === 'seller') {
                    $writable['building_features'] = 'import:furnished';
                }
                continue;
            }
            $target = $map[$importKey] ?? null;
            if (is_string($target) && ltrim($target, '*') !== '') {
                $writable[ltrim($target, '*')] = "import:{$importKey}";
            }
        }

        foreach ((new \ReflectionClass(MlsQuickImportDraftWriter::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'META_') && is_string($value)) {
                $writable[$value] = "writer:{$name}";
            }
        }

        $this->assertNotEmpty($writable, 'The import-writable set resolved empty — the contract would pass vacuously.');

        return $writable;
    }

    private function declaredUnread(string $role, string $metaKey): bool
    {
        return array_key_exists($metaKey, D::IMPORT_META_NOT_READ)
            || array_key_exists("{$role}.{$metaKey}", D::IMPORT_META_NOT_READ);
    }

    /** @return array<string, true> meta keys some CANONICAL_SOURCE_MAP entry reads for this role */
    private function readByContext(string $role): array
    {
        $read = [];
        foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] ?? [] as $sources) {
            foreach ((array) $sources as $source) {
                if (is_string($source) && !str_starts_with($source, 'native:')) {
                    $read[$source] = true;
                }
            }
        }

        return $read;
    }
}
