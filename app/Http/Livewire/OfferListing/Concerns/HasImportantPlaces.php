<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Offers\ImportantPlacesService;
use Illuminate\Validation\ValidationException;

/**
 * Phase 9C — Important Places for Buyer / Tenant Search Areas.
 *
 * Shared load / validate / persist plumbing for the repeatable Important Places rows.
 * Data lives in its OWN additive `important_places_json` meta key; the legacy commute
 * fields (`commute_destination_zip`, `max_commute_minutes`, `commute_mode`) are left
 * completely untouched — Important Places is additive, not a migration.
 *
 * Used by BuyerOfferListing / BuyerOfferListingEdit / TenantOfferListing /
 * TenantOfferListingEdit. Hire (9D) is intentionally out of scope.
 */
trait HasImportantPlaces
{
    /** Bound to the Search Areas partial bridge (`wire:model.defer`). */
    public $important_places_json = '';

    /** Decoded, normalized rows handed to the partial for edit/draft pre-render. */
    public $existingImportantPlaces = [];

    protected function importantPlacesService(): ImportantPlacesService
    {
        return app(ImportantPlacesService::class);
    }

    /** Load the `important_places_json` meta into the component + partial prefill array. */
    protected function loadImportantPlaces($auction): void
    {
        $raw = $auction->info('important_places_json') ?? '';

        $this->important_places_json = is_string($raw)
            ? $raw
            : (is_array($raw) ? json_encode($raw) : '');

        $this->existingImportantPlaces = $this->importantPlacesService()
            ->normalize($this->important_places_json);
    }

    /**
     * Persist normalized Important Places to their dedicated meta key. Fully-empty rows
     * are dropped; partially-completed rows are preserved so drafts keep in-progress work.
     * Runs on both draft and submit paths (validation gates the submit path separately).
     */
    protected function saveImportantPlaces($auction): void
    {
        $normalized = $this->importantPlacesService()
            ->normalize($this->important_places_json ?? '');

        $this->important_places_json = json_encode($normalized);
        $this->existingImportantPlaces = $normalized;

        $auction->saveMeta('important_places_json', $this->important_places_json);
    }

    /**
     * Full-submit guard: block the save when any started row is left incomplete. Empty
     * rows never trip this (normalize drops them first). Call only on the full-submit
     * path, never on Save Draft.
     */
    protected function assertImportantPlacesValid(): void
    {
        $errors = $this->importantPlacesService()
            ->validate($this->important_places_json ?? '');

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'important_places_json' => $errors,
            ]);
        }
    }
}
