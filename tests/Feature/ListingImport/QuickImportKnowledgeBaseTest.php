<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\AskAi\AskAiFaqConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MLS Quick Import asks the SAME AI Knowledge Base questions as manual creation,
 * and admits only the answers those questions asked for.
 *
 * WHAT THESE TESTS ARE FOR
 * ------------------------
 * The Knowledge Base engine already existed and was already shared by all eight
 * manual Create/Edit components. It was simply never reachable from MLS Quick
 * Import — a listing created from an MLS number went straight from Your Terms to
 * Review, published with an empty knowledge base, and nothing anywhere said so.
 *
 * The risk in closing that gap is not that the questions fail to appear. It is
 * that they appear as a SECOND set: a copy of the config, a copy of the gating,
 * or a persist that accepts keys the form never asked. Every test here is
 * therefore a comparison between the two entry paths or between the form and the
 * persist, never a hand-written list of expected questions — a list would itself
 * be another copy, and would pass happily while the two drifted apart.
 *
 * @see \Tests\Feature\AskAi\AskAiKnowledgeBaseRenderTest  the gating matrix itself
 * @see \Tests\Feature\ListingImport\SellerSaleTermsParityTest  the same doctrine, for Terms
 */
class QuickImportKnowledgeBaseTest extends TestCase
{
    use DatabaseTransactions;

    /** The one partial. Both entry paths must render THIS file, not a copy. */
    private const SHARED_PARTIAL = 'livewire.offer-listing.shared.ai-questions-input';

    private User $seller;
    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
        ]);

        $this->seller   = User::factory()->create(['user_type' => 'seller']);
        $this->landlord = User::factory()->create(['user_type' => 'landlord']);
    }

    // ─── Harness ─────────────────────────────────────────────────────────────

    private function seedRecord(string $mls, string $propertyType, int $price = 450000): void
    {
        BridgeProperty::create([
            'provider'                => 'stellar_bridge',
            'listing_key'       => $mls . '-KEY',
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'mls_status'        => 'Active',
            'property_type'     => $propertyType,
            'list_price'        => $price,
            'unparsed_address'  => '1 Knowledge Way',
            'city'              => 'TAMPA',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '1 Knowledge Way',
            ]),
        ]);
    }

    /** Drive a quick import as far as the AI Knowledge Base step. */
    private function toKnowledge(string $role, string $mls): \Livewire\Testing\TestableLivewire
    {
        $user      = $role === 'seller' ? $this->seller : $this->landlord;
        $component = $role === 'seller' ? SellerMlsQuickImport::class : LandlordMlsQuickImport::class;

        return Livewire::actingAs($user)
            ->test($component)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->call('continueToKnowledge');
    }

    /**
     * The knowledge-base question keys present in a rendered page.
     *
     * Read off the partial's own `wire:model.defer="listing_ai_faq.<key>"`
     * bindings, so this measures what a user can actually type into — not what a
     * config file says, and not what a component declares.
     *
     * @return list<string>
     */
    private function renderedKeys(string $html): array
    {
        preg_match_all('/wire:model\.defer="listing_ai_faq\.([A-Za-z0-9_]+)"/', $html, $m);

        $keys = array_values(array_unique($m[1] ?? []));
        sort($keys);

        return $keys;
    }

    /**
     * What MANUAL creation asks for this role and property type.
     *
     * Rendered from the shared partial directly — the same file, with the same
     * three variables, that all eight manual Create/Edit blades @include. This is
     * the manual question set by construction rather than by assertion.
     */
    private function manualKeys(string $role, string $propertyType): array
    {
        return $this->renderedKeys(
            View::make(self::SHARED_PARTIAL, [
                'user_type'      => $role,
                'property_type'  => $propertyType,
                'listing_ai_faq' => [],
            ])->render()
        );
    }

    // ─── One partial, not two ────────────────────────────────────────────────

    /**
     * @test
     *
     * Structural, and deliberately so: the quick-import template must @include
     * the very file the manual wizards include. A test that compared rendered
     * question TEXT would still pass on the day someone pastes the partial's
     * contents inline, which is the failure mode this feature is most exposed to.
     */
    public function quick_import_includes_the_manual_wizards_own_partial(): void
    {
        $quickImport = file_get_contents(
            resource_path('views/livewire/offer-listing/quick-import/mls-quick-import.blade.php')
        );

        $this->assertStringContainsString(
            "@include('" . self::SHARED_PARTIAL . "'",
            $quickImport,
            'MLS Quick Import must render the shared AI Knowledge Base partial, not a copy of it.'
        );

        // …and the manual wizards must still be including the same file, so this
        // assertion cannot pass by both paths having quietly moved elsewhere.
        foreach ([
            'views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
            'views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        ] as $manual) {
            $this->assertStringContainsString(
                "@include('" . self::SHARED_PARTIAL . "')",
                file_get_contents(resource_path($manual)),
                "{$manual} no longer includes the shared partial — the two entry paths have diverged."
            );
        }
    }

    /**
     * @test
     *
     * No MLS-specific question configuration may exist. The four role configs are
     * the whole vocabulary.
     */
    public function no_mls_specific_knowledge_base_config_exists(): void
    {
        foreach (glob(config_path('*.php')) as $file) {
            $name = basename($file, '.php');

            $this->assertFalse(
                (bool) preg_match('/(mls|import|quick).*(faq|knowledge)|(faq|knowledge).*(mls|import|quick)/i', $name),
                "config/{$name}.php looks like an MLS-specific knowledge base config; there must be only one question source per role."
            );
        }
    }

    // ─── Seller ──────────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Seller Residential: the imported path asks exactly what the manual path asks.
     */
    public function seller_residential_import_asks_the_manual_seller_residential_questions(): void
    {
        $this->seedRecord('KB-S-RES', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-S-RES')
            ->assertSet('step', 'knowledge')
            ->assertSet('property_type', 'Residential');

        $imported = $this->renderedKeys($component->lastRenderedDom);
        $manual   = $this->manualKeys('seller', 'Residential');

        $this->assertNotEmpty($imported, 'The Seller Residential import rendered no knowledge base questions at all.');
        $this->assertSame($manual, $imported, 'MLS Quick Import and manual creation ask different Seller Residential questions.');
    }

    /**
     * @test
     *
     * Seller Commercial: universal + commercial, and nothing from any other
     * property type's group. Asserted against the config's own group membership
     * rather than a pasted list of keys.
     */
    public function seller_commercial_import_is_gated_to_universal_plus_commercial(): void
    {
        $this->seedRecord('KB-S-COM', 'Commercial Sale');

        $component = $this->toKnowledge('seller', 'KB-S-COM')
            ->assertSet('step', 'knowledge')
            ->assertSet('property_type', 'Commercial');

        $rendered = $this->renderedKeys($component->lastRenderedDom);
        $groups   = config('ai_faq_seller.groups');

        $keysOfGroup = static function (string $group) use ($groups): array {
            $keys = [];
            foreach (($groups[$group] ?? []) as $questions) {
                foreach ($questions as $key => $entry) {
                    $keys[] = (string) $key;
                }
            }
            return $keys;
        };

        $expected = array_values(array_unique(array_merge(
            $keysOfGroup('universal'),
            $keysOfGroup('commercial'),
        )));
        sort($expected);

        $this->assertSame($expected, $rendered, 'Seller Commercial did not render exactly universal + commercial.');

        // Nothing from the other four property types may leak in. Keys shared with
        // universal or commercial are excluded — those belong here legitimately.
        foreach (['residential', 'income', 'business', 'land'] as $foreign) {
            $leaked = array_intersect(
                array_diff($keysOfGroup($foreign), $expected),
                $rendered
            );

            $this->assertSame(
                [],
                array_values($leaked),
                "Seller Commercial leaked questions from the '{$foreign}' group: " . implode(', ', $leaked)
            );
        }
    }

    // ─── Landlord ────────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Landlord Residential: the imported path asks exactly what the manual path asks.
     */
    public function landlord_residential_import_asks_the_manual_landlord_residential_questions(): void
    {
        $this->seedRecord('KB-L-RES', 'Residential Lease', 3200);

        $component = $this->toKnowledge('landlord', 'KB-L-RES')
            ->assertSet('step', 'knowledge')
            ->assertSet('property_type', 'Residential Property');

        $imported = $this->renderedKeys($component->lastRenderedDom);
        $manual   = $this->manualKeys('landlord', 'Residential Property');

        $this->assertNotEmpty($imported, 'The Landlord Residential import rendered no knowledge base questions at all.');
        $this->assertSame($manual, $imported, 'MLS Quick Import and manual creation ask different Landlord Residential questions.');
    }

    /**
     * @test
     *
     * The roles ask DIFFERENT things. Without this, a bug that served the seller
     * set to both roles would satisfy both parity tests above.
     */
    public function seller_and_landlord_receive_different_question_sets(): void
    {
        $this->seedRecord('KB-S-DIFF', 'Residential');
        $this->seedRecord('KB-L-DIFF', 'Residential Lease', 3200);

        $sellerKeys   = $this->renderedKeys($this->toKnowledge('seller', 'KB-S-DIFF')->lastRenderedDom);
        $landlordKeys = $this->renderedKeys($this->toKnowledge('landlord', 'KB-L-DIFF')->lastRenderedDom);

        $this->assertNotSame($sellerKeys, $landlordKeys);
        $this->assertNotEmpty(array_diff($sellerKeys, $landlordKeys), 'Seller asked nothing the landlord did not.');
        $this->assertNotEmpty(array_diff($landlordKeys, $sellerKeys), 'Landlord asked nothing the seller did not.');
    }

    // ─── Navigation ──────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Terms → Knowledge → Review, and back again, with answers intact in both
     * directions.
     */
    public function answers_survive_moving_backward_and_forward(): void
    {
        $this->seedRecord('KB-NAV', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-NAV')
            ->set('listing_ai_faq.roof_age_and_condition', 'Roof replaced in 2021.')
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->call('backToKnowledge')
            ->assertSet('step', 'knowledge')
            ->assertSet('listing_ai_faq.roof_age_and_condition', 'Roof replaced in 2021.')
            ->call('backToTerms')
            ->assertSet('step', 'terms')
            ->call('continueToKnowledge')
            ->assertSet('step', 'knowledge');

        $this->assertSame(
            'Roof replaced in 2021.',
            $component->get('listing_ai_faq')['roof_age_and_condition'] ?? null,
            'The answer was lost on the way back through the terms step.'
        );
    }

    /**
     * @test
     *
     * Every question is optional. Continue must work with nothing entered, and
     * Skip for now must land in the same place.
     */
    public function the_step_can_be_completed_with_no_answers_at_all(): void
    {
        $this->seedRecord('KB-EMPTY', 'Residential');

        $this->toKnowledge('seller', 'KB-EMPTY')
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->assertSet('errorMessage', '');

        $this->seedRecord('KB-SKIP', 'Residential');

        $this->toKnowledge('seller', 'KB-SKIP')
            ->call('skipKnowledge')
            ->assertSet('step', 'review')
            ->assertSet('errorMessage', '');
    }

    /**
     * @test
     *
     * "Skip for now" is a label on the same transition, not a discard. A user who
     * types an answer and then skips has still answered it.
     */
    public function skipping_still_saves_what_was_already_typed(): void
    {
        $this->seedRecord('KB-SKIP-SAVE', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-SKIP-SAVE')
            ->set('listing_ai_faq.known_defects_issues', 'Guest bath faucet drips.')
            ->call('skipKnowledge')
            ->assertSet('step', 'review');

        $auction = \App\Models\SellerAgentAuction::find($component->get('listingId'));

        $this->assertSame(
            ['known_defects_issues' => 'Guest bath faucet drips.'],
            json_decode($auction->info('listing_ai_faq'), true)
        );
    }

    // ─── Persistence ─────────────────────────────────────────────────────────

    /**
     * @test
     *
     * The whole round trip: import → answer → publish → open Edit Listing and the
     * answers are there, in the same property the manual wizard binds.
     */
    public function answers_survive_publish_and_reappear_in_edit_listing(): void
    {
        $this->seedRecord('KB-ROUND', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-ROUND')
            ->set('listing_ai_faq.roof_age_and_condition', 'Roof replaced 2021, architectural shingle.')
            ->set('listing_ai_faq.seller_motivation_for_selling', 'Relocating for work.')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $listingId = $component->get('listingId');

        $component->call('publish');

        $auction = \App\Models\SellerAgentAuction::find($listingId);

        $this->assertNotNull($auction);
        $this->assertSame(0, (int) $auction->is_draft, 'The listing did not publish.');

        $stored = json_decode($auction->info('listing_ai_faq'), true);

        $this->assertSame([
            'seller_motivation_for_selling' => 'Relocating for work.',
            'roof_age_and_condition'        => 'Roof replaced 2021, architectural shingle.',
        ], $stored);

        // …and Edit Listing — the SAME component a manually created listing opens
        // in — repopulates from that meta with no knowledge of how it got there.
        Livewire::actingAs($this->seller)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $listingId])
            ->assertSet('listing_ai_faq.roof_age_and_condition', 'Roof replaced 2021, architectural shingle.')
            ->assertSet('listing_ai_faq.seller_motivation_for_selling', 'Relocating for work.');
    }

    /**
     * @test
     *
     * The landlord half of the same round trip.
     */
    public function landlord_answers_survive_publish_and_reappear_in_edit_listing(): void
    {
        $this->seedRecord('KB-L-ROUND', 'Residential Lease', 3200);

        $component = $this->toKnowledge('landlord', 'KB-L-ROUND')
            ->set('listing_ai_faq.guest_parking', 'Two marked visitor spaces by the mailboxes.')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $listingId = $component->get('listingId');
        $component->call('publish');

        Livewire::actingAs($this->landlord)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $listingId])
            ->assertSet('listing_ai_faq.guest_parking', 'Two marked visitor spaces by the mailboxes.');
    }

    /**
     * @test
     *
     * Publish is a safety net, not the only writer: an answer typed on the
     * knowledge step is already in meta before Review is reached, so an abandoned
     * import does not lose it.
     */
    public function answers_are_persisted_on_leaving_the_step_not_only_at_publish(): void
    {
        $this->seedRecord('KB-EARLY', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-EARLY')
            ->set('listing_ai_faq.average_utility_costs', 'About $180 a month.')
            ->call('continueToReview');

        $auction = \App\Models\SellerAgentAuction::find($component->get('listingId'));

        $this->assertSame(
            ['average_utility_costs' => 'About $180 a month.'],
            json_decode($auction->info('listing_ai_faq'), true),
            'Answers were not written when leaving the knowledge step.'
        );
        $this->assertSame(1, (int) $auction->is_draft, 'The listing should still be a draft at Review.');
    }

    /**
     * @test
     *
     * Resuming an import by re-entering the MLS number restores the answers
     * already given, rather than presenting an empty form over stored values.
     */
    public function resuming_a_draft_rehydrates_previous_answers(): void
    {
        $this->seedRecord('KB-RESUME', 'Residential');

        $this->toKnowledge('seller', 'KB-RESUME')
            ->set('listing_ai_faq.pest_termite_history', 'Treated for termites in 2019; annual bond since.')
            ->call('continueToReview');

        // A completely fresh component — a new page load, the same MLS number.
        $resumed = $this->toKnowledge('seller', 'KB-RESUME');

        $this->assertSame(
            'Treated for termites in 2019; annual bond since.',
            $resumed->get('listing_ai_faq')['pest_termite_history'] ?? null,
            'A resumed import did not rehydrate its stored knowledge base answers.'
        );
    }

    // ─── Admission boundary ──────────────────────────────────────────────────

    /**
     * @test
     *
     * `listing_ai_faq` is a public Livewire array, so a client may put ANY key in
     * it. A key this role and property type does not ask must not reach meta.
     *
     * Three separate shapes of injection, because they fail differently: a key
     * from another property type's group, a key from another ROLE's config, and a
     * key that exists nowhere at all.
     */
    public function injected_keys_are_not_admitted_to_the_stored_knowledge_base(): void
    {
        $this->seedRecord('KB-INJECT', 'Commercial Sale');

        $component = $this->toKnowledge('seller', 'KB-INJECT')
            ->assertSet('property_type', 'Commercial')
            // Legitimate: a commercial question, on a commercial listing.
            ->set('listing_ai_faq.commercial_ada_accessibility', 'Ramped entry, accessible restroom.')
            // Wrong property type — a Seller RESIDENTIAL question.
            ->set('listing_ai_faq.roof_age_and_condition', 'INJECTED — residential question on a commercial listing')
            // Wrong role — a LANDLORD question.
            ->set('listing_ai_faq.typical_tenancy_length', 'INJECTED — landlord question on a seller listing')
            // Not a question at all.
            ->set('listing_ai_faq.totally_made_up_key', 'INJECTED — no such question anywhere')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $auction = \App\Models\SellerAgentAuction::find($component->get('listingId'));
        $stored  = json_decode($auction->info('listing_ai_faq'), true);

        $this->assertSame(
            ['commercial_ada_accessibility' => 'Ramped entry, accessible restroom.'],
            $stored,
            'An unasked key reached the stored knowledge base.'
        );

        foreach (['roof_age_and_condition', 'typical_tenancy_length', 'totally_made_up_key'] as $injected) {
            $this->assertArrayNotHasKey($injected, $stored);
        }
    }

    /**
     * @test
     *
     * The injection survives to publish() too — the safety-net write must apply
     * the same gate, or skipping Review would be a way around it.
     */
    public function injected_keys_are_not_admitted_at_publish_either(): void
    {
        $this->seedRecord('KB-INJECT-PUB', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-INJECT-PUB')
            ->call('continueToReview')
            // Set AFTER reaching review, so only publish() can write it.
            ->set('listing_ai_faq.commercial_cam_structure', 'INJECTED at the review step')
            ->call('publish');

        $auction = \App\Models\SellerAgentAuction::find($component->get('listingId'));

        $this->assertArrayNotHasKey(
            'commercial_cam_structure',
            json_decode($auction->info('listing_ai_faq'), true) ?: []
        );
    }

    /**
     * @test
     *
     * A non-scalar value is a client shaping the blob, not a person answering a
     * question. It must not be stored, and it must not raise.
     */
    public function non_scalar_answers_are_rejected_without_error(): void
    {
        $this->seedRecord('KB-SHAPE', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-SHAPE')
            ->set('listing_ai_faq.roof_age_and_condition', ['nested' => 'payload'])
            ->set('listing_ai_faq.hvac_system_age', '   ')
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->assertSet('errorMessage', '');

        $auction = \App\Models\SellerAgentAuction::find($component->get('listingId'));

        $this->assertSame([], json_decode($auction->info('listing_ai_faq'), true));
    }

    /**
     * @test
     *
     * The gate and the form read ONE definition. Every key the step renders must
     * be admissible, and every admissible key must be rendered — otherwise a user
     * types into a box whose answer is silently dropped, or a key nobody can see
     * is writable.
     */
    public function every_rendered_question_is_admissible_and_vice_versa(): void
    {
        foreach ([
            ['seller', 'KB-SYM-S1', 'Residential', 'Residential'],
            ['seller', 'KB-SYM-S2', 'Commercial Sale', 'Commercial'],
            ['landlord', 'KB-SYM-L1', 'Residential Lease', 'Residential Property'],
        ] as [$role, $mls, $sourceType, $expectedType]) {
            $this->seedRecord($mls, $sourceType, $role === 'landlord' ? 3200 : 450000);

            $rendered = $this->renderedKeys($this->toKnowledge($role, $mls)->lastRenderedDom);

            $admissible = AskAiFaqConfigService::gatedKeys($role, $expectedType);
            sort($admissible);

            $this->assertSame(
                $admissible,
                $rendered,
                "{$role}/{$expectedType}: the rendered questions and the admissible keys are not the same set."
            );
        }
    }

    // ─── Review summary ──────────────────────────────────────────────────────

    /**
     * @test
     *
     * Review states that the step was captured, and counts through the admission
     * gate — an injected key must not be reported back as captured knowledge.
     */
    public function review_reports_the_admitted_answer_count(): void
    {
        $this->seedRecord('KB-REVIEW', 'Residential');

        $component = $this->toKnowledge('seller', 'KB-REVIEW')
            ->set('listing_ai_faq.roof_age_and_condition', 'Roof is four years old.')
            ->set('listing_ai_faq.commercial_cam_structure', 'INJECTED')
            ->call('continueToReview');

        $component->assertSee('AI Knowledge Base')
            ->assertSeeHtml('1 of');

        $this->assertSame(1, $component->instance()->knowledgeAnsweredCount());
    }

    /**
     * @test
     *
     * An untouched knowledge base is reported as such rather than as a blank
     * space, and publishing is not blocked.
     */
    public function review_reports_an_empty_knowledge_base_plainly(): void
    {
        $this->seedRecord('KB-REVIEW-EMPTY', 'Residential');

        $this->toKnowledge('seller', 'KB-REVIEW-EMPTY')
            ->call('continueToReview')
            ->assertSee('No questions answered');
    }

    // ─── Step ordering ───────────────────────────────────────────────────────

    /**
     * @test
     *
     * The wizard's six steps, in order, with AI Knowledge Base between Your Terms
     * and Review.
     */
    public function the_wizard_shows_six_steps_with_knowledge_between_terms_and_review(): void
    {
        $this->seedRecord('KB-STEPS', 'Residential');

        $html = $this->toKnowledge('seller', 'KB-STEPS')->lastRenderedDom;

        $positions = [];
        foreach (['1. MLS #', '2. Confirm Property', '3. Listing Method', '4. Your Terms', '5. AI Knowledge Base', '6. Review'] as $label) {
            $at = strpos($html, $label);
            $this->assertNotFalse($at, "The progress bar is missing the step '{$label}'.");
            $positions[] = $at;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'The wizard steps are not rendered in order.');
    }
}
