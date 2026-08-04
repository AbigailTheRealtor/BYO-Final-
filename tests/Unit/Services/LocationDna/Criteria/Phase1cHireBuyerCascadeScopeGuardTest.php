<?php

namespace Tests\Unit\Services\LocationDna\Criteria;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Phase 1c slice 1 — the scope guard for the Hire Buyer geography cascade.
 *
 * WHAT CHANGED, AND WHY A NEW GUARD RATHER THAN A LOOSER OLD ONE
 * --------------------------------------------------------------
 * Phase 1a and 1b asserted that the Criteria namespace was referenced by NOTHING. Slice 1 wires it
 * into exactly one workflow, so that absolute has to become a bounded one. The two older guards are
 * relaxed to a flag-scoped ALLOWLIST and nothing else: their write bans, their persistence-boundary
 * assertions and their frozen-file hashes are untouched, because the authorization for this slice
 * covers wiring only.
 *
 * An allowlist without an upper bound is not a boundary, so this file supplies the bound. It is the
 * standing proof of four claims the slice makes:
 *
 *   1. The cascade ships OFF, and even when switched on it is scoped to one workflow.
 *   2. Only Hire Buyer opts its widget into cascade mode. The seven other surfaces that share the
 *      same widget partial are byte-unaffected, and the third-party geography autocomplete is
 *      therefore removed from ONE workflow rather than from the application.
 *   3. Seller and Landlord are not touched at all.
 *   4. The persistence boundary is where G1f left it — asserted by hash, not by inspection.
 */
class Phase1cHireBuyerCascadeScopeGuardTest extends TestCase
{
    /** The one tab partial authorized to run the cascade in slice 1. */
    private const ENABLED_TAB =
        'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php';

    /** The shared widget's opt-in parameter. Absent ⇒ legacy behaviour. */
    private const OPT_IN = 'ldnaGeographyCascade';

    /**
     * Every OTHER surface that includes the shared widget. None may opt in during slice 1.
     *
     * Four are Livewire tabs on the rollout list for later slices; four are the legacy criteria
     * forms, which are frozen by the Phase 1b hash pin and are not on the list at all.
     */
    private const UNTOUCHED_WIDGET_HOSTS = [
        'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
        'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php',
        'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php',
        'resources/views/buyer_criteria/add.blade.php',
        'resources/views/buyer_criteria/edit.blade.php',
        'resources/views/tenant_criteria/add.blade.php',
        'resources/views/tenant_criteria/edit.blade.php',
    ];

    /**
     * The persistence boundary, pinned by content hash.
     *
     * The slice authorization says "do not touch persistence services" and "do not modify canonical
     * writer behaviour". A hash is the only assertion that actually says that — a substring probe
     * would pass while the file was rewritten around it.
     */
    private const PERSISTENCE_BOUNDARY = [
        'app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php'
            => 'cd7873b4eaf6125e0e3c8ede68e9b4070cef8d31',
        'app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'
            => '087f5fdcf8d65c1419b4ffa6736f9643e65165f6',
        'app/Services/LocationDna/Persistence/LocationDnaCommandBuilder.php'
            => 'c7484ed8cc3e55bd4bc900c0b9ae43fd6b856305',
        'app/Services/LocationDna/Persistence/OwnerPrivateLocationDnaWriter.php'
            => 'fd307a7ccbc51119a28c76d09b030b20b7039bcb',
        'app/Services/LocationDna/Persistence/MetaKeyedRecord.php'
            => '5e9d6168842ba01120d81708a4073897fa84bf5a',
        'app/Http/Livewire/Concerns/HasSearchAreas.php'
            => '083249c6f6685ebff8762d2f1a4bf5eb4e6d7f1d',
    ];

    /** Components that must remain entirely unaware of the cascade in slice 1. */
    private const OUT_OF_SLICE_COMPONENTS = [
        'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
        'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
        'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
        'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->root().'/'.$relative);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE CASCADE SHIPS OFF AND IS SCOPED TO ONE WORKFLOW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Read from the config FILE rather than the runtime value, so this fails if the shipped
     * default is ever flipped regardless of what the environment happens to hold.
     */
    public function test_the_cascade_ships_disabled(): void
    {
        $config = require $this->root().'/config/criteria_location_dna.php';

        $this->assertFalse(
            $config['geography_cascade_enabled'],
            'The geography cascade must ship disabled by default.'
        );
    }

    /** The older Phase 1a/1b flags are untouched by this slice. */
    public function test_the_phase_1a_flags_are_unchanged(): void
    {
        $config = require $this->root().'/config/criteria_location_dna.php';

        $this->assertFalse($config['geography_preview_enabled']);
        $this->assertSame('eloquent', $config['geography_source']);
    }

    /**
     * The scope list names the pilot workflow and only the pilot workflow.
     *
     * Master switch and scope list must BOTH agree before anything renders, so a single
     * environment variable cannot widen the rollout by accident.
     */
    public function test_the_cascade_scope_is_the_pilot_workflow_only(): void
    {
        $config = require $this->root().'/config/criteria_location_dna.php';

        $this->assertSame(
            ['hire_buyer'],
            $config['geography_cascade_workflows'],
            'Slice 1 is Hire Buyer. Widening this is a rollout decision, not a code change.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE WIDGET OPT-IN REACHES EXACTLY ONE SURFACE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_enabled_tab_opts_the_widget_into_cascade_mode(): void
    {
        $this->assertStringContainsString(
            self::OPT_IN,
            $this->read(self::ENABLED_TAB),
            'Hire Buyer must hand the widget the cascade opt-in.'
        );
    }

    /**
     * No other host opts in — this is the assertion behind "removed from the enabled workflow only".
     *
     * The shared widget keeps its own geography inputs and its own third-party autocomplete for
     * every surface that does not pass the opt-in, so seven surfaces are behaviourally identical
     * to before this slice.
     */
    public function test_no_other_widget_host_opts_in(): void
    {
        $offenders = [];

        foreach (self::UNTOUCHED_WIDGET_HOSTS as $relative) {
            $source = $this->read($relative);

            $this->assertStringContainsString(
                "@include('partials.location-dna.map-input'",
                $source,
                "{$relative} must still include the shared widget."
            );

            if (str_contains($source, self::OPT_IN)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Slice 1 enables the cascade for Hire Buyer only. Found opt-ins in: '
            .implode(', ', $offenders)
        );
    }

    /**
     * The widget's opt-in defaults to OFF, so a host that says nothing keeps legacy behaviour.
     *
     * This is what makes the change to the shared partial additive rather than a behaviour change
     * for seven surfaces at once.
     */
    public function test_the_widget_opt_in_defaults_to_off(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$'.self::OPT_IN.'\s*=\s*\$'.self::OPT_IN.'\s*\?\?\s*false;/',
            $this->read('resources/views/partials/location-dna/map-input.blade.php'),
            'The shared widget must default the cascade opt-in to false.'
        );
    }

    /**
     * The third-party geography autocomplete is still PRESENT in the shared widget.
     *
     * Slice 1 suppresses it for one workflow; it does not delete it. Deleting it here would break
     * the seven surfaces that still depend on it, which is exactly what the flag exists to avoid.
     */
    public function test_the_legacy_geography_autocomplete_is_not_deleted_from_the_shared_widget(): void
    {
        $widget = $this->read('resources/views/partials/location-dna/map-input.blade.php');

        foreach (['ldnaInitCitiesAutocomplete', 'ldnaInitCountiesAutocomplete'] as $initialiser) {
            $this->assertStringContainsString(
                $initialiser,
                $widget,
                "{$initialiser} must survive — seven surfaces still use it."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · NOTHING OUTSIDE THE SLICE LEARNED ABOUT THE CASCADE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_out_of_slice_components_do_not_reference_the_cascade(): void
    {
        $offenders = [];

        foreach (self::OUT_OF_SLICE_COMPONENTS as $relative) {
            $source = $this->read($relative);

            if (str_contains($source, 'HasGeographyCascade')
                || str_contains($source, 'LocationDna\\Criteria')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Slice 1 touches Hire Buyer only. Found: '.implode(', ', $offenders)
        );
    }

    /**
     * Exactly four components carry the cascade trait — the two that serve Hire Buyer CREATE and
     * the two that serve every Hire Buyer EDIT.
     *
     * WHY THE CATCH-ALL PAIR IS HERE AND NOT IN THE OUT-OF-SLICE LIST
     * ---------------------------------------------------------------
     * A route audit established that Hire Buyer has three live surfaces, not one:
     *
     *   /buyer/add-auction              → HireBuyerAgent\BuyerAgentAuction   (buyers, create)
     *   /hire/agent/auction/buyer       → TenantAgentAuction                 (agents, create)
     *   /buyer/agent/auction/edit/{id}  → TenantAgentAuctionEdit             (ALL edits)
     *   /hire/agent/auction/edit/…      → TenantAgentAuctionEdit
     *
     * `HireBuyerAgent\BuyerAgentAuctionEdit` is imported by routes/web.php but never routed, so it
     * is unreachable in production; the catch-all edit component is the only edit surface there is.
     * Wiring only the create side would have produced a listing created with the cascade and edited
     * with the legacy editor — a broken round trip inside one user's own flow.
     *
     * Seller and landlord are served by the same catch-all class and are excluded by its workflow
     * map returning null, which is asserted separately below.
     */
    public function test_exactly_the_four_hire_buyer_surfaces_use_the_cascade_trait(): void
    {
        $users = [];

        foreach ($this->phpFilesUnder(['app/Http/Livewire']) as $relative) {
            if (str_contains($this->read($relative), 'use HasGeographyCascade')
                || str_contains($this->read($relative), 'Concerns\\HasGeographyCascade;')) {
                $users[] = $relative;
            }
        }

        sort($users);

        $this->assertSame(
            [
                'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
                'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
                'app/Http/Livewire/TenantAgentAuction.php',
                'app/Http/Livewire/TenantAgentAuctionEdit.php',
            ],
            $users,
        );
    }

    /**
     * The catch-all's workflow map excludes seller and landlord by returning NULL.
     *
     * This is the role gate, and it is stronger than a config check: there is no string an operator
     * can put in CRITERIA_LDNA_CASCADE_WORKFLOWS that turns the cascade on for those two roles,
     * because no key is ever produced for them.
     */
    public function test_the_catch_all_workflow_map_excludes_seller_and_landlord(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertMatchesRegularExpression(
                "/'buyer'\s*=>\s*'hire_buyer'/",
                $source,
                "{$relative}: buyer must map to the same key the dedicated component uses."
            );
            $this->assertMatchesRegularExpression(
                "/'tenant'\s*=>\s*'hire_tenant'/",
                $source,
                "{$relative}: tenant must map to its own key, ready for the next slice."
            );
            $this->assertMatchesRegularExpression(
                '/default\s*=>\s*null/',
                $source,
                "{$relative}: every other role must resolve to null — that is the seller/landlord gate."
            );

            foreach (['hire_seller', 'hire_landlord'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$relative}: no workflow key may exist for seller or landlord."
                );
            }
        }
    }

    /**
     * Both Hire Buyer entry points are governed by ONE scope-list key.
     *
     * This is the assertion that the two create surfaces cannot drift apart again: enabling
     * `hire_buyer` enables both, and there is no way to enable one alone.
     */
    public function test_both_hire_buyer_entry_points_share_one_workflow_key(): void
    {
        $this->assertStringContainsString(
            "GEOGRAPHY_CASCADE_WORKFLOW = 'hire_buyer'",
            $this->read('app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php'),
        );
        $this->assertStringContainsString(
            "'buyer'  => 'hire_buyer'",
            $this->read('app/Http/Livewire/TenantAgentAuction.php'),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3b · THE ZIP MIRROR STAYS OFF FOR HIRE BUYER
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `hire_buyer` must NOT be in the ZIP mirror opt-in.
     *
     * `TenantAgentAuction` declares `$zipCodes` and writes it to meta unconditionally for every
     * role. If the cascade mirrored its ZIP selection into that property for Hire Buyer, a listing
     * created through the catch-all would emit real ZIPs while the same listing created through the
     * dedicated component emitted none — the per-entry-point divergence this work removes,
     * reintroduced in stored data where nothing would catch it.
     */
    public function test_hire_buyer_does_not_opt_into_the_zip_mirror(): void
    {
        $trait = $this->read('app/Http/Livewire/Concerns/HasGeographyCascade.php');

        $this->assertMatchesRegularExpression(
            "/ZIP_MIRROR_WORKFLOWS\s*=\s*\['hire_tenant'\]/",
            $trait,
            'Only hire_tenant may mirror ZIPs — that workflow already writes the legacy key.'
        );
        $this->assertStringNotContainsString(
            "ZIP_MIRROR_WORKFLOWS = ['hire_buyer'",
            $trait,
        );
    }

    /**
     * A workflow may ship ENABLED only once its tab actually renders the cascade.
     *
     * The hazard this closes: the cascade always states all four geography keys when it is
     * enabled. If a workflow were switched on while its tab had not opted in, the controls would
     * never render, the selection would stay empty, and the save would merge four empty values
     * over the user's stored geography — silently clearing it.
     *
     * `hire_tenant` is deliberately a valid key already, so the catch-all needs no further edit
     * when its slice lands. This asserts that it may not appear in the SHIPPED scope list until
     * the tenant tab passes the opt-in.
     */
    public function test_a_shipped_workflow_must_have_a_tab_that_renders_the_cascade(): void
    {
        $config = require $this->root().'/config/criteria_location_dna.php';

        $tabs = [
            'hire_buyer'  => self::ENABLED_TAB,
            'hire_tenant' => 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
        ];

        foreach ($config['geography_cascade_workflows'] as $workflow) {
            $this->assertArrayHasKey($workflow, $tabs, "unknown workflow `{$workflow}` in the scope list");

            $this->assertStringContainsString(
                self::OPT_IN,
                $this->read($tabs[$workflow]),
                "`{$workflow}` ships enabled but its tab does not render the cascade — enabling it "
                .'would clear stored geography on the next save.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · THE PERSISTENCE BOUNDARY IS UNMOVED
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_persistence_boundary_is_byte_identical(): void
    {
        foreach (self::PERSISTENCE_BOUNDARY as $relative => $expected) {
            $this->assertSame(
                $expected,
                sha1_file($this->root().'/'.$relative),
                "{$relative} was modified. Slice 1 is forbidden to touch the persistence boundary."
            );
        }
    }

    /**
     * The Hire Buyer write call is unchanged: the default writer, the default mirror set, and the
     * same bridged payload property.
     *
     * The cascade contributes by MERGING into that payload before the call, never by changing the
     * call. `managingMirrors` appearing here would mean Hire Buyer had started emitting a legacy
     * mirror it has never written.
     */
    public function test_hire_buyer_still_writes_through_the_default_owner_private_writer(): void
    {
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertStringContainsString(
                '(new OwnerPrivateLocationDnaWriter())',
                $source,
                "{$relative} must still construct the default writer."
            );
            $this->assertStringContainsString(
                '->persistFromEditorPayload($auction, $this->location_dna_preferences_json);',
                $source,
                "{$relative} must still hand the writer the bridged payload unchanged."
            );
            $this->assertStringNotContainsString(
                'managingMirrors',
                $source,
                "{$relative} must not start managing a legacy mirror it has never written."
            );
        }
    }

    /** Slice 1 required no schema change. */
    public function test_no_migration_was_added(): void
    {
        foreach ($this->phpFilesUnder(['database/migrations']) as $relative) {
            $this->assertStringNotContainsString(
                'GeographyCascade',
                $this->read($relative),
                'Slice 1 required no schema change.'
            );
        }
    }

    /**
     * @param  list<string>  $dirs
     * @return list<string>
     */
    private function phpFilesUnder(array $dirs): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $base = $this->root().'/'.$dir;

            if (! is_dir($base)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = ltrim(str_replace($this->root(), '', $file->getPathname()), '/');
                }
            }
        }

        sort($files);

        return $files;
    }
}
