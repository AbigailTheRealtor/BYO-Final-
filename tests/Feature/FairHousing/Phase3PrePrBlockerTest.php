<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\OfferAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fair Housing Phase 3 — the five defects the pre-PR audit found, as tests.
 *
 * Each block below is red against the code as the audit found it and green against
 * the fix. They drive real routes, real Blade and real Livewire rather than asserting
 * on source strings, because Phase 2 established what source assertions are worth
 * here: the suite stayed green while the landlord page returned HTTP 500 to every
 * visitor.
 *
 * Every suppression case is paired with a POSITIVE CONTROL on the same surface. A
 * page that renders nothing is not a page that suppresses correctly, and only the
 * pair can tell the two apart.
 */
class Phase3PrePrBlockerTest extends TestCase
{
    use DatabaseTransactions;

    /* =====================================================================
     * Fixtures
     * ===================================================================== */

    private function landlordListing(array $meta = []): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $auction = LandlordAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Phase 3 pre-PR listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach (['workflow_type' => 'offer_listing'] + $meta as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        $offerAuction = OfferAuction::create(['user_id' => $owner->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    private function tenantListing(array $meta): TenantAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'user']);

        $t = new TenantAgentAuction();
        $t->user_id     = $owner->id;
        $t->title       = 'Phase 3 tenant listing';
        $t->is_draft    = false;
        $t->is_approved = true;
        $t->save();

        foreach (['workflow_type' => 'offer_listing'] + $meta as $key => $value) {
            $m = new TenantAgentAuctionMeta();
            $m->tenant_agent_auction_id = $t->id;
            $m->meta_key                = $key;
            $m->meta_value              = $value;
            $m->save();
        }

        return $t;
    }

    /** An Offer Playoff listing readable through the authenticated agent route. */
    private function offerListing(array $meta): OfferAuction
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $auction = OfferAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Phase 3 agent-view listing',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        foreach (['listing_role' => 'landlord'] + $meta as $key => $value) {
            OfferAuctionMeta::create([
                'offer_auction_id' => $auction->id,
                'meta_key'         => $key,
                'meta_value'       => $value,
            ]);
        }

        return $auction;
    }

    /* =====================================================================
     * BLOCKER 1 — cross-field assistance-animal exclusions
     *
     * The rule was opt-in to `pet_restrictions`, so the same sentence published
     * untouched from the other two governed boxes. These drive the anonymous page.
     * ===================================================================== */

    /** @test */
    public function assistance_animal_exclusions_are_suppressed_from_approval_conditions_on_the_public_page(): void
    {
        $auction = $this->landlordListing([
            'landlord_approval_conditions' => 'No emotional support animals. Credit 650+.',
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('No emotional support animals', false);
    }

    /** @test */
    public function assistance_animal_exclusions_are_suppressed_from_additional_details_on_the_public_page(): void
    {
        $auction = $this->landlordListing([
            'additional_details' => 'Quiet building. No service animals.',
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('No service animals', false);
    }

    /**
     * @test
     *
     * POSITIVE CONTROL. Accessibility FACTS are what this boundary most has to
     * protect: a rule that cannot tell "wheelchair accessible" from "no wheelchair
     * users" is wrong by construction, and would quietly delete the very
     * information a disabled applicant needs.
     */
    public function neutral_accessibility_facts_still_render_on_the_public_page(): void
    {
        $auction = $this->landlordListing([
            'additional_details' => 'Wheelchair accessible entrance with zero-step entry.',
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Wheelchair accessible entrance', false);
    }

    /** @test */
    public function the_audits_protected_class_variants_are_suppressed_on_the_public_page(): void
    {
        foreach ([
            'No child.',
            'Children are not allowed.',
            'We prefer adults.',
        ] as $unsafe) {
            $auction = $this->landlordListing(['landlord_approval_conditions' => $unsafe]);

            $this->get(route('offer.listing.landlord.view', $auction->id))
                ->assertStatus(200)
                ->assertDontSee(rtrim($unsafe, '.'), false);
        }
    }

    /* =====================================================================
     * BLOCKER 2 — no silent truncation
     *
     * The write ran mb_substr($text, 0, max_length). That destroyed the
     * landlord's words with no error, and because it cut on the way IN, a
     * sentence starting after the limit never reached moderation at all.
     * ===================================================================== */

    /** @test */
    public function an_over_length_draft_is_stored_byte_exact_and_kept_on_the_component(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $long = str_repeat('a', 1001);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 no-truncation draft')
            ->set('landlord_approval_conditions', $long)
            ->call('saveDraft');

        // The component still holds every character the landlord typed.
        $this->assertSame($long, $component->get('landlord_approval_conditions'));
        $this->assertSame(1001, mb_strlen($component->get('landlord_approval_conditions')));

        // And so does the row. Nothing was cut on the way to storage.
        $stored = LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $component->get('listingId'))
            ->where('meta_key', 'landlord_approval_conditions')
            ->value('meta_value');

        $this->assertSame($long, $stored, 'The draft was silently truncated.');
        $this->assertSame(1001, mb_strlen($stored));
    }

    /** @test */
    public function publishing_an_over_length_governed_field_is_refused_and_the_text_is_kept(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $long = str_repeat('a', 1001);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 over-length publish')
            ->set('landlord_approval_conditions', $long)
            ->call('store');

        // Refused, and the error names the field so the landlord knows what to edit.
        $component->assertHasErrors('landlord_approval_conditions');

        // Their text is still in the form, whole.
        $this->assertSame($long, $component->get('landlord_approval_conditions'));

        $this->assertSame(0, LandlordAgentAuction::query()
            ->where('user_id', $owner->id)
            ->where('is_draft', 0)
            ->count(), 'An over-length field was published.');
    }

    /**
     * @test
     *
     * POSITIVE CONTROL: exactly at the bound publishes. Without this, a publish
     * gate that refused everything would pass the test above.
     */
    public function a_governed_field_at_exactly_the_limit_still_publishes(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $exact = str_repeat('a', 1000);

        Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 at-limit publish')
            ->set('landlord_approval_conditions', $exact)
            ->call('store')
            ->assertHasNoErrors('landlord_approval_conditions');
    }

    /**
     * @test
     *
     * The evasion write-time truncation created: filler to the limit, then the
     * unsafe sentence. It used to be discarded before storage, so nothing ever
     * judged it. Now it is stored whole, and therefore judged.
     */
    public function an_unsafe_phrase_after_the_limit_is_stored_whole_and_still_suppressed(): void
    {
        $evader  = str_repeat('a', 1000) . ' No emotional support animals.';
        $auction = $this->landlordListing(['landlord_approval_conditions' => $evader]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('No emotional support animals', false);

        $this->assertSame($evader, LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'landlord_approval_conditions')
            ->value('meta_value'), 'Stored bytes changed.');
    }

    /* =====================================================================
     * BLOCKER 3 — tenant Additional Information allowlist
     *
     * The section's rule was "any populated key not in this list is public", on a
     * route with no auth middleware. The inversion is kept; what changes here is
     * that the allowlist is no longer empty, and the keys in it were classified
     * from their actual control and caption.
     * ===================================================================== */

    /** @test */
    public function a_reviewed_public_overflow_key_renders_from_the_real_production_config(): void
    {
        // Deliberately NO config injection: this reads config/tenant_public_overflow_keys.php
        // as shipped, so an empty production allowlist fails this test.
        $t = $this->tenantListing(['utilities' => 'Included in Rent']);

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertSee('Included in Rent', false);
    }

    /** @test */
    public function every_shipped_public_overflow_key_is_actually_renderable(): void
    {
        $keys = (array) config('tenant_public_overflow_keys.public_keys', []);

        $this->assertNotEmpty($keys, 'The reviewed public allowlist must not be empty.');

        foreach ($keys as $key) {
            $marker = 'OVERFLOW-VISIBLE-' . strtoupper(str_replace('_', '-', $key));
            $t      = $this->tenantListing([$key => $marker]);

            $this->get(route('offer.listing.tenant.view', $t->id))
                ->assertStatus(200)
                ->assertSee($marker, false);
        }
    }

    /** @test */
    public function an_unknown_tenant_key_is_still_private_by_default(): void
    {
        $t = $this->tenantListing(['phase3_brand_new_unreviewed_key' => 'SYNTHETIC-LEAK-MARKER']);

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertDontSee('SYNTHETIC-LEAK-MARKER', false);
    }

    /**
     * @test
     *
     * The keys the census found the old deny-list fallback was publishing on a
     * route with no auth middleware. Seeding them directly is the point: these
     * are historical rows, and none of them may reach the page.
     */
    public function the_sensitive_keys_the_fallback_used_to_publish_stay_private(): void
    {
        foreach ([
            'eviction_explanation',
            'prior_felony_explanation',
            'pre_approval_amount',
            'cash_budget',
            'outstanding_balance',
            'retained_deposits',
            'person_meeting',
            'meeting_details_first_name',
            'meeting_details_last_name',
            'meeting_details_email',
            'meeting_details_phone',
            'number_of_unit',
            'restrictions',
        ] as $key) {
            $marker = 'PRIVATE-LEAK-' . strtoupper(str_replace('_', '-', $key));
            $t      = $this->tenantListing([$key => $marker]);

            $this->get(route('offer.listing.tenant.view', $t->id))
                ->assertStatus(200)
                ->assertDontSee($marker, false);
        }
    }

    /* =====================================================================
     * BLOCKER 4 — the authenticated agent Offer Listing view
     *
     * "Authenticated" is not "entitled to unlawful screening criteria". An admin
     * reaches any listing through this route and is by definition not its owner.
     * ===================================================================== */

    private function actingAsNonOwnerAdmin(): User
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * `pet_restrictions` renders inside the association/HOA block, so a listing
     * that exercises it has to have an HOA. Opening the block is what makes the
     * assertion meaningful — otherwise "not seen" would only mean "not rendered".
     */
    private const HOA_GATE = ['has_hoa' => 'Yes', 'association_name' => 'Phase 3 HOA'];

    /** @test */
    public function unsafe_provider_prose_is_suppressed_from_the_authenticated_agent_view(): void
    {
        $auction = $this->offerListing(self::HOA_GATE + [
            'landlord_approval_conditions' => 'Professionals only, no children. No Section 8.',
            'additional_details'           => 'No service animals in this building.',
            'pet_restrictions'             => 'No emotional support animals.',
        ]);

        $this->actingAsNonOwnerAdmin();

        $this->get(route('offer.listing.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('Professionals only', false)
            ->assertDontSee('No Section 8', false)
            ->assertDontSee('No service animals', false)
            ->assertDontSee('No emotional support animals', false);
    }

    /**
     * @test
     *
     * POSITIVE CONTROL on the same surface and the same three fields.
     */
    public function safe_provider_prose_still_renders_on_the_authenticated_agent_view(): void
    {
        $auction = $this->offerListing(self::HOA_GATE + [
            'landlord_approval_conditions' => 'Credit score 650+, income 3x rent.',
            'additional_details'           => 'Wheelchair accessible entrance.',
            'pet_restrictions'             => 'Maximum 2 pets, 50 lb weight limit.',
        ]);

        $this->actingAsNonOwnerAdmin();

        $this->get(route('offer.listing.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Credit score 650+', false)
            ->assertSee('Wheelchair accessible entrance', false)
            ->assertSee('50 lb weight limit', false);
    }

    /** @test */
    public function the_agent_view_suppression_does_not_change_stored_bytes(): void
    {
        $unsafe  = 'Professionals only, no children.';
        $auction = $this->offerListing(['landlord_approval_conditions' => $unsafe]);

        $this->actingAsNonOwnerAdmin();
        $this->get(route('offer.listing.view', $auction->id))->assertStatus(200);

        $this->assertSame($unsafe, OfferAuctionMeta::query()
            ->where('offer_auction_id', $auction->id)
            ->where('meta_key', 'landlord_approval_conditions')
            ->value('meta_value'), 'The agent view mutated stored data.');
    }

    /* =====================================================================
     * BLOCKER 5 — imported MLS provider prose on the anonymous page
     *
     * Current main publishes the Stellar/Bridge payload through
     * _mls_property_facts.blade.php on a route with no auth middleware. A few of
     * those fields are provider-authored narrative, and they reached the page
     * past no boundary at all.
     * ===================================================================== */

    private function landlordListingWithMlsRows(array $rows): LandlordAgentAuction
    {
        $blob = [
            'version'  => 1,
            'sections' => [[
                'title' => 'Lease & Pets',
                'group' => 'facts',
                'rows'  => $rows,
            ]],
            'permissions'  => [],
            'listing_key'  => 'PHASE3-KEY',
            'mls_number'   => 'PHASE3-MLS',
            'generated_at' => '2026-09-09T00:00:00+00:00',
        ];

        return $this->landlordListing([
            MlsQuickImportDraftWriter::META_PROPERTY_DETAILS => json_encode($blob),
            MlsQuickImportDraftWriter::META_MLS_NUMBER       => 'PHASE3-MLS',
        ]);
    }

    /** @test */
    public function unsafe_imported_mls_prose_is_suppressed_on_the_anonymous_landlord_page(): void
    {
        $auction = $this->landlordListingWithMlsRows([
            ['key' => 'STELLAR_PetRestrictions', 'label' => 'Pet Restrictions', 'value' => 'No emotional support animals.'],
            ['key' => 'STELLAR_AdditionalLeaseRestrictions', 'label' => 'Lease Restriction Details', 'value' => 'No children under 12.'],
            ['key' => 'OpenHouseRemarks', 'label' => 'Notes', 'value' => 'Perfect for young professionals.'],
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('No emotional support animals', false)
            ->assertDontSee('No children under 12', false)
            ->assertDontSee('Perfect for young professionals', false);
    }

    /**
     * @test
     *
     * POSITIVE CONTROL. The MLS Details section must still render — suppressing
     * the whole payload would "pass" the test above while destroying the import
     * parity work this branch merged from main.
     */
    public function safe_imported_mls_prose_still_renders_on_the_anonymous_landlord_page(): void
    {
        $auction = $this->landlordListingWithMlsRows([
            ['key' => 'STELLAR_PetRestrictions', 'label' => 'Pet Restrictions', 'value' => 'Dogs under 40 lbs, two pet maximum.'],
            ['key' => 'STELLAR_AdditionalLeaseRestrictions', 'label' => 'Lease Restriction Details', 'value' => 'Lease term: minimum 6 months.'],
            ['key' => 'OpenHouseRemarks', 'label' => 'Notes', 'value' => 'Refreshments provided. Street parking available.'],
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Dogs under 40 lbs', false)
            ->assertSee('minimum 6 months', false)
            ->assertSee('Street parking available', false);
    }

    /**
     * @test
     *
     * STRUCTURED FACTS ARE NOT PROSE. `OccupantType` is a RESO enum of
     * Owner / Tenant / Vacant, and `STELLAR_RealtorInfo` arrives as an enum array
     * despite being labelled "Listing Notes". Moderating either would delete a
     * property fact for no safety gain, so neither is mapped.
     */
    public function structured_mls_enums_are_never_moderated(): void
    {
        $auction = $this->landlordListingWithMlsRows([
            ['key' => 'OccupantType', 'label' => 'Currently Occupied By', 'value' => 'Tenant'],
            ['key' => 'STELLAR_RealtorInfo', 'label' => 'Listing Notes', 'value' => 'Brochure Available, Survey Available'],
            ['key' => 'AccessibilityFeatures', 'label' => 'Accessibility Features', 'value' => 'Accessible Entrance, Accessible Hallway(s)'],
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Currently Occupied By', false)
            ->assertSee('Brochure Available', false)
            ->assertSee('Accessible Entrance', false);
    }

    /**
     * @test
     *
     * The imported payload is STORED COMPLETE. Phase 3 changes publication
     * eligibility at read time and nothing about ingestion, storage or MLS import
     * completeness — so the suppressed sentence is still in the row afterwards.
     */
    public function suppressing_mls_prose_leaves_the_imported_payload_intact(): void
    {
        $auction = $this->landlordListingWithMlsRows([
            ['key' => 'STELLAR_PetRestrictions', 'label' => 'Pet Restrictions', 'value' => 'No emotional support animals.'],
        ]);

        $this->get(route('offer.listing.landlord.view', $auction->id))->assertStatus(200);

        $stored = LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', MlsQuickImportDraftWriter::META_PROPERTY_DETAILS)
            ->value('meta_value');

        $this->assertStringContainsString(
            'No emotional support animals.',
            (string) $stored,
            'The imported MLS payload was modified. Phase 3 is read-time only.'
        );
    }

    /* =====================================================================
     * HISTORICAL SUPPRESSION — every surface at once
     *
     * Rows are seeded straight into meta, bypassing the form and its validation
     * entirely, because that is what a listing written before Phase 3 existed
     * looks like. No remediation runs anywhere: suppression is a read-time
     * decision, so history goes quiet on the next page load and the landlord's
     * own words stay in the database for them to revise.
     * ===================================================================== */

    /** @test */
    public function historical_unsafe_prose_is_inert_on_every_surface_without_changing_stored_bytes(): void
    {
        $conditions = 'Professionals only, no children. No Section 8. No emotional support animals.';
        $details    = 'Better suited to young professionals.';
        $pets       = 'No service dogs.';

        $auction = $this->landlordListing([
            'landlord_approval_conditions' => $conditions,
            'additional_details'           => $details,
            'pet_policy_requirement'       => json_encode(['Dogs allowed']),
            'pet_restrictions'             => $pets,
        ]);

        $forbidden = ['Professionals only', 'No Section 8', 'No emotional support animals', 'Better suited to young professionals', 'No service dogs'];

        // 1. The anonymous landlord listing page.
        $page = $this->get(route('offer.listing.landlord.view', $auction->id))->assertStatus(200);
        foreach ($forbidden as $phrase) {
            $page->assertDontSee($phrase, false);
        }

        // 2. The anonymous rental-qualification page.
        $check = $this->get(route('offer.listing.landlord.qualification.check', $auction->id))->assertStatus(200);
        foreach ($forbidden as $phrase) {
            $check->assertDontSee($phrase, false);
        }

        // 3. Ask AI / Agent AI context.
        $context = json_encode(app(\App\Services\AskAi\AskAiContextBuilderService::class)
            ->buildForListing('landlord', (int) $auction->id));
        foreach ($forbidden as $phrase) {
            $this->assertStringNotContainsString($phrase, $context, "Leaked into AI context: {$phrase}");
        }

        // 4. The authenticated agent Offer Listing view (a separate model/route).
        $offer = $this->offerListing(self::HOA_GATE + [
            'landlord_approval_conditions' => $conditions,
            'additional_details'           => $details,
            'pet_restrictions'             => $pets,
        ]);
        $this->actingAsNonOwnerAdmin();
        $agentView = $this->get(route('offer.listing.view', $offer->id))->assertStatus(200);
        foreach ($forbidden as $phrase) {
            $agentView->assertDontSee($phrase, false);
        }

        // 5. Imported MLS prose on the anonymous page.
        $mls = $this->landlordListingWithMlsRows([
            ['key' => 'STELLAR_PetRestrictions', 'label' => 'Pet Restrictions', 'value' => 'No emotional support animals.'],
        ]);
        $this->get(route('offer.listing.landlord.view', $mls->id))
            ->assertStatus(200)
            ->assertDontSee('No emotional support animals', false);

        // And NOTHING was remediated: every stored value is byte-identical.
        foreach ([
            'landlord_approval_conditions' => $conditions,
            'additional_details'           => $details,
            'pet_restrictions'             => $pets,
        ] as $key => $expected) {
            $this->assertSame($expected, LandlordAgentAuctionMeta::query()
                ->where('landlord_agent_auction_id', $auction->id)
                ->where('meta_key', $key)
                ->value('meta_value'), "Stored bytes changed for {$key}.");
        }
    }
}
