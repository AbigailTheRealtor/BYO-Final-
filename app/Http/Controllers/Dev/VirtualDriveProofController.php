<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\VirtualDrive\VirtualDriveGoogleAuthBlock;
use App\Support\VirtualDrive\VirtualDriveGoogleGate;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
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
 *
 * AND THE GOOGLE CREDENTIAL IS NOT EMITTED AT ALL
 * ----------------------------------------------
 * The Google page is rendered with an empty `data-credential`. Its browser key
 * lives in exactly one response — a granted launch claim — so the kill switch
 * (VIRTUAL_DRIVE_GOOGLE_ENABLED) and the daily ceiling
 * (VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT) are enforced by WITHHOLDING THE
 * MEANS rather than by asking the browser not to. The page says whether a key
 * exists, which is what lets the launch button report "not configured" without
 * publishing the key to find out.
 *
 * WITH GOOGLE SWITCHED OFF, THE PROVIDER SCRIPT IS NOT ON THE PAGE
 * ---------------------------------------------------------------
 * Not hidden, not disabled, not included: google-streetview-provider.js is the
 * only file that can construct a StreetViewPanorama, and it is omitted. The
 * rest of the page — the listings, the cards, the photos, the nearby list — is
 * unaffected, because none of it was ever Google's.
 */
class VirtualDriveProofController extends Controller
{
    public function __construct(
        private readonly VirtualDriveGoogleLaunchLedger $ledger,
        private readonly VirtualDriveGoogleAuthBlock $authBlock,
    ) {}

    public function compare(): View
    {
        return view('dev.virtual-drive.compare', [
            'sharedCoordinateKey' => (string) config('virtual_drive.shared_coordinate_listing_key', ''),
            'defaultListingKey'   => $this->listingKeyOrEmpty(config('virtual_drive.default_listing_key')),
            // So the page that offers the choice says which provider can answer.
            // It still loads neither, and still emits no credential of any kind.
            'googleEnabled'       => VirtualDriveGoogleGate::enabled(),
            'googleRefusal'       => VirtualDriveGoogleGate::refusalReason(),
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
        $enabled = VirtualDriveGoogleGate::enabled();
        $refusal = VirtualDriveGoogleGate::refusalReason();

        // A standing auth-failure block is stated before anybody presses
        // anything, and the shell starts locked on it. The claim endpoint refuses
        // regardless; this is so a reload SAYS why instead of offering a button.
        $authBlock = $this->authBlockForPage();

        if ($refusal === null && $authBlock !== null) {
            $refusal = $this->authBlock->refusalMessage($authBlock);
        }

        return $this->page(
            request:        $request,
            provider:       'google',
            label:          'Google Street View (Maps JavaScript API)',
            // Omitted entirely when the kill switch is off: nothing on the page
            // is then capable of constructing a panorama.
            script:         $enabled ? 'google-streetview-provider.js' : null,
            // Never the key. The page states only that one exists; the key itself
            // is handed out by VirtualDriveGoogleLaunchController on a grant.
            credential:     null,
            credentialName: 'VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY',
            libraryUrl:     null,
            apiVersion:     (string) config('virtual_drive.google.api_version', 'weekly'),
            launchLabel:    'Drive with Google',
            launchNote:     $refusal ?? $this->googleLaunchNote(),
            providerEnabled: $enabled,
            credentialAvailable: VirtualDriveGoogleGate::hasBrowserKey(),
            claimEndpoint:  $enabled ? route('dev.virtual-drive.api.google-launch', [], false) : null,
            dailyLaunchLimit: VirtualDriveGoogleGate::dailyLaunchLimit(),
            authFailureEndpoint: $enabled ? route('dev.virtual-drive.api.google-auth-failure', [], false) : null,
            authBlock:      $authBlock,
        );
    }

    /**
     * The public view of a recorded block, `['unreadable' => …]` for a block file
     * that exists but cannot be read (still a block), or null.
     *
     * @return array<string,mixed>|null
     */
    private function authBlockForPage(): ?array
    {
        try {
            return $this->authBlock->publicView($this->authBlock->current());
        } catch (\Throwable $e) {
            return ['code' => null, 'message' => 'The recorded auth-failure block could not be read; launches stay refused.'];
        }
    }

    /**
     * What the launch button says when Google may in fact be launched. The
     * allowance is read, never claimed — rendering a page must not cost one of
     * the day's launches.
     */
    private function googleLaunchNote(): string
    {
        $state = $this->ledger->peek();

        if ($state['readable'] !== true) {
            return 'Starts one Google Street View session, which Google bills. The daily launch tally cannot '
                . 'currently be read, so launches will be refused rather than started uncounted.';
        }

        if ($state['remaining'] === 0) {
            return 'Daily Google Street View limit reached: all ' . $state['limit'] . ' launches for '
                . $state['day'] . ' have been used across this proof environment. Nothing will be requested '
                . 'from Google. Apple Look Around and every listing on this page still work.';
        }

        return 'Starts one Google Street View session, which Google bills. ' . $state['used'] . ' of '
            . $state['limit'] . ' launches used today across this proof environment — ' . $state['remaining']
            . ' left. Nothing is requested from Google until you press this, and reloading the page never '
            . 'starts one.';
    }

    /**
     * @param  string|null  $script            the provider file, or null to include NO provider at all
     * @param  bool  $providerEnabled          may this page's provider run here?
     * @param  bool|null  $credentialAvailable  a credential exists (null = infer from $credential)
     * @param  string|null  $claimEndpoint      where a launch must be claimed before the library loads
     */
    private function page(
        Request $request,
        string $provider,
        string $label,
        ?string $script,
        mixed $credential,
        string $credentialName,
        ?string $libraryUrl,
        ?string $apiVersion,
        string $launchLabel,
        string $launchNote,
        bool $providerEnabled = true,
        ?bool $credentialAvailable = null,
        ?string $claimEndpoint = null,
        int $dailyLaunchLimit = 0,
        ?string $authFailureEndpoint = null,
        ?array $authBlock = null,
    ): View {
        $inline = is_string($credential) && trim($credential) !== '' ? trim($credential) : null;

        return view('dev.virtual-drive.show', [
            'provider'            => $provider,
            'providerLabel'       => $label,
            'providerScript'      => $script,
            'credential'          => $inline,
            'providerEnabled'     => $providerEnabled,
            'credentialAvailable' => $credentialAvailable ?? ($inline !== null),
            'claimEndpoint'       => $claimEndpoint,
            'dailyLaunchLimit'    => $dailyLaunchLimit,
            'authFailureEndpoint' => $authFailureEndpoint,
            // JSON for a data attribute; Blade escapes it. Never contains the key.
            'authBlockJson'       => $authBlock === null ? '' : (string) json_encode($authBlock, JSON_UNESCAPED_SLASHES),
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
