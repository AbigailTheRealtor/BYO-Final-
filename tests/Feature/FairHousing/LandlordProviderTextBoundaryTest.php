<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fair Housing Phase 3 — the boundary as the application actually applies it.
 *
 * These drive real routes through Laravel and Blade, and real Livewire components,
 * because Phase 2 already demonstrated what source-string assertions are worth here:
 * the whole suite stayed green while the landlord listing page returned HTTP 500 to
 * every visitor, and again while the review page scored applicants against a criterion
 * the listing page had stopped displaying.
 *
 * Every suppression case is paired with a POSITIVE CONTROL on the same surface, so a
 * page that renders nothing at all cannot be mistaken for a page that suppresses
 * correctly.
 */
class LandlordProviderTextBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private const UNSAFE = 'Professionals only, no children. No Section 8.';
    private const SAFE   = 'Credit score 650+, income 3x rent, 12-month minimum lease';

    private function landlordListing(array $meta = []): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $auction = LandlordAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'Phase 3 boundary listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $rows = ['workflow_type' => 'offer_listing'] + $meta;
        foreach ($rows as $key => $value) {
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

    /* =====================================================================
     * Public display
     * ===================================================================== */

    /** @test */
    public function unsafe_approval_conditions_never_reach_the_public_listing_page(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::UNSAFE]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('Professionals only', false)
            ->assertDontSee('No Section 8', false);
    }

    /**
     * @test
     *
     * POSITIVE CONTROL for the test above. Without this, a page that rendered no
     * approval conditions at all — or failed to render — would pass.
     */
    public function safe_approval_conditions_still_render_on_the_public_page(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::SAFE]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('Credit score 650+', false);
    }

    /** @test */
    public function unsafe_pet_restrictions_are_suppressed_and_safe_ones_are_not(): void
    {
        $unsafe = $this->landlordListing([
            'pet_policy_requirement' => json_encode(['Dogs allowed']),
            'pet_restrictions'       => 'No emotional support animals.',
        ]);
        $this->get(route('offer.listing.landlord.view', $unsafe->id))
            ->assertStatus(200)
            ->assertDontSee('No emotional support animals', false);

        $safe = $this->landlordListing([
            'pet_policy_requirement' => json_encode(['Dogs allowed']),
            'pet_restrictions'       => 'Maximum 2 pets, 50 lb weight limit',
        ]);
        $this->get(route('offer.listing.landlord.view', $safe->id))
            ->assertStatus(200)
            ->assertSee('50 lb weight limit', false);
    }

    /** @test */
    public function the_anonymous_qualification_page_also_suppresses_unsafe_prose(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::UNSAFE]);

        $this->get(route('offer.listing.landlord.qualification.check', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('Professionals only', false);
    }

    /** @test */
    public function historical_unsafe_prose_is_inert_without_changing_stored_bytes(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::UNSAFE]);

        $this->get(route('offer.listing.landlord.view', $auction->id))->assertDontSee('Professionals only', false);

        // The row is untouched — suppression is a read-time decision, and the owner
        // must still be able to see and revise exactly what they wrote.
        $this->assertSame(self::UNSAFE, LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'landlord_approval_conditions')
            ->value('meta_value'));
    }

    /* =====================================================================
     * Ask AI / Agent AI
     * ===================================================================== */

    /** @test */
    public function unsafe_provider_prose_never_reaches_ask_ai_context(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::UNSAFE]);

        $context = app(AskAiContextBuilderService::class)->buildForListing('landlord', (int) $auction->id);

        $this->assertStringNotContainsString('Professionals only', json_encode($context));
        $this->assertNull($context['listing']['landlord_approval_conditions'] ?? null);
    }

    /** @test */
    public function safe_provider_prose_still_reaches_ask_ai_context(): void
    {
        $auction = $this->landlordListing(['landlord_approval_conditions' => self::SAFE]);

        $context = app(AskAiContextBuilderService::class)->buildForListing('landlord', (int) $auction->id);

        $this->assertSame(self::SAFE, $context['listing']['landlord_approval_conditions'] ?? null);
    }

    /** @test */
    public function unsafe_additional_details_are_suppressed_from_the_ai_description(): void
    {
        $auction = $this->landlordListing(['additional_details' => 'Better suited to young professionals.']);

        $context = app(AskAiContextBuilderService::class)->buildForListing('landlord', (int) $auction->id);

        $this->assertStringNotContainsString('Better suited to young professionals', json_encode($context));
        $this->assertNull($context['listing']['description'] ?? null);
    }

    /* =====================================================================
     * Write boundary — crafted payloads
     * ===================================================================== */

    /** @test */
    public function a_crafted_custom_payload_cannot_persist_without_its_parent_on_create(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 crafted create')
            ->set('min_credit_score', 'No requirement')
            ->set('custom_credit_score_requirement', 'CRAFTED-BYPASS')
            ->set('smoking_policy_requirement', 'No smoking')
            ->set('custom_smoking_policy_requirement', 'CRAFTED-SMOKING')
            ->call('saveDraft');

        $listingId = $component->get('listingId');
        $this->assertNotNull($listingId, 'Draft did not persist.');

        foreach (['custom_credit_score_requirement', 'custom_smoking_policy_requirement'] as $key) {
            $stored = LandlordAgentAuctionMeta::query()
                ->where('landlord_agent_auction_id', $listingId)
                ->where('meta_key', $key)
                ->value('meta_value');

            $this->assertNotSame('CRAFTED-BYPASS', $stored);
            $this->assertNotSame('CRAFTED-SMOKING', $stored);
            $this->assertSame('', (string) $stored, "{$key} persisted without its parent condition.");
        }
    }

    /**
     * @test
     *
     * POSITIVE CONTROL: with the parent set to its real unlocking value the same
     * text persists, so the test above is not passing because writes are broken.
     */
    public function custom_text_persists_when_the_parent_authorises_it(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 valid custom')
            ->set('min_credit_score', 'Other')
            ->set('custom_credit_score_requirement', 'Manual review of thin credit files')
            ->call('saveDraft');

        $stored = LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $component->get('listingId'))
            ->where('meta_key', 'custom_credit_score_requirement')
            ->value('meta_value');

        $this->assertSame('Manual review of thin credit files', $stored);
    }

    /**
     * @test
     *
     * `min_monthly_income_fixed` unlocks on 'Fixed Monthly Income', NOT on 'Other'.
     * Assuming 'Other' would have silently discarded every stored fixed income amount.
     */
    public function the_fixed_income_amount_uses_its_own_trigger_not_other(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 fixed income')
            ->set('income_qualification_method', 'Fixed Monthly Income')
            ->set('min_monthly_income_fixed', '5000')
            ->call('saveDraft');

        $this->assertSame('5000', LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $component->get('listingId'))
            ->where('meta_key', 'min_monthly_income_fixed')
            ->value('meta_value'));
    }

    /* =====================================================================
     * Save Draft vs Submit
     * ===================================================================== */

    /**
     * @test
     *
     * A draft keeps exactly what the landlord typed — including wording that is not
     * publishable — so they can come back and revise it. Nothing is deleted or
     * rewritten. Suppression at read time is what keeps it off the page meanwhile.
     */
    public function save_draft_preserves_unsafe_prose_for_its_author(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 draft preservation')
            ->set('landlord_approval_conditions', self::UNSAFE)
            ->call('saveDraft');

        // Still on the component, exactly as typed.
        $this->assertSame(self::UNSAFE, $component->get('landlord_approval_conditions'));

        // And stored, unmodified.
        $this->assertSame(self::UNSAFE, LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $component->get('listingId'))
            ->where('meta_key', 'landlord_approval_conditions')
            ->value('meta_value'));
    }

    /** @test */
    public function submitting_unsafe_prose_is_refused_and_the_text_is_kept(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'Phase 3 publish refusal')
            ->set('landlord_approval_conditions', self::UNSAFE)
            ->call('store');

        // The error is attached to the FIELD, so the landlord is told what to edit.
        $component->assertHasErrors('landlord_approval_conditions');

        // The landlord's words are still in the form for them to revise.
        $this->assertSame(self::UNSAFE, $component->get('landlord_approval_conditions'));

        // And nothing was published: no non-draft listing exists for this owner.
        $this->assertSame(0, LandlordAgentAuction::query()
            ->where('user_id', $owner->id)
            ->where('is_draft', 0)
            ->count(), 'Unsafe prose was published.');
    }

    /* =====================================================================
     * Tenant Additional Information allowlist
     * ===================================================================== */

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

    /** @test */
    public function an_unknown_tenant_meta_key_is_not_published_by_fallback(): void
    {
        $t = $this->tenantListing(['phase3_synthetic_unknown_key' => 'SYNTHETIC-LEAK-MARKER']);

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertDontSee('SYNTHETIC-LEAK-MARKER', false);
    }

    /**
     * @test
     *
     * POSITIVE CONTROL for the allowlist itself: a key named in the config DOES
     * render, so the test above cannot pass merely because the section was deleted.
     */
    public function an_allowlisted_tenant_key_does_render_in_additional_information(): void
    {
        config()->set('tenant_public_overflow_keys.public_keys', ['phase3_allowlisted_demo_key']);

        $t = $this->tenantListing(['phase3_allowlisted_demo_key' => 'ALLOWLISTED-VISIBLE-MARKER']);

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertSee('ALLOWLISTED-VISIBLE-MARKER', false);
    }

    /** @test */
    public function owner_only_consumer_disclosures_remain_owner_only(): void
    {
        $t = $this->tenantListing(['screening_concerns_explanation' => 'PRIVATE-CONSUMER-DISCLOSURE']);

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertDontSee('PRIVATE-CONSUMER-DISCLOSURE', false);
    }

    /* =====================================================================
     * tenant_require label
     * ===================================================================== */

    /** @test */
    public function the_furnishings_value_is_labelled_furnishings_not_tenant_type(): void
    {
        $auction = $this->landlordListing(['tenant_require' => 'Furnished']);

        $response = $this->get(route('offer.listing.landlord.view', $auction->id));

        $response->assertStatus(200);
        $response->assertDontSee('Tenant Type Required', false);
        $response->assertSee('Furnishings', false);
        $response->assertSee('Furnished', false);
    }

    /* =====================================================================
     * Assistance-animal boundary
     * ===================================================================== */

    /** @test */
    public function the_landlord_pet_section_states_that_assistance_animals_are_not_pets(): void
    {
        $partial = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/applicant-requirements.blade.php'
        ));

        $this->assertStringContainsString(
            'Assistance animals are accommodation requests and are not governed by ordinary pet restrictions.',
            $partial
        );
    }

    /* =====================================================================
     * Create / Edit parity
     * ===================================================================== */

    /**
     * @test
     *
     * Both wizards must apply the same boundary. The behavioural crafted-payload proof
     * above runs against Create; this pins that Edit reaches the SAME shared projection
     * rather than keeping a second copy of the rules — which is the failure mode this
     * codebase is quadruplicated enough to invite, and the one
     * `LandlordScreeningPolicy::projectCustomFields()` is directly unit-tested against
     * for all six fields.
     */
    public function create_and_edit_apply_the_same_provider_text_boundary(): void
    {
        $components = [
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        ];

        foreach ($components as $file) {
            $source = file_get_contents(base_path($file));

            // The shared projection, not a local reimplementation.
            $this->assertStringContainsString('LandlordScreeningPolicy::projectCustomFields(', $source,
                "{$file} does not route custom text through the shared projection.");

            // The publish gate is on the publish path.
            $this->assertStringContainsString('$this->assertProviderTextIsPublishable();', $source,
                "{$file} has no publish gate.");

            // No raw write survives for any parent-gated key.
            foreach (array_keys(\App\Support\OfferListing\LandlordScreeningPolicy::customFields()) as $key) {
                $this->assertStringNotContainsString("saveMeta('{$key}'", $source,
                    "{$file} still writes {$key} verbatim.");
            }

            // Prose is length-bounded on the write in both.
            foreach (array_keys(\App\Support\OfferListing\LandlordProviderTextPolicy::fields()) as $key) {
                $this->assertStringContainsString(
                    "LandlordProviderTextPolicy::projectForStorage('{$key}'",
                    $source,
                    "{$file} does not bound {$key} on the write."
                );
            }
        }
    }

    /** @test */
    public function the_retired_landlord_assistance_animal_controls_are_not_reintroduced(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('public $service_animal', $source);
            $this->assertStringNotContainsString('public $support_animal', $source);
        }
    }
}
