<?php

namespace Tests\Feature\SmartTags;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagSeekerPreference;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Buyer/Tenant Offer Listing wizards' "Property Features You Want" picker,
 * driven through the real Livewire components.
 *
 * What is under test is the wiring — that the four wizards render the shared
 * picker only when they should, write through the one writer at every point a
 * row is saved, restore on load, and purge on draft deletion. The write
 * boundary itself is SmartTagSeekerOfferListingPreferenceTest's.
 */
class SmartTagSeekerOfferListingWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    private function enable(bool $on = true): void
    {
        // TEST-LOCAL only. The feature ships off.
        config()->set('smart_tags_wiring.seeker_preferences_enabled', $on);
    }

    private function user(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function label(string $key): string
    {
        return SmartTagTaxonomy::get($key)->label;
    }

    private function buyerDraft(User $owner, string $propertyType, array $keys = []): BuyerAgentAuction
    {
        $a = BuyerAgentAuction::create(['user_id' => $owner->id, 'title' => 'Buyer draft', 'is_draft' => true]);
        $a->saveMeta('workflow_type', 'offer_listing');
        $a->saveMeta('user_type', 'buyer');
        $a->saveMeta('property_type', $propertyType);
        $a = $a->fresh();

        if ($keys !== []) {
            $this->enable();
            app(SmartTagSeekerPreferenceWriter::class)->replaceSelections($a, $keys, $owner->id);
        }

        return $a;
    }

    private function tenantDraft(User $owner, string $propertyType, array $keys = []): TenantAgentAuction
    {
        // TenantAgentAuction is fully guarded, so attributes are set one by one.
        $a = new TenantAgentAuction();
        $a->user_id = $owner->id;
        $a->title = 'Tenant draft';
        $a->is_draft = true;
        $a->save();
        $a->saveMeta('workflow_type', 'offer_listing');
        $a->saveMeta('user_type', 'tenant');
        $a->saveMeta('property_type', $propertyType);
        $a = $a->fresh();

        if ($keys !== []) {
            $this->enable();
            app(SmartTagSeekerPreferenceWriter::class)->replaceSelections($a, $keys, $owner->id);
        }

        return $a;
    }

    private function keysFor(object $subject): array
    {
        return app(SmartTagSeekerPreferenceReader::class)->keysFor($subject);
    }

    // ── 13. flag off: no picker, no write ───────────────────────────────────

    /** @test */
    public function with_the_feature_off_neither_create_wizard_renders_the_picker(): void
    {
        $this->enable(false);

        Livewire::actingAs($this->user('buyer'))->test(BuyerOfferListing::class)
            ->set('property_type', 'Residential')
            ->assertDontSeeHtml('data-seeker-smart-tags')
            ->assertDontSeeHtml('seeker-smart-tag-');

        Livewire::actingAs($this->user('tenant'))->test(TenantOfferListing::class, ['user_type' => 'tenant'])
            ->set('property_type', 'Residential Property')
            ->assertDontSeeHtml('data-seeker-smart-tags')
            ->assertDontSeeHtml('seeker-smart-tag-');
    }

    // ── picker surface: context follows the form's property type ────────────

    /** @test */
    public function the_buyer_picker_offers_only_tags_for_the_chosen_property_type(): void
    {
        $this->enable();

        $component = Livewire::actingAs($this->user('buyer'))->test(BuyerOfferListing::class);

        $component->set('property_type', 'Residential')
            ->assertSeeHtml('data-seeker-smart-tags')
            ->assertSeeHtml('id="seeker-smart-tag-private_pool"')
            ->assertDontSeeHtml('id="seeker-smart-tag-loading_dock"')
            ->assertSee($this->label('private_pool'));

        $component->set('property_type', 'Commercial')
            ->assertSeeHtml('id="seeker-smart-tag-loading_dock"')
            ->assertDontSeeHtml('id="seeker-smart-tag-private_pool"');

        $component->set('property_type', 'Vacant Land')
            ->assertSeeHtml('id="seeker-smart-tag-cleared_land"')
            ->assertDontSeeHtml('id="seeker-smart-tag-loading_dock"');

        // Never an owner-only or non-seeker option, whatever the context.
        $component->set('property_type', 'Residential')
            ->assertDontSeeHtml('id="seeker-smart-tag-accessible_features"')
            ->assertDontSeeHtml('id="seeker-smart-tag-playground"');
    }

    /** @test */
    public function the_tenant_picker_offers_only_lease_tags_for_the_chosen_property_type(): void
    {
        $this->enable();

        $component = Livewire::actingAs($this->user('tenant'))->test(TenantOfferListing::class, ['user_type' => 'tenant']);

        $component->set('property_type', 'Residential Property')
            ->assertSeeHtml('id="seeker-smart-tag-partially_furnished"')
            ->assertDontSeeHtml('id="seeker-smart-tag-vanilla_shell"')
            // A sale-only tag is never offered to a tenant.
            ->assertDontSeeHtml('id="seeker-smart-tag-turnkey_home"');

        $component->set('property_type', 'Commercial Property')
            ->assertSeeHtml('id="seeker-smart-tag-vanilla_shell"')
            ->assertDontSeeHtml('id="seeker-smart-tag-partially_furnished"');
    }

    /** @test */
    public function no_property_type_offers_no_tags(): void
    {
        $this->enable();

        Livewire::actingAs($this->user('buyer'))->test(BuyerOfferListing::class)
            ->set('property_type', '')
            ->assertDontSeeHtml('id="seeker-smart-tag-');
    }

    /** @test */
    public function a_wizard_describing_another_role_renders_no_seeker_picker(): void
    {
        $this->enable();

        // The Tenant wizard can describe a landlord, seller or buyer listing, and the
        // Buyer create view includes other roles' tabs; the seeker picker belongs only
        // to the wizard's own role. Asserted on the component instance rather than by
        // rendering: those other roles' tabs need state this test has no reason to
        // build (the landlord tab reads $pet_fee_type, which only its own flow sets).
        foreach ([
            [new TenantOfferListing(), 'landlord', 'Residential Property'],
            [new TenantOfferListing(), 'buyer', 'Residential Property'],
            [new BuyerOfferListing(), 'tenant', 'Residential'],
        ] as [$component, $userType, $propertyType]) {
            $component->user_type = $userType;
            $component->property_type = $propertyType;
            $this->assertNull($component->seekerSmartTagPanel(), $component::class . " as {$userType}");
        }

        // And the positive control: its own role gets a panel.
        $own = new TenantOfferListing();
        $own->user_type = 'tenant';
        $own->property_type = 'Residential Property';
        $this->assertSame('residential.lease', $own->seekerSmartTagPanel()['context']);
    }

    // ── 10-11. create persistence and edit restoration ──────────────────────

    /** @test */
    public function a_buyer_draft_save_persists_the_picks_and_resuming_restores_them(): void
    {
        $this->enable();
        $owner = $this->user('buyer');

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->set('listing_title', 'Seeker tags')
            ->set('property_type', 'Residential')
            ->set('seeker_smart_tags', ['garage', 'private_pool', 'loading_dock'])
            ->call('saveDraft');

        $draft = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        // Canonical keys only, projected against the STORED type: the commercial key is gone.
        $this->assertSame(['private_pool', 'garage'], $this->keysFor($draft));
        $this->assertSame(0, SmartTagEvidence::query()->count());
        $this->assertSame(0, SmartTagAssignment::query()->count());

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->call('loadDraft', $draft->id)
            ->assertSet('seeker_smart_tags', ['private_pool', 'garage'])
            ->assertSeeHtml('id="seeker-smart-tag-private_pool"');

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $draft->id])
            ->assertSet('seeker_smart_tags', ['private_pool', 'garage']);
    }

    /** @test */
    public function a_tags_only_change_is_not_mistaken_for_an_unchanged_draft(): void
    {
        $this->enable();
        $owner = $this->user('buyer');

        $first = Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->set('listing_title', 'Seeker tags')
            ->set('property_type', 'Residential');
        $first->call('saveDraft');
        $v1 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        $first->set('seeker_smart_tags', ['garage'])->call('saveDraft');
        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        $this->assertNotSame($v1->id, $v2->id, 'the tags-only change should have produced a new draft version');
        $this->assertSame([], $this->keysFor($v1));
        $this->assertSame(['garage'], $this->keysFor($v2));
    }

    /** @test */
    public function a_tenant_draft_save_persists_the_picks_and_edit_restores_them(): void
    {
        $this->enable();
        $owner = $this->user('tenant');

        Livewire::actingAs($owner)->test(TenantOfferListing::class, ['user_type' => 'tenant'])
            ->set('listing_title', 'Seeker tags')
            ->set('property_type', 'Commercial Property')
            ->set('seeker_smart_tags', ['vanilla_shell', 'private_pool'])
            ->call('saveDraft');

        $draft = TenantAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertSame(['vanilla_shell'], $this->keysFor($draft));

        Livewire::actingAs($owner)->test(TenantOfferListingEdit::class, ['auctionId' => $draft->id, 'user_type' => 'tenant'])
            ->assertSet('seeker_smart_tags', ['vanilla_shell']);
    }

    // ── 12. deselection through the wizard ──────────────────────────────────

    /** @test */
    public function deselecting_everything_on_a_new_version_leaves_the_earlier_version_alone(): void
    {
        $this->enable();
        $owner = $this->user('buyer');
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool', 'garage']);

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->call('loadDraft', $v1->id)
            ->set('seeker_smart_tags', [])
            ->call('saveDraft');

        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame([], $this->keysFor($v2));
        $this->assertSame(['private_pool', 'garage'], $this->keysFor($v1), 'a draft version is a snapshot');
    }

    // ── 14. disabled edit preserves existing selections ─────────────────────

    /** @test */
    public function saving_with_the_feature_off_writes_nothing_and_erases_nothing(): void
    {
        $owner = $this->user('buyer');
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool']);

        $this->enable(false);

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->call('loadDraft', $v1->id)
            ->set('seeker_smart_tags', ['garage'])
            ->set('listing_title', 'Changed while off')
            ->call('saveDraft');

        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame(['private_pool'], $this->keysFor($v1));
        $this->assertSame([], $this->keysFor($v2));
        $this->assertSame(1, SmartTagSeekerPreference::query()->count());
    }

    // ── 22. draft deletion purges, whatever the flag ────────────────────────

    /** @test */
    public function deleting_a_draft_purges_its_preferences_even_with_the_feature_off(): void
    {
        $owner = $this->user('buyer');
        $doomed = $this->buyerDraft($owner, 'Residential', ['private_pool']);
        $keeper = $this->buyerDraft($owner, 'Residential', ['garage']);

        $this->enable(false);

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->call('deleteDraft', $doomed->id);

        $this->assertDatabaseMissing('buyer_agent_auctions', ['id' => $doomed->id]);
        $this->assertSame(0, SmartTagSeekerPreference::query()
            ->where('subject_type', 'buyer_offer_listing')->where('subject_id', $doomed->id)->count());
        $this->assertSame(['garage'], $this->keysFor($keeper));
    }

    /** @test */
    public function another_user_cannot_resume_or_delete_a_draft_and_its_preferences_survive(): void
    {
        $owner = $this->user('buyer');
        $draft = $this->buyerDraft($owner, 'Residential', ['private_pool']);
        $intruder = $this->user('buyer');

        Livewire::actingAs($intruder)->test(BuyerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseHas('buyer_agent_auctions', ['id' => $draft->id]);
        $this->assertSame(['private_pool'], $this->keysFor($draft));
    }
}
