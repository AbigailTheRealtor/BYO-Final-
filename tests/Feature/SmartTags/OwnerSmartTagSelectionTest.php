<?php

namespace Tests\Feature\SmartTags;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use App\Support\SmartTags\OwnerSmartTagPanel;
use App\Support\SmartTags\OwnerSmartTagSelection;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\TestCase;

/**
 * Seller/Landlord owner selection — the "Property Features" picker.
 *
 * Publishes through the REAL wizards wherever the question is "does the form do
 * this", because a test that called the lifecycle directly would pass just as
 * well with the picker wired to nothing. The finer writer questions (a structured
 * answer arriving after a selection, deselection semantics) go through the seam
 * instance, which is what the form calls anyway.
 *
 * NOTHING HERE ASSERTS A HARD-CODED FEATURE LIST. Every expectation is derived
 * from the canonical taxonomy, so adding a tag to config/smart_tags.php cannot
 * make this file wrong — which is the same reason the picker projects rather
 * than lists.
 */
class OwnerSmartTagSelectionTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;
    use WiresSmartTags;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();
    }

    protected function tearDown(): void
    {
        SmartTagTaxonomy::flush();
        parent::tearDown();
    }

    /* =====================================================================
     * 1–7. Every owner context is offered its own tags
     * ===================================================================== */

    /**
     * @test
     * @dataProvider ownerContexts
     */
    public function each_owner_context_is_offered_its_own_projected_tags(
        string $listingType,
        string $propertyType,
        string $expectedContext,
        array $mustOffer,
        array $mustNotOffer,
    ): void {
        $type = SmartTagListingType::from($listingType);
        $panel = SmartTagLifecycle::ownerPanel($type, $propertyType, null, []);

        $this->assertTrue($panel->available, "No picker for {$propertyType}.");
        $this->assertSame($expectedContext, $panel->context->value);

        $offered = $this->offeredKeys($panel);

        foreach ($mustOffer as $key) {
            $this->assertContains($key, $offered, "{$expectedContext} should offer {$key}.");
        }

        foreach ($mustNotOffer as $key) {
            $this->assertNotContains($key, $offered, "{$expectedContext} must not offer {$key}.");
        }
    }

    public static function ownerContexts(): array
    {
        return [
            // Seller
            'seller residential' => ['seller_agent', 'Residential', 'residential.sale',
                ['quartz_countertops', 'updated_kitchen', 'walk_in_closet', 'fenced_yard', 'impact_windows', 'private_pool', 'waterfront', 'garage'],
                ['loading_dock', 'cleared_land', 'turnkey_business', 'pets_allowed']],
            'seller income' => ['seller_agent', 'Income', 'income.sale',
                ['separate_electric_meters', 'on_site_laundry', 'value_add_opportunity'],
                ['turnkey_business', 'cleared_land']],
            'seller commercial' => ['seller_agent', 'Commercial', 'commercial.sale',
                ['loading_dock', 'three_phase_power', 'reception_area', 'private_offices', 'overhead_doors'],
                ['private_pool', 'cleared_land', 'walk_in_closet']],
            'seller business' => ['seller_agent', 'Business', 'business.sale',
                ['turnkey_business', 'inventory_included', 'loading_dock'],
                ['private_pool', 'cleared_land']],
            'seller land' => ['seller_agent', 'Vacant Land', 'land.sale',
                ['cleared_land', 'fenced_lot', 'well_water', 'septic_system', 'pasture'],
                ['loading_dock', 'private_pool', 'updated_kitchen']],
            // Landlord
            'landlord residential' => ['landlord_agent', 'Residential Property', 'residential.lease',
                ['furnished', 'community_pool', 'in_unit_laundry', 'internet_included', 'pets_allowed', 'lawn_care_included', 'garage'],
                ['loading_dock', 'cleared_land', 'turnkey_business']],
            'landlord commercial' => ['landlord_agent', 'Commercial Property', 'commercial.lease',
                ['loading_dock', 'conference_room', 'private_offices', 'reception_area', 'fiber_internet', 'covered_parking'],
                ['private_pool', 'cleared_land', 'pets_allowed']],
        ];
    }

    /* =====================================================================
     * 8–10. Projection, grouping, and the two filters
     * ===================================================================== */

    /** @test */
    public function the_options_are_the_taxonomy_projected_onto_the_owner_surface(): void
    {
        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, 'Residential', null, []);
        $context = SmartTagContext::ResidentialSale;

        $expected = array_keys(SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_OWNER));
        $offered = $this->offeredKeys($panel);

        sort($expected);
        $sorted = $offered;
        sort($sorted);

        $this->assertSame($expected, $sorted,
            'The picker must offer exactly the taxonomy projection — no extra list, no omissions.');
        $this->assertSame(count($offered), count(array_unique($offered)), 'A tag was offered twice.');
    }

    /** @test */
    public function options_are_grouped_by_taxonomy_category_in_display_order(): void
    {
        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, 'Residential', null, []);

        $categories = SmartTagTaxonomy::categories();
        $groupKeys = array_column($panel->groups, 'key');

        $this->assertNotEmpty($groupKeys);
        $this->assertSame($groupKeys, array_unique($groupKeys), 'A category was rendered twice.');

        foreach ($panel->groups as $group) {
            $this->assertArrayHasKey($group['key'], $categories, "{$group['key']} is not a declared category.");
            $this->assertSame((string) $categories[$group['key']]['label'], $group['label']);
            $this->assertNotEmpty($group['options'], 'An empty category was rendered.');

            foreach ($group['options'] as $option) {
                $this->assertSame($group['key'], SmartTagTaxonomy::get($option['key'])->category);
                // Readable labels, never canonical keys.
                $this->assertNotSame($option['key'], $option['label']);
            }
        }

        $declaredOrder = array_values(array_intersect(array_keys($categories), $groupKeys));
        $this->assertSame($declaredOrder, $groupKeys, 'Categories are not in taxonomy display order.');
    }

    /**
     * There is no non-owner-selectable tag in today's taxonomy, so the guarantee
     * is proved against one that is declared for this test rather than asserted
     * vacuously — and it is proved at the WRITE as well as in the picker.
     *
     * @test
     */
    public function a_tag_that_is_not_owner_selectable_is_neither_offered_nor_writable(): void
    {
        $this->declareTaxonomyOverride('quartz_countertops', ['owner_selectable' => false]);

        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, 'Residential', null, ['quartz_countertops']);

        $this->assertNotContains('quartz_countertops', $this->offeredKeys($panel));
        $this->assertNotContains('quartz_countertops', $panel->selected);

        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);

        app(SmartTagLifecycle::class)->saveOwnerSelectionsSilently(
            $listing, ['quartz_countertops', 'kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS
        );

        $this->assertSame(['kitchen_island'], $this->manualEvidence('seller_agent', $listing->id));
    }

    /** @test */
    public function a_pending_compliance_review_tag_is_neither_offered_nor_writable(): void
    {
        // gated_community is declared but pending review in the shipped taxonomy.
        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, 'Residential', null, ['gated_community']);

        $this->assertNotContains('gated_community', $this->offeredKeys($panel));
        $this->assertNotContains('gated_community', $panel->selected);

        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);

        app(SmartTagLifecycle::class)->saveOwnerSelectionsSilently(
            $listing, ['gated_community'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS
        );

        $this->assertSame([], $this->manualEvidence('seller_agent', $listing->id));
    }

    /* =====================================================================
     * 11–14. What a submitted selection may not be
     * ===================================================================== */

    /** @test */
    public function publishing_with_invalid_wrong_context_and_prohibited_keys_stores_only_the_valid_ones(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Vacant Land', [
            'smart_tag_selections' => [
                'cleared_land',          // valid here
                'loading_dock',          // a real key, wrong context
                'gourmet_kitchen',       // never existed
                'Custom Awesome Tag',    // free text
                'family_friendly',       // a prohibited concept
                'gated_community',       // pending compliance review
                'senior_community_55',   // a compliance gate, not a tag
            ],
        ]);

        $this->assertSame(['cleared_land'], $this->manualEvidence('seller_agent', $listing->id));
    }

    /** @test */
    public function no_fair_housing_concept_can_reach_the_picker_or_the_store(): void
    {
        $forbidden = ['family_friendly', 'families_welcome', 'good_schools', 'safe_neighborhood',
                      'quiet_neighborhood', 'senior_community_55', 'leasing_55_plus', 'age_62_plus',
                      'young_professionals', 'students_welcome', 'walking_distance_to_shops'];

        foreach (self::ownerContexts() as $case) {
            [$listingType, $propertyType] = $case;
            $offered = $this->offeredKeys(
                SmartTagLifecycle::ownerPanel(SmartTagListingType::from($listingType), $propertyType, null, [])
            );

            foreach ($forbidden as $key) {
                $this->assertNotContains($key, $offered, "{$propertyType} offered the prohibited concept {$key}.");
            }
        }

        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);

        app(SmartTagLifecycle::class)->saveOwnerSelectionsSilently(
            $listing, $forbidden, $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS
        );

        $this->assertSame([], $this->manualEvidence('seller_agent', $listing->id));
    }

    /**
     * Proximity belongs to Location DNA and stays out of Smart Tags entirely, so
     * the picker cannot become a second place to state where a property is.
     *
     * @test
     */
    public function no_offered_label_describes_people_or_proximity(): void
    {
        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::LandlordAgent, 'Residential Property', null, []);

        foreach ($panel->groups as $group) {
            foreach ($group['options'] as $option) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(minutes? (from|to)|miles? (from|to)|walking distance|close to|near(by)?|neighborh?ood|family|families|students?|professionals?|seniors?|55\+|62\+)\b/i',
                    $option['label'],
                    "The owner picker offers a label describing people or proximity: {$option['label']}"
                );
            }
        }
    }

    /* =====================================================================
     * 15. Authorization
     * ===================================================================== */

    /** @test */
    public function only_the_listing_owner_may_change_its_manual_tags(): void
    {
        $owner = $this->makeOwner();
        $intruder = $this->makeOwner('agent');
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $lifecycle = app(SmartTagLifecycle::class);

        $lifecycle->saveOwnerSelectionsSilently($listing, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $this->assertSame(['kitchen_island'], $this->manualEvidence('seller_agent', $listing->id));

        foreach ([$intruder, null] as $actor) {
            $result = $lifecycle->saveOwnerSelectionsSilently($listing, ['wet_bar'], $actor, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

            $this->assertNotNull($result);
            $this->assertFalse($result->saved);
            $this->assertSame(['kitchen_island'], $this->manualEvidence('seller_agent', $listing->id),
                'A non-owner changed another listing\'s tags.');
        }
    }

    /** @test */
    public function a_hire_agent_row_and_an_archived_listing_are_refused(): void
    {
        $owner = $this->makeOwner();
        $lifecycle = app(SmartTagLifecycle::class);

        $hire = $this->sellerListing($owner, ['property_type' => 'Residential'], 'hire_agent');
        $result = $lifecycle->saveOwnerSelectionsSilently($hire, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $this->assertFalse($result->saved);
        $this->assertSame([], $this->manualEvidence('seller_agent', $hire->id));

        $archived = $this->sellerListing($owner, ['property_type' => 'Residential'], 'offer_listing', ['is_archived' => 1]);
        $result = $lifecycle->saveOwnerSelectionsSilently($archived, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $this->assertFalse($result->saved);
        $this->assertSame([], $this->manualEvidence('seller_agent', $archived->id));
    }

    /** @test */
    public function the_owner_surface_does_not_exist_for_buyer_or_tenant_components(): void
    {
        foreach ([
            \App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class,
            \App\Http\Livewire\OfferListing\Tenant\TenantOfferListing::class,
        ] as $component) {
            $this->assertFalse(method_exists($component, 'ownerSmartTagPanel'),
                "{$component} must not carry the Seller/Landlord owner Smart Tag surface.");
            $this->assertFalse(property_exists($component, 'smart_tag_selections'));
        }
    }

    /* =====================================================================
     * 16–18. Save, restore, deselect, provenance
     * ===================================================================== */

    /** @test */
    public function publishing_a_seller_listing_saves_the_owners_selection_and_edit_restores_it(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->publishSeller($owner, 'Residential', [
            'smart_tag_selections' => ['kitchen_island', 'walk_in_closet'],
        ]);

        $this->assertSame(['kitchen_island', 'walk_in_closet'], $this->manualEvidence('seller_agent', $listing->id));

        // The owner's ticks are an ordinary listing answer, so the Edit wizard
        // repopulates them the way it repopulates interior_features.
        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $listing->id)
            ->assertSet('smart_tag_selections', ['kitchen_island', 'walk_in_closet']);
    }

    /** @test */
    public function publishing_a_landlord_listing_saves_the_owners_selection_and_edit_restores_it(): void
    {
        $owner = $this->landlordOwner();
        $listing = $this->publishLandlord($owner, 'Residential Property', [
            'smart_tag_selections' => ['furnished', 'internet_included'],
        ]);

        $this->assertSame(['furnished', 'internet_included'], $this->manualEvidence('landlord_agent', $listing->id));

        Livewire::actingAs($owner)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $listing->id)
            ->assertSet('smart_tag_selections', ['furnished', 'internet_included']);
    }

    /**
     * DESELECTION MEANS UNKNOWN, NEVER ABSENT. Only a structured source reading
     * an explicit "No" may record absence; an owner taking a tick back is simply
     * no longer asserting the feature.
     *
     * @test
     */
    public function deselecting_removes_the_manual_assertion_and_never_records_absent(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $lifecycle = app(SmartTagLifecycle::class);

        $lifecycle->saveOwnerSelectionsSilently($listing, ['kitchen_island', 'wet_bar'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $this->assertSame(['kitchen_island', 'wet_bar'], $this->manualEvidence('seller_agent', $listing->id));

        $lifecycle->saveOwnerSelectionsSilently($listing, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

        $this->assertSame(['kitchen_island'], $this->manualEvidence('seller_agent', $listing->id));

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('tag_key', 'wet_bar')->count(),
            'Deselecting left evidence behind.');

        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('tag_key', 'wet_bar')->count(),
            'A deselected tag must resolve to UNKNOWN — the absence of a row — not to "absent".');

        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('state', 'absent')->count(),
            'An owner deselection invented an "absent" answer.');
    }

    /** @test */
    public function every_manual_change_appends_an_attributed_event(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $lifecycle = app(SmartTagLifecycle::class);

        $lifecycle->saveOwnerSelectionsSilently($listing, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $lifecycle->saveOwnerSelectionsSilently($listing, [], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

        $events = SmartTagManualEvent::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->orderBy('id')->get();

        $this->assertSame(['selected', 'deselected'], $events->pluck('action')->all());

        foreach ($events as $event) {
            $this->assertSame('kitchen_island', $event->tag_key);
            $this->assertSame((int) $owner->id, $event->actor_user_id);
            $this->assertSame('listing_owner', $event->actor_role);
        }

        // Append-only: the history of a withdrawn selection survives the withdrawal.
        $this->assertSame(2, $events->count());
    }

    /* =====================================================================
     * 19–21. Precedence, and the authoritative structured fields
     * ===================================================================== */

    /**
     * Manual owner evidence outranks the description parser, so a tick is not
     * cancelled by prose and a tick's removal does not resurrect one.
     *
     * @test
     */
    public function manual_evidence_outranks_description_derived_evidence(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'additional_details' => 'The kitchen has a large island and quartz counters throughout.',
        ]);

        $lifecycle = app(SmartTagLifecycle::class);
        $lifecycle->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);
        $lifecycle->saveOwnerSelectionsSilently($listing, ['kitchen_island'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

        $assignment = SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('tag_key', 'kitchen_island')->first();

        $this->assertNotNull($assignment);
        $this->assertSame('present', $assignment->state);
        $this->assertSame('manual_listing_owner', $assignment->winning_source,
            'Manual owner evidence must outrank the description parser.');

        // And re-deriving the description does not erase the owner's selection.
        $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);
        $this->assertContains('kitchen_island', $this->manualEvidence('seller_agent', $listing->id));
    }

    /**
     * A tag the listing's own Yes/No field already answers is not a checkbox.
     * Pool = Yes is shown as already included; Pool = No refuses the tick.
     *
     * @test
     */
    public function a_tag_the_property_details_already_answer_is_shown_rather_than_offered(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, [
            'property_type' => 'Residential',
            'pool_needed'   => 'Yes',
            'pool_type'     => json_encode(['private' => true]),
        ]);

        $panel = SmartTagLifecycle::ownerPanel(
            SmartTagListingType::SellerAgent, 'Residential', (int) $listing->id, []
        );

        $this->assertNotContains('private_pool', $this->offeredKeys($panel),
            'A tag the form already answers must not be a second checkbox.');
        $this->assertContains('private_pool', array_column($panel->detected, 'key'),
            'A feature the listing already establishes must be shown as already included.');
    }

    /** @test */
    public function a_structured_no_cannot_be_overridden_by_a_manual_selection(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, [
            'property_type' => 'Residential',
            'pool_needed'   => 'No',
        ]);

        $panel = SmartTagLifecycle::ownerPanel(
            SmartTagListingType::SellerAgent, 'Residential', (int) $listing->id, ['private_pool']
        );

        $this->assertNotContains('private_pool', $this->offeredKeys($panel));
        $this->assertNotContains('private_pool', $panel->selected);

        // "No pool" is not a feature: it is answered, so it is not offered, and it
        // is NOT printed under a heading of things the listing has.
        $this->assertNotContains('private_pool', array_column($panel->detected, 'key'));

        $lifecycle = app(SmartTagLifecycle::class);
        $lifecycle->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);
        $lifecycle->saveOwnerSelectionsSilently($listing, ['private_pool'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

        $this->assertSame([], $this->manualEvidence('seller_agent', $listing->id));

        $assignment = SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('tag_key', 'private_pool')->first();

        $this->assertNotNull($assignment);
        $this->assertSame('absent', $assignment->state,
            'The structured "No" must still be what the listing says about a pool.');
    }

    /**
     * The owner ticked it first, then answered the Yes/No field with "No". The
     * form's answer wins and the stale manual assertion is pruned — with an event
     * that says why, rather than silently.
     *
     * @test
     */
    public function a_selection_is_pruned_when_the_property_details_later_answer_it(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $lifecycle = app(SmartTagLifecycle::class);

        $lifecycle->saveOwnerSelectionsSilently($listing, ['private_pool'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);
        $this->assertSame(['private_pool'], $this->manualEvidence('seller_agent', $listing->id));

        $listing->saveMeta('pool_needed', 'No');

        $lifecycle->saveOwnerSelectionsSilently($listing->fresh(), ['private_pool'], $owner, SmartTagTelemetry::ENTRY_SELLER_OWNER_TAGS);

        $this->assertSame([], $this->manualEvidence('seller_agent', $listing->id));
        $this->assertSame(1, SmartTagManualEvent::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('action', 'pruned_answered_by_property_details')->count());
    }

    /* =====================================================================
     * 22–23. The boundaries this phase must not cross
     * ===================================================================== */

    /** @test */
    public function the_wizard_surface_writes_no_smart_tag_row_of_its_own(): void
    {
        $sources = [
            'app/Http/Livewire/Concerns/HasOwnerSmartTags.php',
            'resources/views/livewire/offer-listing/shared/_owner-smart-tags.blade.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        ];

        foreach ($sources as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            foreach (['SmartTagEvidence', 'SmartTagAssignment', 'SmartTagManualEvent', 'SmartTagDerivationState',
                      'smart_tag_evidence', 'smart_tag_assignments', 'smart_tag_manual_events'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source,
                    "{$relative} reaches a Smart Tag table directly — the write belongs to ManualSmartTagWriter, behind SmartTagLifecycle.");
            }
        }
    }

    /** @test */
    public function no_surface_file_hard_codes_a_feature_list(): void
    {
        // The picker projects the taxonomy. A literal list in a template is the
        // duplicate vocabulary the governance forbids.
        $blade = (string) file_get_contents(base_path('resources/views/livewire/offer-listing/shared/_owner-smart-tags.blade.php'));

        foreach (array_keys(SmartTagTaxonomy::all()) as $key) {
            $this->assertStringNotContainsString("'{$key}'", $blade, "The picker template names the tag {$key}.");
            $this->assertStringNotContainsString("\"{$key}\"", $blade, "The picker template names the tag {$key}.");
        }
    }

    /* =====================================================================
     * The activation gate
     * ===================================================================== */

    /** @test */
    public function with_the_gate_closed_nothing_renders_and_nothing_is_written(): void
    {
        $this->disableSmartTags();

        $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, 'Residential', null, ['kitchen_island']);

        $this->assertFalse($panel->available);
        $this->assertSame(OwnerSmartTagPanel::REASON_DISABLED, $panel->unavailableReason);
        $this->assertSame([], $panel->groups);
        $this->assertSame([], $panel->selected);

        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', [
            'smart_tag_selections' => ['kitchen_island'],
        ]);

        $this->assertSame(0, SmartTagEvidence::query()->count(), 'A closed gate wrote evidence.');
        $this->assertSame(0, SmartTagManualEvent::query()->count(), 'A closed gate wrote an event.');

        // The owner's answer is still remembered as ordinary form state, so
        // opening the gate later does not lose what they ticked.
        $this->assertSame(['kitchen_island'],
            OwnerSmartTagSelection::decode($listing->info(OwnerSmartTagSelection::META_KEY)));
    }

    /** @test */
    public function a_listing_with_no_property_type_yet_is_offered_nothing_and_says_so(): void
    {
        foreach ([null, '', 'Houseboat'] as $propertyType) {
            $panel = SmartTagLifecycle::ownerPanel(SmartTagListingType::SellerAgent, $propertyType, null, ['kitchen_island']);

            $this->assertFalse($panel->available);
            $this->assertSame(OwnerSmartTagPanel::REASON_NO_CONTEXT, $panel->unavailableReason);
            $this->assertSame([], $panel->groups);
        }
    }

    /* =====================================================================
     * The rendered surface
     * ===================================================================== */

    /**
     * @test
     * @dataProvider wizardComponents
     */
    public function the_picker_renders_in_the_wizard_and_disappears_with_the_gate(string $component, string $role, string $propertyType): void
    {
        $owner = $role === 'seller' ? $this->sellerOwner() : $this->landlordOwner();

        $on = Livewire::actingAs($owner)->test($component)
            ->set('property_type', $propertyType)
            ->set('smart_tag_selections', ['kitchen_island'])
            ->assertSee('Property Features')
            ->assertSee('Add more features')
            ->assertSeeHtml('id="owner-smart-tag-kitchen_island"')
            ->assertSeeHtml('wire:model.defer="smart_tag_selections"');

        // The ticked box comes back ticked. Livewire does not set a checkbox's
        // initial state from the component data, so this is the half of Edit
        // restoration that only the rendered markup can prove.
        $this->assertMatchesRegularExpression(
            '/value="kitchen_island"\s+checked/',
            $on->lastRenderedDom,
            'A selected feature did not render as checked.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="breakfast_bar"\s+checked/',
            $on->lastRenderedDom,
            'An unselected feature rendered as checked.'
        );

        $this->disableSmartTags();

        Livewire::actingAs($owner)->test($component)
            ->set('property_type', $propertyType)
            ->set('smart_tag_selections', ['kitchen_island'])
            // A closed gate must take the CONTROL away too: a rendered checkbox
            // whose save does nothing is worse than no checkbox.
            ->assertDontSee('Add more features')
            ->assertDontSeeHtml('owner-smart-tag-kitchen_island')
            ->assertDontSeeHtml('wire:model.defer="smart_tag_selections"');
    }

    public static function wizardComponents(): array
    {
        return [
            'seller create'   => [\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class, 'seller', 'Residential'],
            'seller edit'     => [SellerOfferListingEdit::class, 'seller', 'Residential'],
            'landlord create' => [\App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class, 'landlord', 'Residential Property'],
            'landlord edit'   => [LandlordOfferListingEdit::class, 'landlord', 'Residential Property'],
        ];
    }

    /**
     * The first paint after a listing is loaded already knows what the listing's
     * own fields answer.
     *
     * `$listingId` is the durable answer, but the create wizards' loadDraft()
     * does not set it, so a picker that read only that property would offer a
     * second Pool checkbox on the one render the owner actually looks at and
     * correct itself a request later.
     *
     * @test
     */
    public function loading_a_listing_immediately_shows_what_its_own_fields_already_answer(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->sellerListing($owner, [
            'property_type' => 'Residential',
            'pool_needed'   => 'Yes',
            'pool_type'     => json_encode(['private' => true]),
            'waterfront'    => 'Yes',
        ], 'offer_listing', ['user_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $listing->id)
            ->assertSee('Already included from your listing details')
            ->assertSee('Private Pool')
            ->assertDontSeeHtml('id="owner-smart-tag-private_pool"')
            ->assertDontSeeHtml('id="owner-smart-tag-waterfront"');
    }

    /**
     * The Seller partial is also included by the Buyer and Tenant wizards, and
    /**
     * The Seller partial is also included by the Buyer and Tenant wizards, and
     * the Landlord partial by the Tenant and Buyer ones. Those components
     * describe a SEARCH, not a property, and must render no owner surface.
     *
     * @test
     * @dataProvider seekerComponents
     */
    public function the_seeker_wizards_render_no_owner_picker(string $component, string $userType): void
    {
        Livewire::actingAs($this->makeOwner($userType))->test($component)
            ->assertDontSee('Add more features')
            ->assertDontSeeHtml('owner-smart-tag-')
            ->assertDontSeeHtml('smart_tag_selections');
    }

    public static function seekerComponents(): array
    {
        return [
            'buyer'  => [\App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class, 'buyer'],
            'tenant' => [\App\Http\Livewire\OfferListing\Tenant\TenantOfferListing::class, 'tenant'],
        ];
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    /** @return string[] */
    private function offeredKeys(OwnerSmartTagPanel $panel): array
    {
        $keys = [];
        foreach ($panel->groups as $group) {
            foreach ($group['options'] as $option) {
                $keys[] = $option['key'];
            }
        }

        return $keys;
    }

    /** @return string[] manual_listing_owner evidence keys */
    private function manualEvidence(string $listingType, int $listingId): array
    {
        return $this->evidenceFor($listingType, $listingId, 'manual_listing_owner');
    }

    /**
     * Replace one tag's declaration for this test only.
     *
     * @param array<string, mixed> $changes
     */
    private function declareTaxonomyOverride(string $key, array $changes): void
    {
        $taxonomy = SmartTagConfig::taxonomy();
        $taxonomy['tags'][$key] = array_merge($taxonomy['tags'][$key], $changes);

        // SmartTagTaxonomy::flush() also flushes SmartTagConfig, which reads the
        // container before the file — so the override is what the taxonomy sees.
        SmartTagTaxonomy::flush();
        config(['smart_tags' => $taxonomy]);
        SmartTagTaxonomy::flush();
    }
}
