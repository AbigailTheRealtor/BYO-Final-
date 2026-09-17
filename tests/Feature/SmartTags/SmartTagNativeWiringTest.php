<?php

namespace Tests\Feature\SmartTags;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\Feature\SmartTags\Doubles\ThrowingDerivationService;
use Tests\Feature\SmartTags\Doubles\ThrowingSmartTagLifecycle;
use Tests\TestCase;

/**
 * Phase 2 — native Seller and Landlord Offer Listing wiring.
 *
 * These publish through the REAL Livewire wizards, so what is asserted is what a
 * user's submit actually does: the same validation, the same saveAllMetadata,
 * the same ordering. A test that called the lifecycle directly would pass just
 * as well if the hook had been placed before the meta was written.
 */
class SmartTagNativeWiringTest extends TestCase
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

    /* =====================================================================
     * Seller — the five sale contexts
     * ===================================================================== */

    /** @test */
    public function publishing_a_residential_seller_listing_derives_tags_in_the_residential_sale_context(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', [
            'waterfront' => 'Yes',
        ]);

        $assignments = SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')
            ->where('listing_id', $listing->id)
            ->get();

        $this->assertGreaterThan(0, $assignments->count(), 'Publishing derived no Smart Tags.');
        $this->assertSame(['residential.sale'], $assignments->pluck('context')->unique()->values()->all());
        $this->assertContains('waterfront', $this->presentTags('seller_agent', $listing->id));
    }

    /**
     * The remaining four Seller contexts. Asserted on the CONTEXT rather than on
     * specific tags, because which tags a bare listing yields is the taxonomy's
     * business and is covered by the Phase 1 deriver tests — what Phase 2 must
     * prove is that publishing each property type reaches derivation at all and
     * lands in the right context.
     *
     * @test
     * @dataProvider sellerContexts
     */
    public function publishing_each_seller_property_type_lands_in_its_own_context(string $propertyType, string $expectedContext): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), $propertyType);

        $state = \App\Models\SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')
            ->where('listing_id', $listing->id)
            ->first();

        $this->assertNotNull($state, "Publishing a {$propertyType} listing recorded no derivation state.");
        $this->assertSame($expectedContext, $state->context);
    }

    public static function sellerContexts(): array
    {
        return [
            'residential' => ['Residential', 'residential.sale'],
            'income'      => ['Income', 'income.sale'],
            'commercial'  => ['Commercial', 'commercial.sale'],
            'business'    => ['Business', 'business.sale'],
            'vacant land' => ['Vacant Land', 'land.sale'],
        ];
    }

    /** @test */
    public function an_unrecognised_seller_property_type_derives_nothing(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential');

        // Rewrite the stored type to something outside the closed vocabulary and
        // re-derive: the context resolver must fail closed rather than guess.
        $listing->saveMeta('property_type', 'Houseboat');

        $outcome = app(SmartTagLifecycle::class)
            ->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->derived);
        $this->assertSame(\App\Services\SmartTags\DerivationOutcome::NO_CONTEXT, $outcome->skippedReason);
        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->count(),
            'A listing with no supported context kept its assignments.');
    }

    /* =====================================================================
     * Seller — the public description
     * ===================================================================== */

    /** @test */
    public function the_seller_public_description_derives_description_evidence(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', [
            'additional_details' => 'The kitchen has quartz countertops and there is a private pool.',
        ]);

        $this->assertNotEmpty(
            $this->evidenceFor('seller_agent', $listing->id, 'native_listing_description'),
            'The public description produced no evidence.'
        );
    }

    /** @test */
    public function a_changed_description_retags_and_an_unchanged_one_does_not_reparse(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->publishSeller($owner, 'Residential', [
            'additional_details' => 'Quartz countertops throughout.',
        ]);

        $lifecycle = app(SmartTagLifecycle::class);

        // Unchanged — the description parser must not run again.
        $again = $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);
        $this->assertNotNull($again);
        $this->assertFalse($again->descriptionParsed, 'An unchanged description was reparsed.');

        // Changed — it must.
        $listing->saveMeta('additional_details', 'There is a private pool and a three car garage.');
        $changed = $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);
        $this->assertNotNull($changed);
        $this->assertTrue($changed->descriptionParsed, 'A changed description was not reparsed.');
    }

    /** @test */
    public function unchanged_native_structured_input_is_not_rederived(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', ['waterfront' => 'Yes']);

        $again = app(SmartTagLifecycle::class)
            ->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $this->assertNotNull($again);
        $this->assertFalse($again->structuredDerived, 'Unchanged native structured input was re-derived.');
    }

    /** @test */
    public function removing_the_description_removes_its_evidence_and_re_resolves(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', [
            'additional_details' => 'The kitchen has quartz countertops and there is a private pool.',
        ]);

        $this->assertNotEmpty($this->evidenceFor('seller_agent', $listing->id, 'native_listing_description'));

        $listing->saveMeta('additional_details', '');
        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $this->assertSame([], $this->evidenceFor('seller_agent', $listing->id, 'native_listing_description'),
            'Description evidence survived the description being removed.');
    }

    /* =====================================================================
     * Landlord
     * ===================================================================== */

    /** @test */
    public function publishing_a_residential_lease_derives_tags_in_the_residential_lease_context(): void
    {
        $listing = $this->publishLandlord($this->landlordOwner(), 'Residential Property');

        $state = \App\Models\SmartTagDerivationState::query()
            ->where('listing_type', 'landlord_agent')
            ->where('listing_id', $listing->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('residential.lease', $state->context);
    }

    /** @test */
    public function publishing_a_commercial_lease_derives_tags_in_the_commercial_lease_context(): void
    {
        $listing = $this->publishLandlord($this->landlordOwner(), 'Commercial Property');

        $state = \App\Models\SmartTagDerivationState::query()
            ->where('listing_type', 'landlord_agent')
            ->where('listing_id', $listing->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('commercial.lease', $state->context);
    }

    /** @test */
    public function a_policy_approved_landlord_description_is_parsed(): void
    {
        $listing = $this->publishLandlord($this->landlordOwner(), 'Residential Property', [
            'additional_details' => 'The unit has quartz countertops and comes with a private pool.',
        ]);

        $this->assertNotEmpty(
            $this->evidenceFor('landlord_agent', $listing->id, 'native_listing_description'),
            'A policy-approved rental description produced no evidence.'
        );
    }

    /**
     * The Fair Housing boundary, end to end.
     *
     * Publishing refuses unsafe prose outright, so the only way a listing HOLDS
     * unsafe prose is a draft save — which is exactly the stored state this
     * asserts against. The policy withholds it from the public page, and Smart
     * Tags parses what the page shows.
     *
     * @test
     */
    public function a_policy_suppressed_landlord_description_is_never_parsed(): void
    {
        $owner = $this->landlordOwner();
        $listing = $this->publishLandlord($owner, 'Residential Property', [
            'additional_details' => 'The unit has quartz countertops and comes with a private pool.',
        ]);

        $this->assertNotEmpty($this->evidenceFor('landlord_agent', $listing->id, 'native_listing_description'));

        // Unsafe prose, as a draft save would leave it: stored exactly, withheld
        // from the page by LandlordProviderTextPolicy::displayValue().
        $unsafe = 'Quartz countertops throughout. No emotional support animals.';
        $listing->saveMeta('additional_details', $unsafe);

        $this->assertNull(
            \App\Support\OfferListing\LandlordProviderTextPolicy::displayValue('additional_details', $unsafe),
            'Fixture is not actually suppressed — the test would prove nothing.'
        );

        $outcome = app(SmartTagLifecycle::class)
            ->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_LANDLORD_PUBLISH);

        $this->assertNotNull($outcome);
        $this->assertContains(\App\Services\SmartTags\DerivationOutcome::DESCRIPTION_SUPPRESSED, $outcome->notes);
        $this->assertSame([], $this->evidenceFor('landlord_agent', $listing->id, 'native_listing_description'),
            'Suppressed prose was parsed into Smart Tag evidence.');
    }

    /** @test */
    public function the_bracket_placeholder_landlord_storage_shape_stays_unknown(): void
    {
        $listing = $this->publishLandlord($this->landlordOwner(), 'Residential Property');

        // Landlord Create's ensureArray() turns a single-select into "[]".
        $listing->saveMeta('tenant_require', '[]');
        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_LANDLORD_PUBLISH);

        $states = SmartTagEvidence::query()
            ->where('listing_type', 'landlord_agent')
            ->where('listing_id', $listing->id)
            ->whereIn('tag_key', ['furnished', 'unfurnished'])
            ->pluck('state', 'tag_key')
            ->all();

        $this->assertSame([], $states, '"[]" was read as an answer rather than as unknown.');
    }

    /* =====================================================================
     * Drafts, Hire Agent rows and the gates
     * ===================================================================== */

    /** @test */
    public function saving_a_seller_draft_derives_nothing(): void
    {
        $owner = $this->sellerOwner();
        $this->actingAs($owner);

        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->set('listing_title', 'Draft, not published')
            ->set('property_type', 'Residential')
            ->set('waterfront', 'Yes')
            ->set('additional_details', 'Quartz countertops and a private pool.')
            ->call('saveDraft');

        $this->assertGreaterThan(0, SellerAgentAuction::query()->where('user_id', $owner->id)->count(),
            'The draft did not save — the test would pass vacuously.');

        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts(),
            'A draft save derived Smart Tags.');
    }

    /** @test */
    public function saving_a_landlord_draft_derives_nothing(): void
    {
        $owner = $this->landlordOwner();
        $this->actingAs($owner);

        Livewire::test(\App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class)
            ->set('listing_title', 'Draft, not published')
            ->set('property_type', 'Residential Property')
            ->call('saveDraft');

        $this->assertGreaterThan(0, LandlordAgentAuction::query()->where('user_id', $owner->id)->count());
        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts(),
            'A draft save derived Smart Tags.');
    }

    /** @test */
    public function repeated_draft_saves_never_accumulate_smart_tag_rows(): void
    {
        $owner = $this->sellerOwner();
        $this->actingAs($owner);

        // SAVE_AS_NEW_DRAFT is true, so each save is a NEW row. This is the exact
        // shape that made deriving on drafts unacceptable.
        for ($i = 1; $i <= 3; $i++) {
            Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
                ->set('listing_title', "Draft version {$i}")
                ->set('property_type', 'Residential')
                ->set('waterfront', 'Yes')
                ->call('saveDraft');
        }

        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts());
    }

    /** @test */
    public function a_hire_agent_row_never_receives_tags(): void
    {
        $owner = $this->sellerOwner();

        $hire = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'additional_details' => 'Quartz countertops and a private pool.',
        ], workflow: 'hire_agent');

        $outcome = app(SmartTagLifecycle::class)
            ->deriveForNativeSilently($hire, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->derived);
        $this->assertSame(\App\Services\SmartTags\DerivationOutcome::NOT_AN_OFFER_LISTING, $outcome->skippedReason);
        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts());
    }

    /** @test */
    public function a_landlord_hire_agent_row_never_receives_tags(): void
    {
        $hire = $this->landlordListing($this->landlordOwner(), [
            'property_type' => 'Residential Property',
        ], workflow: 'hire_agent');

        $outcome = app(SmartTagLifecycle::class)
            ->deriveForNativeSilently($hire, SmartTagTelemetry::ENTRY_LANDLORD_PUBLISH);

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->derived);
        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts());
    }

    /** @test */
    public function with_the_master_gate_closed_publishing_derives_nothing(): void
    {
        $this->disableSmartTags();

        $listing = $this->publishSeller($this->sellerOwner(), 'Residential', [
            'waterfront'         => 'Yes',
            'additional_details' => 'Quartz countertops and a private pool.',
        ]);

        $this->assertNotNull($listing, 'The listing must still publish with Smart Tags off.');
        $this->assertSame(['evidence' => 0, 'assignments' => 0, 'states' => 0], $this->smartTagRowCounts(),
            'Smart Tags were derived with the master gate closed.');
    }

    /* =====================================================================
     * Manual evidence, context changes and failure isolation
     * ===================================================================== */

    /** @test */
    public function automatic_rederivation_never_touches_manual_owner_evidence(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->publishSeller($owner, 'Residential', [
            'additional_details' => 'Quartz countertops throughout.',
        ]);

        app(ManualSmartTagWriter::class)->replaceSelections(
            SmartTagListingRef::fromModel($listing), ['kitchen_island'], $owner
        );

        $manualBefore = SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('source', 'manual_listing_owner')->get();

        $this->assertCount(1, $manualBefore, 'Fixture did not store a manual selection.');
        $eventsBefore = SmartTagManualEvent::query()->count();

        $lifecycle = app(SmartTagLifecycle::class);

        // Structured change, description change, description removal, context
        // change and a version change — every trigger, in one listing's life.
        $listing->saveMeta('waterfront', 'Yes');
        $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $listing->saveMeta('additional_details', 'There is a private pool.');
        $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $listing->saveMeta('additional_details', '');
        $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $listing->saveMeta('property_type', 'Commercial');
        $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $manualAfter = SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('source', 'manual_listing_owner')->get();

        $this->assertCount(1, $manualAfter, 'Manual evidence was deleted by automatic re-derivation.');
        $this->assertSame($manualBefore->first()->tag_key, $manualAfter->first()->tag_key);
        $this->assertSame($manualBefore->first()->context, $manualAfter->first()->context);
        $this->assertSame($eventsBefore, SmartTagManualEvent::query()->count(),
            'Automatic derivation wrote manual audit events.');
    }

    /** @test */
    public function changing_the_property_type_stops_the_old_context_influencing_assignments(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->publishSeller($owner, 'Residential', ['waterfront' => 'Yes']);

        $before = $this->presentTags('seller_agent', $listing->id);
        $this->assertNotEmpty($before);

        $listing->saveMeta('property_type', 'Commercial');
        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $contexts = SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->pluck('context')->unique()->values()->all();

        $this->assertTrue($contexts === [] || $contexts === ['commercial.sale'],
            'Assignments kept the old context after the property type changed: ' . json_encode($contexts));
    }

    /**
     * FAILURE ISOLATION, with the primary write already committed.
     *
     * The lifecycle is replaced by one that throws, and the publish must still
     * succeed completely: the row, its draft state, its meta and the redirect.
     *
     * @test
     */
    public function a_smart_tag_failure_does_not_fail_or_roll_back_a_seller_publish(): void
    {
        $this->app->bind(SmartTagLifecycle::class, fn () => new ThrowingSmartTagLifecycle());

        $owner = $this->sellerOwner();
        $this->actingAs($owner);

        $component = Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class);
        foreach ($this->sellerPublishFields('Residential', ['waterfront' => 'Yes']) as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('store')->assertHasNoErrors();

        $listing = SellerAgentAuction::query()->where('user_id', $owner->id)->latest('id')->first();

        $this->assertNotNull($listing, 'A Smart Tag failure rolled back the listing.');
        $this->assertSame(0, (int) $listing->is_draft, 'The listing did not publish.');
        $this->assertSame('Residential', $listing->get->property_type, 'The listing meta was rolled back.');
        $this->assertSame('offer_listing', $listing->get->workflow_type);
    }

    /** @test */
    public function a_smart_tag_failure_does_not_fail_a_landlord_publish(): void
    {
        $this->app->bind(SmartTagLifecycle::class, fn () => new ThrowingSmartTagLifecycle());

        $owner = $this->landlordOwner();
        $this->actingAs($owner);

        $component = Livewire::test(\App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class);
        foreach ($this->landlordPublishFields('Residential Property') as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('store')->assertHasNoErrors();

        $listing = LandlordAgentAuction::query()->where('user_id', $owner->id)->latest('id')->first();

        $this->assertNotNull($listing);
        $this->assertSame(0, (int) $listing->is_draft);
    }

    /**
     * The facade swallows a throw from the derivation service itself, rather than
     * relying on every call site to wrap it.
     *
     * @test
     */
    public function the_lifecycle_swallows_a_derivation_fault_and_reports_null(): void
    {
        $listing = $this->publishSeller($this->sellerOwner(), 'Residential');

        // A service that CONSTRUCTS fine and throws when called — otherwise the
        // container would fail while building the lifecycle and the test would
        // prove nothing about the facade's catch.
        $this->app->bind(\App\Services\SmartTags\SmartTagDerivationService::class,
            fn () => new ThrowingDerivationService());

        $lifecycle = $this->app->make(SmartTagLifecycle::class);

        $this->assertNull(
            $lifecycle->deriveForNativeSilently($listing->fresh(), SmartTagTelemetry::ENTRY_SELLER_PUBLISH),
            'The lifecycle rethrew a derivation fault.'
        );
    }

    /* =====================================================================
     * Listing type mapping
     * ===================================================================== */

    /** @test */
    public function the_reverse_model_mapper_answers_only_for_smart_tag_listing_types(): void
    {
        $this->assertSame(SmartTagListingType::SellerAgent, SmartTagListingType::forModelClass(SellerAgentAuction::class));
        $this->assertSame(SmartTagListingType::LandlordAgent, SmartTagListingType::forModelClass(LandlordAgentAuction::class));
        $this->assertSame(SmartTagListingType::Bridge, SmartTagListingType::forModelClass(\App\Models\BridgeProperty::class));

        // A leading backslash is the same class.
        $this->assertSame(SmartTagListingType::SellerAgent, SmartTagListingType::forModelClass('\\' . SellerAgentAuction::class));

        $this->assertNull(SmartTagListingType::forModelClass(\App\Models\BuyerAgentAuction::class));
        $this->assertNull(SmartTagListingType::forModelClass(\App\Models\TenantAgentAuction::class));
        $this->assertNull(SmartTagListingType::forModelClass(\App\Models\User::class));
        $this->assertNull(SmartTagListingType::forModelClass('Not\\A\\Class'));
    }
}
