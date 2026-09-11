<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The two Virtual Drive proof pages. Development-only — see VirtualDriveProofGate.
 *
 * ONE PROVIDER PER PAGE
 * ---------------------
 * Apple and Google are two pages that share one template and one listing UI,
 * never one page with both. That keeps the comparison fair (the UI is
 * identical; only the provider script differs) and it keeps the Google page
 * inside Google's Terms, which forbid Street View imagery and a non-Google map
 * on the same screen.
 *
 * A CREDENTIAL IS EMITTED ONLY WHEN IT EXISTS
 * -------------------------------------------
 * Each page reads its own provider's credential and nothing else — the Google
 * page can never fall back to GOOGLE_PLACES_API_KEY (a server key) or to
 * Explore's browser key. With no credential the page says which variable is
 * missing and the provider library is never loaded.
 */
class VirtualDriveProofController extends Controller
{
    public function apple(): View
    {
        return $this->page(
            provider:       'apple',
            label:          'Apple Look Around (MapKit JS)',
            script:         'apple-lookaround-provider.js',
            credential:     config('virtual_drive.apple.mapkit_token'),
            credentialName: 'VIRTUAL_DRIVE_MAPKIT_JS_TOKEN',
            libraryUrl:     (string) config('virtual_drive.apple.library_url'),
            apiVersion:     null,
        );
    }

    public function google(): View
    {
        return $this->page(
            provider:       'google',
            label:          'Google Street View (Maps JavaScript API)',
            script:         'google-streetview-provider.js',
            credential:     config('virtual_drive.google.browser_key'),
            credentialName: 'VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY',
            libraryUrl:     null,
            apiVersion:     (string) config('virtual_drive.google.api_version', 'weekly'),
        );
    }

    private function page(
        string $provider,
        string $label,
        string $script,
        mixed $credential,
        string $credentialName,
        ?string $libraryUrl,
        ?string $apiVersion,
    ): View {
        return view('dev.virtual-drive.show', [
            'provider'       => $provider,
            'providerLabel'  => $label,
            'providerScript' => $script,
            'credential'     => is_string($credential) && trim($credential) !== '' ? trim($credential) : null,
            'credentialName' => $credentialName,
            'libraryUrl'     => $libraryUrl,
            'apiVersion'     => $apiVersion,
            'nearbyRadius'   => (int) config('virtual_drive.nearby.radius_meters', 400),
        ]);
    }
}
