<?php

namespace Tests\Support\AskAi;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use Tests\TestCase;

/**
 * An MLS-imported listing built from a committed Bridge fixture through the REAL import path
 * (normalizer → quick-import lookup → draft writer), published. Shared by the Ask AI tests that
 * must see exactly what an import produces.
 */
final class MlsImportFixture
{
    public static function import(TestCase $t, string $slug, string $role, array $overrides = []): object
    {
        $raw = array_merge(json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")), true), [
            'ListingKey'                     => 'AUDIT-' . strtoupper($slug),
            'ListingId'                      => 'AUD' . strtoupper(substr(md5($slug), 0, 6)),
            'StandardStatus'                 => 'Active',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);
        BridgeProperty::where('listing_key', $raw['ListingKey'])->delete();
        // Through the REAL normalizer, so the typed columns the candidate adapter reads
        // (bedrooms_total, bathrooms_total_integer, …) are populated exactly as an import does.
        app(\App\Services\Bridge\BridgePropertyNormalizer::class)->upsert($raw);
        $result = app(MlsQuickImportService::class)->lookup($raw['ListingId'], $role);
        $t->assertTrue($result->isFound(), "{$slug}: lookup failed: {$result->status}");
        $listing = app(MlsQuickImportDraftWriter::class)->materialise($role, User::factory()->create()->id, $result);
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->save();

        return $listing->fresh();
    }
}
