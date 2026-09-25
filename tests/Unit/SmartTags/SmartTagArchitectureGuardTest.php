<?php

namespace Tests\Unit\SmartTags;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Structural guarantees, asserted against source rather than remembered:
 *
 *   • no AI, network or image service is reachable from Smart Tags;
 *   • the Smart Tag config files have exactly one reader;
 *   • SmartTagLifecycle is the ONLY seam — no application path calls the
 *     derivation service, evidence writer, resolver, projector or purger
 *     directly;
 *   • only the wired call sites reference Smart Tags at all, and the subsystems
 *     Phase 2 promised not to touch still do not.
 *
 * PHASE 2 CHANGED THE THIRD AND FOURTH GUARANTEES, DELIBERATELY. Phase 1 asserted
 * total inertness: nothing anywhere called Smart Tags. Wiring the lifecycle ends
 * that, and a test asserting an obsolete property would simply have been deleted.
 * It is replaced by the narrower guarantee that actually matters now — one seam,
 * and a named list of files allowed through it — so widening the blast radius
 * still requires editing this test, which is the protection Phase 1's version
 * provided and the reason it is not just removed.
 */
class SmartTagArchitectureGuardTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return array<string, string> path => contents */
    private function phpFiles(string $relativeDir): array
    {
        $dir = $this->root() . '/' . $relativeDir;
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $out[substr($file->getPathname(), strlen($this->root()) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, string> */
    private function smartTagSources(): array
    {
        return array_merge(
            $this->phpFiles('app/Support/SmartTags'),
            $this->phpFiles('app/Services/SmartTags'),
            ['config/smart_tags.php' => (string) file_get_contents($this->root() . '/config/smart_tags.php')],
            ['config/smart_tag_sources.php' => (string) file_get_contents($this->root() . '/config/smart_tag_sources.php')],
        );
    }

    /** @test */
    public function smart_tags_reach_no_ai_network_or_image_service(): void
    {
        $forbidden = [
            'OpenAi', 'OpenAI', 'openai', 'Anthropic', 'Guzzle', 'Http::', 'ClientInterface', 'curl_', 'file_get_contents(\'http',
            'fsockopen', 'stream_socket_client', 'AskAi', 'AgentAi', 'gpt-', 'image_url', 'input_image', 'Imagick', 'imagecreate', 'GooglePlaces',
            'ProviderRequestBudget', 'BridgeApiService', 'LazyBridgeImportService',
        ];

        foreach ($this->smartTagSources() as $path => $source) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $source, "{$path} references {$needle}");
            }
        }
    }

    /** @test */
    public function the_smart_tag_config_files_have_exactly_one_reader(): void
    {
        $offenders = [];
        foreach (['app', 'routes', 'resources/views', 'database', 'config'] as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                if ($path === 'app/Support/SmartTags/SmartTagConfig.php') {
                    continue;
                }
                // A real read: config('smart_tags…') or a require/include of the file.
                if (preg_match('/config\(\s*[\'"]smart_tag|\b(?:require|include)(?:_once)?\b[^;\n]*smart_tag/', $source)) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertSame([], $offenders, 'Only SmartTagConfig may read the Smart Tag config files');
    }

    /**
     * The files Phase 2 wired, and the only ones allowed to name Smart Tags.
     *
     * Every entry is a call site that goes through SmartTagLifecycle. Adding one
     * is a deliberate edit here, in the same commit as the wiring, which is the
     * point: an accidental new caller fails this test rather than shipping.
     *
     * THE OWNER-SELECTION SURFACE added three: the shared wizard concern, which
     * calls SmartTagLifecycle::ownerPanel() and ::trySaveOwnerSelections(), and
     * the Blade partial that renders what the first returns. They are call sites
     * rather than taxonomy readers — one of them WRITES, through the seam — so
     * they belong here and are held to the one-seam rule below. The four Offer
     * Listing components that `use` the concern were already listed.
     *
     * The two `property-preferences.blade.php` partials that @include the picker
     * are deliberately absent: they name no Smart Tag symbol, so the guard has
     * nothing to catch there and listing them would claim a coupling that the
     * files do not have.
     *
     * @var string[]
     */
    private const WIRED_CALL_SITES = [
        'app/Console/Commands/DeriveSmartTags.php',
        // Read-only coverage report; reaches Smart Tags only via SmartTagLifecycle::bridgeCoverage().
        'app/Console/Commands/SmartTagCoverageReport.php',
        // Registers the gated Bridge catch-up schedule; asks SmartTagWiring only.
        'app/Console/Kernel.php',
        'app/Console/Commands/ImportBridgeProperties.php',
        'app/Http/Livewire/Concerns/BelongsToListingWorkflow.php',
        'app/Http/Livewire/Concerns/HasOwnerSmartTags.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/QuickImport/MlsQuickImportComponent.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        'app/Services/Bridge/BridgeListingLookupService.php',
        'app/Services/Bridge/LazyBridgeImportService.php',
        'app/Services/Explore/ExploreInventoryService.php',
        'app/Services/Stellar/Matching/BuyerMatchService.php',
        'resources/views/livewire/offer-listing/shared/_owner-smart-tags.blade.php',
    ];

    /**
     * Subsystems that legitimately READ the Smart Tag taxonomy but never touch the
     * derivation machinery.
     *
     * Listing Preferences (Save | Maybe | Pass) links each preference reason to a
     * canonical tag key and inherits `seeker_selectable` from the taxonomy rather
     * than restating it — a reviewed dependency, documented in
     * docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md and in §11 of the
     * Smart Tags governance.
     *
     * Its write surface and its Blade control are in this list for a NARROWER
     * reason still: they name SmartTagListingType and SmartTagListingRef, which
     * are listing IDENTITY types, not taxonomy at all. The controller validates
     * `listing_type` against SmartTagListingType::cases() precisely so a browser
     * cannot invent one, and both build a SmartTagListingRef to hand to the
     * preference services. Neither reads a tag, a rule or an evidence row.
     *
     * These two entries were added when Listing Preferences Phase 2 merged: the
     * prefixes above were written against Phase 1, which had no controller and no
     * component, so the list described the subsystem's file layout at that moment
     * rather than the subsystem.
     *
     * KEPT SEPARATE FROM WIRED_CALL_SITES ON PURPOSE. A reader consults the
     * taxonomy, the contexts and the compliance guard; a CALLER derives, writes or
     * purges evidence. Only the second is what SmartTagLifecycle exists to gate,
     * and smart_tag_lifecycle_is_the_only_seam() still holds every file in this
     * list to that rule. Folding them into one list would mean a genuinely new
     * caller could be waved through as "just another reader".
     *
     * @var string[] path prefixes
     */
    private const TAXONOMY_READERS = [
        'app/Models/ListingPreference',
        'app/Services/ListingPreferences/',
        'app/Support/ListingPreferences/',
        'app/Http/Controllers/ListingPreferenceController.php',
        // Phase 3B: both read SmartTagListingType / SmartTagListingRef as the
        // listing-identity registry, and derive, write and purge nothing.
        'app/Http/Controllers/MyListingPreferencesController.php',
        'app/Support/VirtualDrive/VirtualDrivePreferenceControl.php',
        // The Playwright harness's fixture seeder: writes preferences through
        // ListingPreferenceWriter using the listing-identity registry. Test
        // infrastructure; it derives, evidences and purges nothing.
        'database/seeders/ListingPreferenceBrowserTestSeeder.php',
        'resources/views/components/listing-preference/',
        'config/listing_preference',

        // Seeker Smart Tag preferences — what a Buyer/Tenant ASKS FOR.
        //
        // Readers, not call sites, by the distinction below: they consult the
        // taxonomy, the contexts and SmartTagSelectionPolicy, and write to
        // `smart_tag_seeker_preferences`. None of them derives, writes or purges
        // EVIDENCE about a property, which is what SmartTagLifecycle gates — a
        // seeker preference is a request, not a claim about a listing.
        //
        // The two criteria controllers reach the writer and the reader; the
        // shared picker partial projects the taxonomy for SURFACE_SEEKER and the
        // Buyer/Tenant edit wizards restore stored selections through the reader.
        'app/Services/SmartTags/Seeker/',
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
        // Deletion cleanup for the seeker table, and its registration. Neither
        // derives anything; see the named exception in
        // the_subsystems_phase_two_promised_not_to_touch_are_untouched().
        'app/Observers/SmartTagSeekerPreferenceObserver.php',
        'app/Providers/AppServiceProvider.php',
        'resources/views/partials/smart-tags/',
        'resources/views/buyer_criteria/',
        'resources/views/tenant_criteria/',
        // Seeker preferences on Buyer/Tenant OFFER LISTINGS — the records Stellar
        // matching actually reads. Same writer, reader, table, policy and gate as
        // the criteria entries above; the concern is the only file that calls
        // them, the four wizards name only the subject type they write, and the
        // partial renders what the concern projected. None derives, writes or
        // purges evidence about a property.
        'app/Http/Livewire/Concerns/HasSeekerSmartTags.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'resources/views/livewire/offer-listing/shared/_seeker-smart-tags.blade.php',

        // Seeker Smart Tag MATCHING — the picks participating in the Stellar score.
        //
        // Readers by the same distinction: the four criteria loaders put the
        // reader's matchingKeysFor() into the payload, the payload re-governs the
        // keys, and the scorer reads resolved `smart_tag_assignments` through
        // ListingSmartTagIndex and scores them inside Amenities. None derives,
        // writes or purges evidence; BuyerMatchService stays a wired call site for
        // its derivation OPT-OUT and is listed there. Named file by file, not by
        // directory, so a new Stellar file that starts reading tags fails here.
        'app/Services/Stellar/BuyerCriteriaLoader.php',
        'app/Services/Stellar/TenantCriteriaLoader.php',
        'app/Services/Stellar/BuyerOfferListingCriteriaLoader.php',
        'app/Services/Stellar/TenantOfferListingCriteriaLoader.php',
        'app/Services/Stellar/Matching/DTO/BuyerCriteriaPayload.php',
        'app/Services/Stellar/Matching/DTO/BuyerMatchResult.php',
        'app/Services/Stellar/Matching/BuyerMatchScorer.php',

        // After the P1-A facts seam: the scorer core reads resolved tags as FACTS
        // (ListingSmartTagFacts on ListingMatchFacts; the comparison on
        // ListingMatchScore), and the result builder words the comparison. The
        // three single-listing surfaces read their row's tags through
        // ListingSmartTagIndex before scoring, as scoreAll() does for a result set.
        'app/Services/Stellar/Matching/ListingMatchFacts.php',
        'app/Services/Stellar/Matching/ListingMatchScore.php',
        'app/Services/Stellar/Matching/ListingSmartTagFacts.php',
        'app/Services/Stellar/Matching/BuyerMatchResultBuilder.php',
        'app/Services/Stellar/PropertyMatchContextService.php',
        'app/Services/Stellar/MatchCheck/MatchCheckScorer.php',
        'app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php',
    ];

    /** @test */
    public function smart_tag_lifecycle_is_the_only_seam(): void
    {
        // Everything a call site must NOT reach. The lifecycle facade is the one
        // permitted entry point; these are the internals it exists to wrap, so
        // that gate checks, failure isolation and telemetry cannot be bypassed.
        $internals = ['SmartTagDerivationService', 'SmartTagEvidenceWriter', 'ManualSmartTagWriter', 'SmartTagAssignmentPurger',
                      'SmartTagAssignmentProjector', 'SmartTagResolver', 'BridgeStructuredTagDeriver', 'NativeListingTagDeriver',
                      'ListingDescriptionTagParser'];

        $offenders = [];
        foreach (['app', 'routes', 'resources/views', 'database', 'config'] as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                if (str_starts_with($path, 'app/Services/SmartTags/') || str_starts_with($path, 'app/Support/SmartTags/')) {
                    continue;
                }
                foreach ($internals as $service) {
                    // A code reference (import, construction, static call, class constant), not a docblock mention.
                    $code = '/(?:use\s+App\\\\Services\\\\SmartTags\\\\(?:[\w\\\\]*\\\\)?' . $service . '\b|new\s+\\\\?(?:[\w\\\\]*\\\\)?' . $service . '\b|\b' . $service . '::)/';
                    if (preg_match($code, $source)) {
                        $offenders[] = "{$path} → {$service}";
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            'Application code must reach Smart Tags only through SmartTagLifecycle');
    }

    /** @test */
    public function only_the_wired_call_sites_reference_smart_tags(): void
    {
        $offenders = [];

        foreach (['app', 'routes', 'resources/views', 'database', 'config'] as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                $isTaxonomyReader = false;
                foreach (self::TAXONOMY_READERS as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        $isTaxonomyReader = true;
                        break;
                    }
                }

                if ($isTaxonomyReader
                    || str_starts_with($path, 'app/Services/SmartTags/')
                    || str_starts_with($path, 'app/Support/SmartTags/')
                    || str_starts_with($path, 'app/Models/SmartTag')
                    || str_starts_with($path, 'config/smart_tag')
                    || str_starts_with($path, 'database/migrations/')
                    || in_array($path, self::WIRED_CALL_SITES, true)) {
                    continue;
                }

                if (preg_match('/SmartTag|smart_tag/i', $source)) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertSame([], $offenders,
            'A file outside the wired call sites and the taxonomy readers references Smart Tags — add it to WIRED_CALL_SITES or TAXONOMY_READERS deliberately, or remove the reference');
    }

    /** @test */
    public function the_subsystems_phase_two_promised_not_to_touch_are_untouched(): void
    {
        // Location DNA, the AI DNA generators, MLS sync and the observers were
        // explicitly out of scope. Explore, Stellar matching and Bridge appear in
        // the wired list for their OPT-OUT lines only, so they are checked by
        // only_the_wired_call_sites_reference_smart_tags() instead of here.
        $untouched = [
            'app/Services/LocationDna', 'app/Services/Dna', 'app/Services/ListingImport/Sync',
            'app/Jobs', 'app/Observers',
        ];

        // ONE named exception, and the narrowness is the point — a single file,
        // not the directory.
        //
        // The promise this test records is that DERIVATION does not hang off a
        // model event: an observer that derives would fire on every save of every
        // listing, which is what `SmartTagLifecycle` exists to keep explicit.
        // SmartTagSeekerPreferenceObserver derives nothing. It deletes rows from
        // `smart_tag_seeker_preferences` — a CONSUMER table holding what a Buyer
        // or Tenant asked for — after their criteria record is deleted, because
        // that table has no foreign key and nothing else would clean it up.
        //
        // It has to be an observer rather than the explicit post-commit call
        // `BelongsToListingWorkflow::purgeListingRows()` uses, because this
        // application currently has NO criteria deletion path to put a call in.
        // If a bulk delete is ever added it must call the writer's purge()
        // explicitly, since Eloquent events do not fire for query-builder deletes.
        $allowed = self::OBSERVER_SMART_TAG_EXCEPTIONS;

        foreach ($untouched as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                if (in_array($path, $allowed, true)) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression('/SmartTag|smart_tag/i', $source, "{$path} references Smart Tags");
            }
        }
    }

    /**
     * The exception is ONE file, and it is exact — never a directory, never a
     * pattern. A second entry here is a deliberate edit that has to argue for
     * itself, which is the whole value of the list being this short.
     *
     * @var string[]
     */
    private const OBSERVER_SMART_TAG_EXCEPTIONS = [
        'app/Observers/SmartTagSeekerPreferenceObserver.php',
    ];

    /**
     * WHAT THE CARVE-OUT ABOVE IS NOT ALLOWED TO BECOME.
     *
     * Skipping a file in the observer prohibition only says "this file may name
     * Smart Tags". On its own that turns a real guarantee into "no Smart Tags in
     * observers, except one file, which may do anything" — and the thing the
     * prohibition exists to prevent is derivation and lifecycle work hiding
     * inside a model event, which is precisely what that one file could grow.
     *
     * So the exception is bounded from the inside as well: the observer may
     * reference the seeker-preference purge surface and nothing else. If someone
     * later adds a derive call, an evidence write or an assignment write to it,
     * this fails and names the symbol.
     *
     * @test
     */
    public function the_observer_exception_may_only_purge_seeker_preferences(): void
    {
        $this->assertCount(1, self::OBSERVER_SMART_TAG_EXCEPTIONS,
            'the observer carve-out is one exact file; widening it is a deliberate, argued edit');

        $path = self::OBSERVER_SMART_TAG_EXCEPTIONS[0];

        // This class is container-free by design, so paths come from its own
        // root() helper rather than base_path().
        $observers = $this->phpFiles('app/Observers');

        $this->assertArrayHasKey($path, $observers,
            'the carved-out observer must exist, or the exception is stale');

        $source = $observers[$path];

        // (1) The only Smart Tag surface it may reach is the seeker-preference
        // purge path — the writer's purge() and the subject-type registry.
        $allowedSymbols = [
            // The namespace segment itself, unavoidable in the `use` lines for
            // the classes below.
            'SmartTags',
            // Its own class name.
            'SmartTagSeekerPreferenceObserver',
            // The seeker-preference purge surface, and nothing else.
            'SmartTagSeekerPreferenceWriter',
            'SmartTagSeekerSubjectType',
            'SmartTagSeekerPreferenceGate',
            'smart_tag_seeker_preferences',
        ];

        preg_match_all('/\b(SmartTag[A-Za-z]*|smart_tag[a-z_]*)\b/', $source, $m);

        foreach (array_unique($m[1]) as $symbol) {
            $this->assertContains(
                $symbol,
                $allowedSymbols,
                "{$path} references {$symbol}, which is outside the seeker-preference purge surface"
            );
        }

        // (2) Named explicitly, so the failure message says what was smuggled in
        // rather than only that something was.
        foreach ([
            'SmartTagLifecycle',
            'SmartTagDerivationService',
            'SmartTagEvidenceWriter',
            'SmartTagEvidence',
            'SmartTagAssignment',
            'SmartTagAssignmentProjector',
            'NativeListingTagDeriver',
            'BridgeStructuredTagDeriver',
            'ListingDescriptionTagParser',
            'ManualSmartTagWriter',
            'tryDeriveNative',
            'tryDeriveBridge',
            'replaceDerived',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "{$path} must never reach {$forbidden}: an observer may purge seeker preferences, never derive or persist tags"
            );
        }

        // (3) It must actually be the purge it was carved out for.
        $this->assertStringContainsString('->purge(', $source,
            'the carved-out observer should call the seeker-preference purge');
    }

    /**
     * The prohibition still bites for every OTHER observer — the carve-out did
     * not quietly become a directory.
     *
     * @test
     */
    public function every_other_observer_is_still_prohibited_from_naming_smart_tags(): void
    {
        $checked = 0;

        foreach ($this->phpFiles('app/Observers') as $path => $source) {
            if (in_array($path, self::OBSERVER_SMART_TAG_EXCEPTIONS, true)) {
                continue;
            }

            $checked++;
            $this->assertDoesNotMatchRegularExpression('/SmartTag|smart_tag/i', $source,
                "{$path} references Smart Tags and is not the one carved-out observer");
        }

        $this->assertGreaterThan(0, $checked,
            'the observer directory should contain other observers for this guard to be meaningful');
    }

    /** @test */
    public function the_high_volume_bridge_paths_opt_out(): void
    {
        // The two multi-hundred-record importers must pass deriveSmartTags: false.
        // Asserted at the call site rather than by behaviour, because a behavioural
        // test passes just as well when the whole feature is switched off.
        $explore = (string) file_get_contents($this->root() . '/app/Services/Explore/ExploreInventoryService.php');
        $this->assertStringContainsString('deriveSmartTags: false', $explore,
            'Explore viewport discovery must not derive Smart Tags inline');

        $matching = (string) file_get_contents($this->root() . '/app/Services/Stellar/Matching/BuyerMatchService.php');
        $this->assertStringContainsString('deriveSmartTags: false', $matching,
            'Buyer/Tenant criteria search must not derive Smart Tags inline');
    }

    /** @test */
    public function the_activation_flags_are_not_in_the_production_flag_contract(): void
    {
        // A safety switch in the deploy contract would become a deploy-time
        // mechanism for enabling the feature. Asserted against the file's source
        // so it holds whether or not the config is loadable here.
        $contract = (string) file_get_contents($this->root() . '/config/required_production_flags.php');

        foreach (['SMART_TAGS_DERIVATION_ENABLED', 'SMART_TAGS_BRIDGE_ENABLED', 'SMART_TAGS_SEEKER_PREFERENCES_ENABLED',
                  'SMART_TAGS_SEEKER_MATCHING_ENABLED', 'SMART_TAGS_SEEKER_MATCHING_CONTEXTS',
                  'SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED', 'smart_tags_wiring'] as $needle) {
            $this->assertStringNotContainsString($needle, $contract,
                "The production flag contract must never name the Smart Tag safety switch {$needle}");
        }
    }

    /** @test */
    public function mls_remarks_remain_hard_disabled_in_code(): void
    {
        $service = (string) file_get_contents($this->root() . '/app/Services/SmartTags/SmartTagDerivationService.php');
        $writer = (string) file_get_contents($this->root() . '/app/Services/SmartTags/SmartTagEvidenceWriter.php');

        $this->assertStringContainsString('const MLS_REMARKS_PROCESSING_APPROVED = false;', $service);
        $this->assertStringContainsString('const MLS_REMARKS_PERSISTENCE_APPROVED = false;', $writer);

        // And no environment variable may reach either constant — the gate is a
        // reviewed code change, not a config edit.
        $this->assertDoesNotMatchRegularExpression('/MLS_REMARKS_PROCESSING_APPROVED\s*=\s*[^;]*env\(/', $service);
        $this->assertDoesNotMatchRegularExpression('/MLS_REMARKS_PERSISTENCE_APPROVED\s*=\s*[^;]*env\(/', $writer);
    }
}
