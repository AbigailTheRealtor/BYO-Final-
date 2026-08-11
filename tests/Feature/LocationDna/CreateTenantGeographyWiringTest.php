<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use Tests\TestCase;

/**
 * T2 — Create Tenant carries the cascade and search traits, and nothing is switched on.
 *
 * WHAT "WIRED BUT NOT ENABLED" MEANS, AND WHY THE TWO LAND SEPARATELY
 * -------------------------------------------------------------------
 * The components resolve `create_tenant`, boot both capabilities in the required order, and project
 * at the right save boundaries. None of that renders anything: the workflow is absent from the
 * shipped scope list and the tenant tab carries no cascade surface, so `geoCascadeEnabled` is false
 * on every path a user can reach.
 *
 * That separation is a data-safety rule rather than tidiness. `SellerLandlordCascadeExclusionTest`
 * forbids the reverse order — a workflow listed in config whose tab has not opted in writes four
 * empty geography keys over stored data — so the wiring has to precede the view opt-in, and this
 * suite is the only thing asserting the surface is inert in the gap between them.
 *
 * THE ZIP MIRROR IS THE REASON THIS IS SAFE TO WIRE AT ALL
 * --------------------------------------------------------
 * `HasGeographyCascade` is frozen for this work, so `create_tenant` cannot join
 * `ZIP_MIRROR_WORKFLOWS`. That is not a limitation worked around — it is what closes the failure
 * this workflow was previously blocked on. `syncDiscreteLocationProps()` gates the `$zipCodes`
 * write on `geographyMirrorsZipCodes()`, so the cascade cannot touch the property the legacy
 * `zipCodes` meta is written from, and no cascade selection can empty it.
 * {@see self::the_cascade_cannot_write_the_zip_codes_property()} pins that directly.
 */
class CreateTenantGeographyWiringTest extends TestCase
{
    use DatabaseTransactions;

    /** Every role the four-role Offer components can serve. */
    private const ROLES = ['tenant', 'buyer', 'seller', 'landlord'];

    private const TENANT_SURFACES = [TenantOfferListing::class, TenantOfferListingEdit::class];

    // ═════════════════════════════════════════════════════════════════════════
    // HARNESS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A component instance with no framework lifecycle run against it.
     *
     * `newInstanceWithoutConstructor()` keeps this a test of the cascade rather than of Livewire's
     * mount: these components hydrate auctions, resolve drafts and touch several services during a
     * real mount, none of which this suite is asking about. The cascade methods read only
     * `$user_type` and config, so an un-mounted instance answers the question exactly.
     *
     * @return array{workflow: ?string, cascade: bool, search: bool, mirrors: bool}
     */
    private function bootAs(string $componentClass, string $role): array
    {
        $reflection = new ReflectionClass($componentClass);
        $component  = $reflection->newInstanceWithoutConstructor();

        $component->user_type = $role;

        $workflow = $reflection->getMethod('geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $bootCascade = $reflection->getMethod('bootGeographyCascade');
        $bootCascade->setAccessible(true);
        $bootSearch = $reflection->getMethod('bootGeographySearch');
        $bootSearch->setAccessible(true);

        $key = $workflow->invoke($component);
        $bootCascade->invoke($component, $key);
        $bootSearch->invoke($component);

        return [
            'workflow' => $key,
            'cascade'  => $component->geoCascadeEnabled,
            'search'   => $component->geoSearchEnabled,
            'mirrors'  => $component->geographyMirrorsZipCodes(),
        ];
    }

    /** Force every gate open so "still disabled" means structurally, not by config. */
    private function openEveryGate(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_search_enabled'    => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'create_buyer', 'create_tenant',
            ],
        ]);
    }

    private function sourceOf(string $componentClass): string
    {
        return (string) file_get_contents((new ReflectionClass($componentClass))->getFileName());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · create_tenant RESOLVES ONLY FOR THE TENANT ROLE
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_tenant_role_claims_create_tenant_on_both_surfaces(): void
    {
        $this->openEveryGate();

        foreach (self::TENANT_SURFACES as $component) {
            $this->assertSame(
                'create_tenant',
                $this->bootAs($component, 'tenant')['workflow'],
                "{$component} must claim create_tenant for the tenant role"
            );
        }
    }

    /**
     * Every other role resolves to NULL, with every gate forced open.
     *
     * The point of forcing the gates is that a null workflow is a STRUCTURAL exclusion: there is no
     * value of CRITERIA_LDNA_CASCADE_WORKFLOWS that can reach these roles, because the map never
     * hands them a key to match against.
     *
     * @test
     */
    public function no_other_role_resolves_to_any_workflow(): void
    {
        $this->openEveryGate();

        foreach (self::TENANT_SURFACES as $component) {
            foreach (['buyer', 'seller', 'landlord', 'agent', '', 'TENANT'] as $role) {
                $state = $this->bootAs($component, $role);

                $this->assertNull($state['workflow'], "{$component} / {$role}: must map to no workflow");
                $this->assertFalse($state['cascade'], "{$component} / {$role}: cascade must stay off");
                $this->assertFalse($state['search'], "{$component} / {$role}: search must stay off");
            }
        }
    }

    /** The two surfaces agree exactly — create and edit are one workflow to a user. @test */
    public function the_create_and_edit_surfaces_agree_for_every_role(): void
    {
        $this->openEveryGate();

        foreach (self::ROLES as $role) {
            $this->assertSame(
                $this->bootAs(TenantOfferListing::class, $role),
                $this->bootAs(TenantOfferListingEdit::class, $role),
                "Create and edit diverge for the {$role} role"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · WIRED, BUT STILL GATED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Under the SHIPPED configuration the tenant surface is inert.
     *
     * `create_tenant` is absent from the scope list, so both gates read false even though the
     * component resolves the key. This is the assertion that expires when T5 enables the workflow.
     *
     * @test
     */
    public function the_shipped_configuration_leaves_create_tenant_inert(): void
    {
        // Master gate open, scope list untouched — isolating the scope list as the thing holding
        // the workflow closed rather than letting the master gate mask the result.
        config(['criteria_location_dna.geography_cascade_enabled' => true]);
        config(['criteria_location_dna.geography_search_enabled'  => true]);

        foreach (self::TENANT_SURFACES as $component) {
            $state = $this->bootAs($component, 'tenant');

            $this->assertSame('create_tenant', $state['workflow'], "{$component}: the key is claimed");
            $this->assertFalse($state['cascade'], "{$component}: but the cascade must stay closed");
            $this->assertFalse($state['search'], "{$component}: and search with it");
        }
    }

    /** With the workflow in scope it does switch on — the wiring is real, not decorative. @test */
    public function the_wiring_works_once_the_workflow_is_listed(): void
    {
        $this->openEveryGate();

        foreach (self::TENANT_SURFACES as $component) {
            $state = $this->bootAs($component, 'tenant');

            $this->assertTrue($state['cascade'], "{$component}: cascade must switch on");
            $this->assertTrue($state['search'], "{$component}: search must switch on with it");
        }
    }

    /** Search rides on the cascade: cascade off means search off, whatever its own flag says. @test */
    public function search_cannot_outlive_the_cascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => false,
            'criteria_location_dna.geography_search_enabled'    => true,
            'criteria_location_dna.geography_cascade_workflows' => ['create_tenant'],
        ]);

        foreach (self::TENANT_SURFACES as $component) {
            $this->assertFalse($this->bootAs($component, 'tenant')['search'], $component);
        }
    }

    /**
     * The boot order is right in the source.
     *
     * `bootGeographySearch()` reads `$geoCascadeEnabled`, which `bootGeographyCascade()` sets.
     * Reversed, search resolves false on first render and true on every request after — a bug that
     * only appears on the second keystroke, which no assertion on a single boot would catch.
     *
     * @test
     */
    public function search_boots_after_the_cascade_at_every_site(): void
    {
        foreach (self::TENANT_SURFACES as $component) {
            $source = $this->sourceOf($component);

            $cascadeBoots = [];
            $offset = 0;
            while (($pos = strpos($source, 'bootGeographyCascade($this->geographyCascadeWorkflow())', $offset)) !== false) {
                $cascadeBoots[] = $pos;
                $offset = $pos + 1;
            }

            $searchBoots = [];
            $offset = 0;
            while (($pos = strpos($source, 'bootGeographySearch()', $offset)) !== false) {
                $searchBoots[] = $pos;
                $offset = $pos + 1;
            }

            $this->assertNotEmpty($cascadeBoots, "{$component}: no cascade boot found");
            $this->assertSameSize(
                $cascadeBoots,
                $searchBoots,
                "{$component}: every cascade boot must be paired with a search boot"
            );

            foreach ($cascadeBoots as $i => $cascadePos) {
                $this->assertGreaterThan(
                    $cascadePos,
                    $searchBoots[$i],
                    "{$component}: search boot #{$i} must follow its cascade boot"
                );
            }
        }
    }

    /**
     * The projection precedes the pre-validation hydrate on both surfaces.
     *
     * Without this ordering the hydrate re-reads the widget's server-seeded blob and the
     * required-field rules judge the STORED geography rather than the edited selection. A
     * stored-data assertion would not catch it, because the persist-time projection also runs.
     *
     * @test
     */
    public function the_projection_precedes_the_pre_validation_hydrate(): void
    {
        foreach (self::TENANT_SURFACES as $component) {
            $source = $this->sourceOf($component);

            // Matched as full statements, not bare method names: both components carry a
            // class-level comment mentioning `hydrateDiscreteLocationFromBlob()` hundreds of lines
            // above any call, and a loose needle finds that comment and compares against it.
            $projection = strpos($source, '$this->applyGeographyCascadeToPayload();');
            $hydrate    = strpos($source, '$this->hydrateDiscreteLocationFromBlob();');

            $this->assertNotFalse($projection, "{$component}: no projection call");
            $this->assertNotFalse($hydrate, "{$component}: no hydrate call");
            $this->assertLessThan(
                $hydrate,
                $projection,
                "{$component}: the projection must run before the first hydrate"
            );
        }
    }

    /**
     * The create surface projects at BOTH seams; the edit surface needs only one.
     *
     * `TenantOfferListing` has a `saveAllMetadata()` that `saveDraft()` reaches without going
     * through `store()`, so a projection only on the validation path would skip every draft save.
     * `TenantOfferListingEdit` has no such method — `update()` validates and persists inline, and
     * both draft methods delegate to it — so one call covers everything.
     *
     * @test
     */
    public function every_persist_entry_point_is_covered(): void
    {
        $create = $this->sourceOf(TenantOfferListing::class);
        $this->assertSame(
            2,
            substr_count($create, '$this->applyGeographyCascadeToPayload();'),
            'TenantOfferListing needs a projection at store() AND at saveAllMetadata().'
        );
        $this->assertStringContainsString('protected function saveAllMetadata', $create);

        $edit = $this->sourceOf(TenantOfferListingEdit::class);
        $this->assertSame(
            1,
            substr_count($edit, '$this->applyGeographyCascadeToPayload();'),
            'TenantOfferListingEdit has a single seam; a second call would be dead weight.'
        );
        $this->assertStringNotContainsString(
            'protected function saveAllMetadata',
            $edit,
            'If this component grows a saveAllMetadata(), the single-seam reasoning above expires.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE ZIP MIRROR STAYS SHUT
    // ═════════════════════════════════════════════════════════════════════════

    /** `create_tenant` is not a mirroring workflow, with every gate open. @test */
    public function create_tenant_is_not_a_zip_mirroring_workflow(): void
    {
        $this->openEveryGate();

        foreach (self::TENANT_SURFACES as $component) {
            $this->assertFalse(
                $this->bootAs($component, 'tenant')['mirrors'],
                "{$component}: create_tenant must never mirror ZIPs while the trait is frozen"
            );
        }
    }

    /**
     * The cascade cannot write `$zipCodes`, which is the property the legacy meta is saved from.
     *
     * This is the whole safety argument for wiring Create Tenant, asserted on the real component
     * rather than inferred from the allowlist: a populated property survives a projection sync.
     *
     * @test
     */
    public function the_cascade_cannot_write_the_zip_codes_property(): void
    {
        $this->openEveryGate();

        $reflection = new ReflectionClass(TenantOfferListing::class);
        $component  = $reflection->newInstanceWithoutConstructor();

        $component->user_type = 'tenant';
        $component->zipCodes  = ['33701', '33702'];

        $workflow = $reflection->getMethod('geographyCascadeWorkflow');
        $workflow->setAccessible(true);
        $boot = $reflection->getMethod('bootGeographyCascade');
        $boot->setAccessible(true);
        $boot->invoke($component, $workflow->invoke($component));

        $this->assertTrue($component->geoCascadeEnabled, 'precondition: the cascade is on');

        $sync = $reflection->getMethod('syncDiscreteLocationProps');
        $sync->setAccessible(true);
        $sync->invoke($component);

        $this->assertSame(
            ['33701', '33702'],
            $component->zipCodes,
            'The cascade emptied the legacy ZIP property — the exact failure that blocked this workflow.'
        );
    }

    /** The allowlist itself is untouched. @test */
    public function the_zip_mirror_allowlist_is_unchanged(): void
    {
        $this->assertStringContainsString(
            "private const ZIP_MIRROR_WORKFLOWS = ['hire_tenant'];",
            (string) file_get_contents(base_path('app/Http/Livewire/Concerns/HasGeographyCascade.php'))
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · NO STORAGE FORMAT CHANGE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The projection states the four canonical keys and merges into the existing payload.
     *
     * A rebuild would destroy the polygons, radius searches, flexible flag and notes that belong to
     * the map widget and that the cascade knows nothing about.
     *
     * @test
     */
    public function the_projection_merges_the_four_canonical_keys_and_nothing_else(): void
    {
        $this->openEveryGate();

        $reflection = new ReflectionClass(TenantOfferListing::class);
        $component  = $reflection->newInstanceWithoutConstructor();

        $component->user_type = 'tenant';
        $component->location_dna_preferences_json = json_encode([
            'polygons'          => [['id' => 1]],
            'radius_searches'   => [['miles' => 5]],
            'flexible_location' => true,
            'location_notes'    => 'near the water',
        ]);

        $workflow = $reflection->getMethod('geographyCascadeWorkflow');
        $workflow->setAccessible(true);
        $boot = $reflection->getMethod('bootGeographyCascade');
        $boot->setAccessible(true);
        $boot->invoke($component, $workflow->invoke($component));

        $apply = $reflection->getMethod('applyGeographyCascadeToPayload');
        $apply->setAccessible(true);
        $apply->invoke($component);

        $payload = json_decode($component->location_dna_preferences_json, true);

        // The widget's own keys survive.
        $this->assertSame([['id' => 1]], $payload['polygons']);
        $this->assertSame([['miles' => 5]], $payload['radius_searches']);
        $this->assertTrue($payload['flexible_location']);
        $this->assertSame('near the water', $payload['location_notes']);

        // Exactly the four canonical geography keys are added — no fifth.
        $this->assertSame(
            ['polygons', 'radius_searches', 'flexible_location', 'location_notes',
             'state', 'counties', 'cities', 'zip_codes'],
            array_keys($payload)
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · EVERY OTHER SURFACE IS UNAFFECTED
    // ═════════════════════════════════════════════════════════════════════════

    /** The Buyer components still claim create_buyer and are otherwise untouched. @test */
    public function the_buyer_surfaces_are_unaffected(): void
    {
        $this->openEveryGate();

        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $component) {
            $this->assertSame('create_buyer', $this->bootAs($component, 'buyer')['workflow'], $component);
            $this->assertNull($this->bootAs($component, 'tenant')['workflow'], $component);
            $this->assertFalse(
                $this->bootAs($component, 'buyer')['mirrors'],
                "{$component}: the Buyer family must never mirror ZIPs"
            );
        }
    }

    /** The dedicated Seller and Landlord Offer components carry no cascade at all. @test */
    public function the_seller_and_landlord_offer_components_carry_no_cascade(): void
    {
        foreach ([SellerOfferListing::class, LandlordOfferListing::class] as $component) {
            $source = $this->sourceOf($component);

            $this->assertStringNotContainsString('HasGeographyCascade', $source, $component);
            $this->assertStringNotContainsString('HasGeographySearch', $source, $component);
            $this->assertStringNotContainsString('geographyCascadeWorkflow', $source, $component);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6 · NOTHING IS ENABLED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * T2 wires; it does not enable. Both halves are asserted because either alone would be a
     * different — and unsafe — state.
     *
     * @test
     */
    public function the_workflow_is_neither_listed_nor_rendered(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertNotContains(
            'create_tenant',
            $config['geography_cascade_workflows'],
            'T2 must not enable the workflow.'
        );
        $this->assertFalse($config['geography_cascade_enabled'], 'The master gate must still ship off.');
    }

    /**
     * The tab has opted in, and that is the SAFE half of the pair.
     *
     * This assertion used to require the opposite — that the tab carried no cascade surface — which
     * was correct while T2 stood alone and the view opt-in had not landed. T3 added it, in that
     * order deliberately: a workflow listed in config whose tab has NOT opted in states four empty
     * geography keys over stored data, so the surface must precede the scope entry, never follow it.
     *
     * What still holds, and is asserted above, is that the workflow is not listed. Surface-without-
     * workflow is inert and reversible; workflow-without-surface is data loss.
     *
     * @test
     */
    public function the_tab_has_opted_in_while_the_workflow_stays_unlisted(): void
    {
        $tab = (string) file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php'
        ));

        $this->assertStringContainsString('ldnaGeographyCascade', $tab, 'T3 opted the tab in.');
        $this->assertStringContainsString("@if (\$geoCascadeEnabled ?? false)", $tab, 'and did so behind the flag.');

        $config = require base_path('config/criteria_location_dna.php');
        $this->assertNotContains('create_tenant', $config['geography_cascade_workflows']);
    }
}
