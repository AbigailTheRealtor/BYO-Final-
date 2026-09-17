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
     * @var string[]
     */
    private const WIRED_CALL_SITES = [
        'app/Console/Commands/DeriveSmartTags.php',
        'app/Console/Commands/ImportBridgeProperties.php',
        'app/Http/Livewire/Concerns/BelongsToListingWorkflow.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/QuickImport/MlsQuickImportComponent.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        'app/Services/Bridge/BridgeListingLookupService.php',
        'app/Services/Bridge/LazyBridgeImportService.php',
        'app/Services/Explore/ExploreInventoryService.php',
        'app/Services/Stellar/Matching/BuyerMatchService.php',
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
        'config/listing_preference',
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

        foreach ($untouched as $dir) {
            foreach ($this->phpFiles($dir) as $path => $source) {
                $this->assertDoesNotMatchRegularExpression('/SmartTag|smart_tag/i', $source, "{$path} references Smart Tags");
            }
        }
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

        foreach (['SMART_TAGS_DERIVATION_ENABLED', 'SMART_TAGS_BRIDGE_ENABLED', 'smart_tags_wiring'] as $needle) {
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
