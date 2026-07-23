<?php

namespace App\Support\Storage;

/**
 * R2-D.2 (HI-05A) — the single public-media URL entry point for views.
 *
 * All Blade sites that previously built a public listing-media URL via
 * asset('storage/…'), Storage::url() or Storage::disk('public')->url() now call
 * ListingMediaUrl::get($relativeKey) instead. It is a thin, view-friendly façade
 * over ListingStorageReader::publicUrl() — the resolver holds the logic
 * (local vs object-first, prefix scope, public/private isolation), this class
 * just makes it callable from `{{ }}` and `@php` without wiring.
 *
 * $relative is the disk-relative key (e.g. 'auction/images/uuid.jpg'), NOT a
 * 'storage/'-prefixed path — the 'storage/' segment that the old asset() calls
 * hardcoded is supplied by the public disk's own URL configuration.
 *
 * Default behavior (public_read='local') is byte-equivalent to the prior URLs.
 */
class ListingMediaUrl
{
    public static function get(string $relative): string
    {
        return app(ListingStorageReader::class)->publicUrl($relative);
    }
}
