<?php

namespace Tests\Feature\SmartTags;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\SmartTagSeekerPreference;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceResult;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Seeker Smart Tag selections survive draft VERSIONING while the feature is off.
 *
 * Every Offer Listing draft save mints a new row. With
 * SMART_TAGS_SEEKER_PREFERENCES_ENABLED off the picker is hidden and nothing
 * submitted is written — but the version the seeker continues with must not
 * lose what they picked while it was on. That is preservation, done by
 * SmartTagSeekerPreferenceWriter::carryForwardSelections(), which takes no keys
 * and re-projects the source's stored ones for the new version's context.
 */
class SmartTagSeekerOfferListingVersionCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    private SmartTagSeekerPreferenceWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->writer = app(SmartTagSeekerPreferenceWriter::class);
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

    private function buyerDraft(User $owner, string $propertyType, array $keys = [], string $workflow = 'offer_listing'): BuyerAgentAuction
    {
        $a = BuyerAgentAuction::create(['user_id' => $owner->id, 'title' => 'Buyer draft', 'is_draft' => true]);
        $a->saveMeta('workflow_type', $workflow);
        $a->saveMeta('user_type', 'buyer');
        $a->saveMeta('property_type', $propertyType);
        $a = $a->fresh();

        if ($keys !== []) {
            $this->enable();
            $this->writer->replaceSelections($a, $keys, $owner->id);
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
            $this->writer->replaceSelections($a, $keys, $owner->id);
        }

        return $a;
    }

    private function keysFor(object $subject): array
    {
        return app(SmartTagSeekerPreferenceReader::class)->keysFor($subject);
    }

    private function rowsFor(string $type, int $id): array
    {
        return SmartTagSeekerPreference::query()
            ->where('subject_type', $type)->where('subject_id', $id)
            ->orderBy('tag_key')->get(['tag_key', 'context', 'user_id', 'seeker_role'])
            ->map(fn ($r) => $r->only(['tag_key', 'context', 'user_id', 'seeker_role']))->all();
    }

    // ── 1-2, 5-6: the wizards carry picks onto the new version with the flag off ──

    /** @test */
    public function a_buyer_draft_version_made_with_the_feature_off_keeps_the_existing_picks(): void
    {
        $owner = $this->user('buyer');
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool', 'garage']);
        $before = $this->rowsFor('buyer_offer_listing', $v1->id);

        $this->enable(false);

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v1->id])
            ->assertDontSeeHtml('data-seeker-smart-tags')
            ->set('listing_title', 'Edited while off')
            ->call('saveDraftOnly');

        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($v1->id, $v2->id, 'the draft save should have minted a new version');
        $this->assertSame(['private_pool', 'garage'], $this->keysFor($v2));
        $this->assertSame($before, $this->rowsFor('buyer_offer_listing', $v1->id), 'the source version is never written');
    }

    /** @test */
    public function a_tenant_draft_version_made_with_the_feature_off_keeps_the_existing_picks(): void
    {
        $owner = $this->user('tenant');
        $v1 = $this->tenantDraft($owner, 'Residential Property', ['partially_furnished', 'private_pool']);
        $before = $this->rowsFor('tenant_offer_listing', $v1->id);

        $this->enable(false);

        Livewire::actingAs($owner)->test(TenantOfferListingEdit::class, ['auctionId' => $v1->id, 'user_type' => 'tenant'])
            ->assertDontSeeHtml('data-seeker-smart-tags')
            ->set('listing_title', 'Edited while off')
            ->call('saveDraft');

        $v2 = TenantAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($v1->id, $v2->id, 'the draft save should have minted a new version');
        $this->assertEqualsCanonicalizing(['partially_furnished', 'private_pool'], $this->keysFor($v2));
        $this->assertSame($before, $this->rowsFor('tenant_offer_listing', $v1->id), 'the source version keeps its picks');
    }

    // ── 3-4: with the flag off, nothing submitted is written ─────────────────

    /** @test */
    public function with_the_feature_off_submitted_keys_are_ignored_and_deselection_is_impossible(): void
    {
        $owner = $this->user('buyer');
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool']);

        $this->enable(false);

        // A hand-crafted payload: new keys AND the existing one removed.
        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v1->id])
            ->set('seeker_smart_tags', ['garage', 'waterfront'])
            ->set('listing_title', 'Crafted while off')
            ->call('saveDraftOnly');
        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v2->id])
            ->set('seeker_smart_tags', [])
            ->set('listing_title', 'Deselected while off')
            ->call('saveDraftOnly');
        $v3 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        $this->assertSame(['private_pool'], $this->keysFor($v2), 'submitted keys must not be written');
        $this->assertSame(['private_pool'], $this->keysFor($v3), 'deselection must not be performed');
        $this->assertSame(['private_pool'], $this->keysFor($v1));
    }

    // ── 7: a property-type change while off carries only what still applies ──

    /** @test */
    public function a_type_change_while_off_carries_only_selections_valid_for_the_new_context(): void
    {
        $owner = $this->user('buyer');
        // waterfront is seeker-selectable in residential.sale AND commercial.sale;
        // private_pool only in residential.sale.
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool', 'waterfront']);

        $this->enable(false);

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v1->id])
            ->set('property_type', 'Commercial')
            ->call('saveDraftOnly');

        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertSame('Commercial', $v2->get->property_type);
        $this->assertSame(['waterfront'], $this->keysFor($v2));
        $this->assertSame(
            ['commercial.sale'],
            SmartTagSeekerPreference::query()->where('subject_type', 'buyer_offer_listing')
                ->where('subject_id', $v2->id)->pluck('context')->unique()->values()->all()
        );
        $this->assertSame(['private_pool', 'waterfront'], $this->keysFor($v1));
    }

    // ── writer boundary ──────────────────────────────────────────────────────

    /** @test */
    public function the_writer_carries_forward_without_the_flag_and_takes_no_keys(): void
    {
        $owner = $this->user('buyer');
        $source = $this->buyerDraft($owner, 'Residential', ['private_pool', 'garage']);
        $target = $this->buyerDraft($owner, 'Residential');

        $this->enable(false);
        $result = $this->writer->carryForwardSelections($source, $target, $owner->id);

        $this->assertTrue($result->accepted);
        $this->assertSame(['private_pool', 'garage'], $this->keysFor($target));
        $this->assertSame(
            [['tag_key' => 'garage', 'context' => 'residential.sale', 'user_id' => $owner->id, 'seeker_role' => 'buyer'],
             ['tag_key' => 'private_pool', 'context' => 'residential.sale', 'user_id' => $owner->id, 'seeker_role' => 'buyer']],
            $this->rowsFor('buyer_offer_listing', $target->id)
        );
    }

    /** @test */
    public function carrying_forward_twice_is_a_no_op(): void
    {
        $owner = $this->user('tenant');
        $source = $this->tenantDraft($owner, 'Commercial Property', ['vanilla_shell']);
        $target = $this->tenantDraft($owner, 'Commercial Property');

        $this->enable(false);
        $this->writer->carryForwardSelections($source, $target, $owner->id);
        $first = SmartTagSeekerPreference::query()->orderBy('id')->get()->toArray();
        $this->writer->carryForwardSelections($source, $target, $owner->id);

        $this->assertSame($first, SmartTagSeekerPreference::query()->orderBy('id')->get()->toArray());
        $this->assertSame(['vanilla_shell'], $this->keysFor($target));
    }

    /** @test */
    public function an_empty_source_leaves_the_new_version_empty(): void
    {
        $owner = $this->user('buyer');
        $source = $this->buyerDraft($owner, 'Residential');
        $target = $this->buyerDraft($owner, 'Residential');

        $this->enable(false);
        $this->writer->carryForwardSelections($source, $target, $owner->id);

        $this->assertSame([], $this->keysFor($target));
        $this->assertSame(0, SmartTagSeekerPreference::query()->count());
    }

    // ── 8-9: other subjects are untouched, same ids never collide ────────────

    /** @test */
    public function other_subjects_and_same_numbered_rows_of_another_type_are_untouched(): void
    {
        $buyer = $this->user('buyer');
        $tenant = $this->user('tenant');

        $source = $this->buyerDraft($buyer, 'Residential', ['private_pool']);
        $bystander = $this->buyerDraft($buyer, 'Residential', ['garage']);
        $target = $this->buyerDraft($buyer, 'Residential');

        // Tenant rows with the SAME numeric ids as the buyer rows.
        $tenantRows = [];
        foreach ([$source, $bystander, $target] as $b) {
            do {
                $t = $this->tenantDraft($tenant, 'Residential Property');
            } while ($t->id < $b->id);
            if ($t->id === $b->id) {
                $this->writer->replaceSelections($t, ['partially_furnished'], $tenant->id);
                $tenantRows[$t->id] = $this->rowsFor('tenant_offer_listing', $t->id);
            }
        }
        $this->assertNotEmpty($tenantRows, 'fixture: at least one tenant row must share a buyer id');
        $bystanderBefore = $this->rowsFor('buyer_offer_listing', $bystander->id);

        $this->enable(false);
        $this->writer->carryForwardSelections($source, $target, $buyer->id);

        $this->assertSame(['private_pool'], $this->keysFor($target));
        $this->assertSame($bystanderBefore, $this->rowsFor('buyer_offer_listing', $bystander->id));
        foreach ($tenantRows as $id => $rows) {
            $this->assertSame($rows, $this->rowsFor('tenant_offer_listing', $id), "tenant_offer_listing#{$id}");
        }
    }

    // ── 10: no cross-role, cross-product or same-row copy ────────────────────

    /** @test */
    public function cross_role_criteria_hire_and_same_row_copies_are_refused(): void
    {
        $owner = $this->user('buyer');
        $buyerSource = $this->buyerDraft($owner, 'Residential', ['waterfront']);

        // A tenant listing owned by the SAME user, so ownership is not what refuses it.
        $tenantTarget = $this->tenantDraft($owner, 'Residential Property');

        $criteria = new BuyerCriteriaAuction();
        foreach (['user_id' => $owner->id, 'buyer_id' => $owner->id, 'max_price' => 500000, 'title' => 'Criteria'] as $k => $v) {
            $criteria->{$k} = $v;
        }
        $criteria->save();
        $criteria->saveMeta('property_type', 'Residential Property');
        $criteria = $criteria->fresh();
        $this->writer->replaceSelections($criteria, ['waterfront'], $owner->id);

        $hireTarget = $this->buyerDraft($owner, 'Residential', [], 'hire_agent');
        $offerTarget = $this->buyerDraft($owner, 'Residential');

        $this->enable(false);

        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_SUBJECT_MISMATCH,
            $this->writer->carryForwardSelections($buyerSource, $tenantTarget, $owner->id)->refusalReason);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_SUBJECT_MISMATCH,
            $this->writer->carryForwardSelections($criteria, $offerTarget, $owner->id)->refusalReason);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_UNSUPPORTED_SUBJECT,
            $this->writer->carryForwardSelections($buyerSource, $hireTarget, $owner->id)->refusalReason);
        $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_SUBJECT_MISMATCH,
            $this->writer->carryForwardSelections($buyerSource, $buyerSource, $owner->id)->refusalReason);

        $this->assertSame([], $this->keysFor($tenantTarget));
        $this->assertSame([], $this->keysFor($offerTarget));
        $this->assertSame(0, SmartTagSeekerPreference::query()->where('subject_id', $hireTarget->id)
            ->where('subject_type', 'buyer_offer_listing')->count());
    }

    // ── 11: guests and non-owners cannot trigger a copy ──────────────────────

    /** @test */
    public function guests_and_non_owners_cannot_carry_selections(): void
    {
        $owner = $this->user('buyer');
        $intruder = $this->user('buyer');
        $source = $this->buyerDraft($owner, 'Residential', ['private_pool']);
        $ownTarget = $this->buyerDraft($owner, 'Residential');
        $theirTarget = $this->buyerDraft($intruder, 'Residential');

        $this->enable(false);

        foreach ([
            [$source, $ownTarget, null],            // guest
            [$source, $ownTarget, $intruder->id],   // owns neither
            [$source, $theirTarget, $intruder->id], // owns only the target
            [$source, $theirTarget, $owner->id],    // owns only the source
        ] as [$from, $to, $actor]) {
            $this->assertSame(SmartTagSeekerPreferenceResult::REFUSED_NOT_OWNER,
                $this->writer->carryForwardSelections($from, $to, $actor)->refusalReason);
        }

        $this->assertSame([], $this->keysFor($ownTarget));
        $this->assertSame([], $this->keysFor($theirTarget));
    }

    // ── 13: the flag-on behaviour is unchanged ───────────────────────────────

    /** @test */
    public function with_the_feature_on_the_submitted_selection_still_decides_the_new_version(): void
    {
        $owner = $this->user('buyer');
        $v1 = $this->buyerDraft($owner, 'Residential', ['private_pool', 'garage']);

        $this->enable(true);

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v1->id])
            ->set('seeker_smart_tags', ['garage'])
            ->call('saveDraftOnly');
        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)
            ->call('loadDraft', $v2->id)
            ->set('seeker_smart_tags', [])
            ->call('saveDraft');
        $v3 = BuyerAgentAuction::query()->latest('id')->firstOrFail();

        $this->assertSame(['garage'], $this->keysFor($v2), 'a deselection with the feature on is honoured');
        $this->assertSame([], $this->keysFor($v3), 'clearing with the feature on is honoured, not undone by the carry');
        $this->assertSame(['private_pool', 'garage'], $this->keysFor($v1));
    }

    // ── 14-15: criteria untouched, purge unchanged ───────────────────────────

    /** @test */
    public function criteria_selections_are_untouched_and_purge_still_removes_a_carried_version(): void
    {
        $owner = $this->user('buyer');
        $criteria = new BuyerCriteriaAuction();
        foreach (['user_id' => $owner->id, 'buyer_id' => $owner->id, 'max_price' => 500000, 'title' => 'Criteria'] as $k => $v) {
            $criteria->{$k} = $v;
        }
        $criteria->save();
        $criteria->saveMeta('property_type', 'Residential Property');
        $criteria = $criteria->fresh();
        $this->writer->replaceSelections($criteria, ['private_pool'], $owner->id);
        $criteriaBefore = $this->rowsFor('buyer_criteria', $criteria->id);

        $v1 = $this->buyerDraft($owner, 'Residential', ['garage']);

        $this->enable(false);

        Livewire::actingAs($owner)->test(BuyerOfferListingEdit::class, ['auctionId' => $v1->id])
            ->set('listing_title', 'Edited while off')
            ->call('saveDraftOnly');
        $v2 = BuyerAgentAuction::query()->latest('id')->firstOrFail();
        $this->assertSame(['garage'], $this->keysFor($v2));

        Livewire::actingAs($owner)->test(BuyerOfferListing::class)->call('deleteDraft', $v2->id);

        $this->assertDatabaseMissing('buyer_agent_auctions', ['id' => $v2->id]);
        $this->assertSame(0, SmartTagSeekerPreference::query()
            ->where('subject_type', 'buyer_offer_listing')->where('subject_id', $v2->id)->count());
        $this->assertSame(['garage'], $this->keysFor($v1), 'purging the new version leaves its source alone');
        $this->assertSame($criteriaBefore, $this->rowsFor('buyer_criteria', $criteria->id));
    }
}
