<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The Virtual Drive proof pages. Development-only — see VirtualDriveProofGate.
 *
 * THE COMPARISON PAGE LOADS NEITHER PROVIDER
 * ------------------------------------------
 * It lists the same stored homes and links each one to the Apple page and the
 * Google page. It includes no provider script and emits no credential, so
 * opening it can never start a street-level session of either kind.
 *
 * ONE PROVIDER PER PAGE, AND ONLY AFTER A DELIBERATE CLICK
 * --------------------------------------------------------
 * Apple and Google are two pages that share one template and one listing UI,
 * never one page with both. That keeps the comparison fair and keeps the Google
 * page inside Google's Terms, which forbid Street View imagery and a non-Google
 * map on the same screen. On either page the provider library is requested only
 * when the launch button is pressed; `?listing=` preselects a home and never
 * launches anything, so following a link or reloading the page cannot start a
 * (billable) session.
 *
 * A CREDENTIAL IS EMITTED ONLY WHEN IT EXISTS
 * -------------------------------------------
 * Each page reads its own provider's credential and nothing else — the Google
 * page can never fall back to GOOGLE_PLACES_API_KEY (a server key) or to
 * Explore's browser key.
 */
class VirtualDriveProofController extends Controller
{
    public function compare(): View
    {
        return view('dev.virtual-drive.compare', [
            'sharedCoordinateKey' => (string) config('virtual_drive.shared_coordinate_listing_key', ''),
            'defaultListingKey'   => $this->listingKeyOrEmpty(config('virtual_drive.default_listing_key')),
        ]);
    }

    public function apple(Request $request): View
    {
        return $this->page(
            request:        $request,
            provider:       'apple',
            label:          'Apple Look Around (MapKit JS)',
            script:         'apple-lookaround-provider.js',
            credential:     config('virtual_drive.apple.mapkit_token'),
            credentialName: 'VIRTUAL_DRIVE_MAPKIT_JS_TOKEN',
            libraryUrl:     (string) config('virtual_drive.apple.library_url'),
            apiVersion:     null,
            launchLabel:    'Open Look Around',
            launchNote:     'Loads Apple MapKit JS and opens Look Around at this home. Nothing is requested from Apple until you press this.',
        );
    }

    public function google(Request $request): View
    {
        return $this->page(
            request:        $request,
            provider:       'google',
            label:          'Google Street View (Maps JavaScript API)',
            script:         'google-streetview-provider.js',
            credential:     config('virtual_drive.google.browser_key'),
            credentialName: 'VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY',
            libraryUrl:     null,
            apiVersion:     (string) config('virtual_drive.google.api_version', 'weekly'),
            launchLabel:    'Drive with Google',
            launchNote:     'Starts one Google Street View session, which Google bills. Nothing is requested from Google until you press this, and reloading the page never starts one.',
        );
    }

    private function page(
        Request $request,
        string $provider,
        string $label,
        string $script,
        mixed $credential,
        string $credentialName,
        ?string $libraryUrl,
        ?string $apiVersion,
        string $launchLabel,
        string $launchNote,
    ): View {
        return view('dev.virtual-drive.show', [
            'provider'         => $provider,
            'providerLabel'    => $label,
            'providerScript'   => $script,
            'credential'       => is_string($credential) && trim($credential) !== '' ? trim($credential) : null,
            'credentialName'   => $credentialName,
            'libraryUrl'       => $libraryUrl,
            'apiVersion'       => $apiVersion,
            'nearbyRadius'     => (int) config('virtual_drive.nearby.radius_meters', 400),
            'selectedListing'  => $this->selectedListing($request),
            'defaultListing'   => $this->listingKeyOrEmpty(config('virtual_drive.default_listing_key')),
            'viewMode'         => $request->query('view') === 'customer' ? 'customer' : 'dev',
            'nearbyRequery'    => (float) config('virtual_drive.nearby.requery_fraction', 0.4),
            'signs'            => (array) config('virtual_drive.signs', []),
            'launchLabel'      => $launchLabel,
            'launchNote'       => $launchNote,
        ]);
    }

    private function listingKeyOrEmpty(mixed $value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9\-]{1,64}$/', $value) === 1 ? $value : '';
    }

    /** A ListingKey-shaped value, or nothing. The shell checks it against the test set. */
    private function selectedListing(Request $request): string
    {
        $value = $request->query('listing');

        return is_string($value) && preg_match('/^[A-Za-z0-9\-]{1,64}$/', $value) === 1 ? $value : '';
    }
}
