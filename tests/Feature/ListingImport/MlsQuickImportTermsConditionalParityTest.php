<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "YOUR TERMS" BEHAVES THE SAME ON MLS QUICK IMPORT AS IT DOES ON CREATE.
 *
 * THE REGRESSION BEING PINNED
 * ---------------------------
 * The canonical Sale Terms and Leasing Terms tabs were already shared with MLS
 * Quick Import — the same Blade file, not a copy — but the JavaScript that
 * OPERATES those tabs was not, because it never lived in the tab. It sat in each
 * manual page's own @push('scripts'), duplicated across offer-seller-listing,
 * offer-seller-listing-edit and hire-seller-agent, and Quick Import has no such
 * block. So Quick Import rendered all ten seller conditional sections with
 * style="display: none" and nothing on the page could open one.
 *
 * The parent answer was lost as well. Both parent selects sit inside wire:ignore
 * with no wire:model, so @this.set() in that page JS was the only thing that ever
 * carried a choice to the server; with the JS absent, choosing "Assumable" stored
 * offered_financing as "[]".
 *
 * WHAT THESE TESTS CAN AND CANNOT SEE
 * -----------------------------------
 * PHPUnit does not run jQuery, so nothing here proves a click reveals a section
 * in a browser — that is listed as manual verification and is not simulated. What
 * IS provable, and is what actually broke, splits in two:
 *
 *   1. STRUCTURE — the shared behaviour reaches the page at all, from the
 *      canonical partial rather than from a per-page copy, and the duplicates are
 *      gone. Asserted against the real HTTP response, because @push('scripts')
 *      renders into the layout's stack and is therefore invisible to
 *      Livewire::test()->lastRenderedDom.
 *
 *   2. SERVER-RENDERED STATE — every section's open/closed state is emitted by
 *      Blade from the stored parent answer. That is the half a browser test would
 *      not add much to, and it is what makes a revealed section survive a
 *      re-render, a wizard Back, and a validation failure.
 */
class MlsQuickImportTermsConditionalParityTest extends TestCase
{
    use RefreshDatabase;

    private const BEHAVIOUR_PARTIAL =
        'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/_seller-terms-behaviour.blade.php';

    private const CANONICAL_PARTIAL =
        'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';

    private const LANDLORD_BEHAVIOUR_PARTIAL =
        'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/_lease-terms-behaviour.blade.php';

    private const LANDLORD_CANONICAL_PARTIAL =
        'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php';

    private const QUICK_IMPORT_VIEW =
        'resources/views/livewire/offer-listing/quick-import/mls-quick-import.blade.php';

    /**
     * Every seller conditional section in the canonical tab, and the financing or
     * provision answer that opens it.
     *
     * Not a second condition map: the structural test below re-derives the same
     * pairs from the Blade and the behaviour partial and compares them to this, so
     * a section added to one and not the other fails rather than drifting.
     */
    private const SELLER_SECTIONS = [
        'Assumable'                => 'seller-financing-assumable-section',
        'Cryptocurrency'           => 'seller-financing-crypto-section',
        'Exchange/Trade'           => 'seller-financing-exchange-section',
        'Lease Option'             => 'seller-financing-leaseoption-section',
        'Lease Purchase'           => 'seller-financing-leasepurchase-section',
        'Non-Fungible Token (NFT)' => 'seller-financing-nft-section',
        'Seller Financing'         => 'seller-financing-sellerfinancing-section',
        'Other'                    => 'seller-financing-other-section',
    ];

    private const PROVISION_SECTIONS = [
        'Assignment Contract' => 'seller-provision-assignment-section',
        'Other'               => 'seller-provision-other-section',
    ];

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedRecord(string $mls, string $propertyType, int $price): void
    {
        BridgeProperty::create([
            'listing_key'       => $mls . '-KEY',
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'mls_status'        => 'Active',
            'property_type'     => $propertyType,
            'list_price'        => $price,
            'unparsed_address'  => '1 Test Street',
            'city'              => 'TAMPA',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '1 Test Street',
            ]),
        ]);
    }

    /** Drive a seller quick import to the terms step. */
    private function sellerToTerms(string $mls = 'QI-TERMS-S'): \Livewire\Testing\TestableLivewire
    {
        $this->seedRecord($mls, 'Residential', 500000);

        return Livewire::actingAs(User::factory()->create())
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    /** Drive a landlord quick import to the terms step. */
    private function landlordToTerms(string $mls, string $type): \Livewire\Testing\TestableLivewire
    {
        $this->seedRecord($mls, $type, 3200);

        return Livewire::actingAs(User::factory()->create())
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    /** The inline display style Blade emitted for one element id. */
    private function displayOf(string $html, string $id): string
    {
        $this->assertMatchesRegularExpression(
            '/id="' . preg_quote($id, '/') . '"/',
            $html,
            "Element #{$id} was not rendered at all."
        );

        preg_match('/id="' . preg_quote($id, '/') . '"[^>]*/', $html, $m);

        if (preg_match('/display:\s*([a-z-]+)/i', $m[0], $d)) {
            return strtolower($d[1]);
        }

        return 'unset';
    }

    private function assertOpen(string $html, string $id): void
    {
        $this->assertSame('block', $this->displayOf($html, $id),
            "#{$id} should be revealed but Blade emitted it closed.");
    }

    private function assertClosed(string $html, string $id): void
    {
        $this->assertSame('none', $this->displayOf($html, $id),
            "#{$id} should be hidden but Blade emitted it open.");
    }

    private function source(string $relative): string
    {
        // NOT base_path(): a worktree may run with a symlinked vendor/, which makes
        // Laravel resolve its base path to the checkout that owns the autoloader
        // rather than to the files under test. Anchor on this file instead.
        $path = dirname(__DIR__, 3) . '/' . $relative;
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    // ─── 1. Structural: the behaviour reaches the page ───────────────────────

    /**
     * @test
     *
     * The real HTTP response for Seller Quick Import carries the shared handlers.
     * This is the assertion that failed before the fix, and it is made against the
     * full page because @push('scripts') renders into the layout's stack.
     */
    public function seller_quick_import_page_carries_the_shared_conditional_behaviour(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('offer.listing.seller.quick-import'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('applyProvisionVisibility', $html);
        $this->assertStringContainsString('applyFinancingVisibility', $html);
        $this->assertStringContainsString("on('change', '#sale_provision'", $html);
        $this->assertStringContainsString("on('change', '#offered_financing'", $html);
    }

    /**
     * @test
     *
     * …and the Landlord page carries its own three.
     */
    public function landlord_quick_import_page_carries_the_shared_conditional_behaviour(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('offer.listing.landlord.quick-import'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('applyLandlordOwnerPaysVisibility', $html);
        $this->assertStringContainsString('applyLandlordLeaseTermVisibility', $html);
        $this->assertStringContainsString('applyLandlordTermsOfLeaseVisibility', $html);
    }

    /**
     * @test
     *
     * Bound once. Two delegated handlers on one select means two @this.set()
     * round trips for a single change, which is the defect that removing the
     * duplicates was meant to avoid.
     */
    public function the_shared_seller_handlers_are_bound_exactly_once_per_page(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('offer.listing.seller.quick-import'))
            ->getContent();

        $this->assertSame(1, substr_count($html, "on('change', '#sale_provision'"));
        $this->assertSame(1, substr_count($html, "on('change', '#offered_financing'"));
        $this->assertStringContainsString('__sellerTermsConditionalsBound', $html);
    }

    /**
     * @test
     *
     * The behaviour is included BY THE CANONICAL PARTIAL. That is the property
     * that makes a future consumer of the tab work without anybody remembering to
     * copy JavaScript, and it is the thing that was missing.
     */
    public function the_canonical_partial_owns_the_behaviour(): void
    {
        $this->assertStringContainsString(
            '_seller-terms-behaviour',
            $this->source(self::CANONICAL_PARTIAL),
            'The canonical Sale Terms tab must include the shared behaviour partial.'
        );

        $this->assertStringContainsString(
            '_lease-terms-behaviour',
            $this->source(self::LANDLORD_CANONICAL_PARTIAL),
            'The canonical Leasing Terms tab must include the shared behaviour partial.'
        );
    }

    /**
     * @test
     *
     * Quick Import must never grow conditional logic of its own. It may include
     * the shared partial — that is how it receives behaviour — but the maps, the
     * section ids and the toggle functions must not appear in its own template.
     */
    public function quick_import_declares_no_conditional_javascript_of_its_own(): void
    {
        // Blade comments are stripped before render, and this file's comments discuss
        // script execution by name, so strip them before looking for real markup.
        $view = preg_replace('/\{\{--.*?--\}\}/s', '', $this->source(self::QUICK_IMPORT_VIEW));

        $this->assertStringNotContainsString('<script', $view,
            'mls-quick-import.blade.php must not carry script of its own.');

        $this->assertStringNotContainsString('function applyFinancingVisibility', $view);
        $this->assertStringNotContainsString('function applyProvisionVisibility', $view);

        foreach (array_merge(self::SELLER_SECTIONS, self::PROVISION_SECTIONS) as $sectionId) {
            $this->assertStringNotContainsString($sectionId, $view,
                "Quick Import must not name #{$sectionId}; the canonical partial owns it.");
        }
    }

    /**
     * @test
     *
     * The duplicated copies are gone from every wrapper that used to carry one.
     * If one comes back, this page ends up binding twice.
     */
    public function the_duplicated_wrapper_copies_have_been_removed(): void
    {
        $wrappers = [
            'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
            'resources/views/livewire/hire-seller-agent/hire-seller-agent.blade.php',
        ];

        foreach ($wrappers as $wrapper) {
            $src = $this->source($wrapper);

            $this->assertStringNotContainsString('function applyFinancingVisibility', $src,
                "{$wrapper} still declares its own copy.");
            $this->assertStringNotContainsString('function applyProvisionVisibility', $src,
                "{$wrapper} still declares its own copy.");
            $this->assertStringNotContainsString("on('change', '#sale_provision'", $src,
                "{$wrapper} still binds the parent select itself.");
            $this->assertStringNotContainsString("on('change', '#offered_financing'", $src,
                "{$wrapper} still binds the parent select itself.");
        }

        foreach ([
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        ] as $wrapper) {
            $src = $this->source($wrapper);

            $this->assertStringNotContainsString("@this.call('updateOwnerPays'", $src,
                "{$wrapper} still calls updateOwnerPays from a change binding.");
            $this->assertStringNotContainsString("off('change.ltsSync')", $src,
                "{$wrapper} still binds the lease-term select itself.");
        }
    }

    /**
     * @test
     *
     * The behaviour partial's map and the canonical tab's markup describe the same
     * set of sections, compared in both directions so neither can grow alone.
     */
    public function the_behaviour_map_and_the_markup_agree_on_every_section(): void
    {
        $markup    = $this->source(self::CANONICAL_PARTIAL);
        $behaviour = $this->source(self::BEHAVIOUR_PARTIAL);

        preg_match_all('/id="(seller-(?:financing|provision)-[a-z-]+-section)"/', $markup, $m);
        $inMarkup = array_unique($m[1]);

        preg_match_all('/#(seller-(?:financing|provision)-[a-z-]+-section)/', $behaviour, $b);
        $inBehaviour = array_unique($b[1]);

        sort($inMarkup);
        sort($inBehaviour);

        $this->assertSame($inMarkup, $inBehaviour,
            'Every conditional section in the tab must be in the shared map, and vice versa.');

        $expected = array_values(array_unique(
            array_merge(array_values(self::SELLER_SECTIONS), array_values(self::PROVISION_SECTIONS))
        ));
        sort($expected);

        $this->assertSame($expected, $inMarkup,
            'The section inventory changed; update this test deliberately.');
    }

    /**
     * @test
     *
     * Cash has no child section and must not gain one.
     */
    public function cash_has_no_child_section(): void
    {
        $this->assertStringNotContainsString(
            "'Cash':",
            $this->source(self::BEHAVIOUR_PARTIAL),
            'Cash must not be given a conditional section.'
        );
    }

    // ─── 2. Seller: every branch opens ───────────────────────────────────────

    /**
     * @test
     * @dataProvider financingBranches
     */
    public function each_financing_branch_opens_its_section(string $value, string $sectionId): void
    {
        $component = $this->sellerToTerms()->set('offered_financing', [$value]);

        $this->assertOpen($component->lastRenderedDom, $sectionId);
    }

    public function financingBranches(): array
    {
        $cases = [];
        foreach (self::SELLER_SECTIONS as $value => $sectionId) {
            $cases[$value] = [$value, $sectionId];
        }

        return $cases;
    }

    /**
     * @test
     * @dataProvider provisionBranches
     */
    public function each_provision_branch_opens_its_section(string $value, string $sectionId): void
    {
        $component = $this->sellerToTerms()->set('sale_provision', [$value]);

        $this->assertOpen($component->lastRenderedDom, $sectionId);
    }

    public function provisionBranches(): array
    {
        return [
            'Assignment Contract' => ['Assignment Contract', 'seller-provision-assignment-section'],
            'Other'               => ['Other', 'seller-provision-other-section'],
        ];
    }

    /**
     * @test
     *
     * Assignment Contract reveals the section, and the nested follow-ups inside it
     * — which are ordinary server-side @if on their own parents — render once the
     * seller answers. Those never broke; what broke was that nobody could reach
     * them. This proves reaching the parent is now enough.
     */
    public function assignment_contract_reveals_its_nested_follow_ups(): void
    {
        $component = $this->sellerToTerms()
            ->set('sale_provision', ['Assignment Contract'])
            ->set('sale_provision_assignment', 'Yes');

        $html = $component->lastRenderedDom;

        $this->assertOpen($html, 'seller-provision-assignment-section');
        $this->assertStringContainsString('assignment_fee_type', $html);

        // The amount input sits behind the $ / % control, which Quick Import leaves
        // at '' exactly as manual Create does, so it appears only once that is
        // answered. Choosing it here proves the whole chain is reachable.
        $withFeeType = $component->set('assignment_fee_type', '$')->lastRenderedDom;
        $this->assertStringContainsString('assignment_fee_amount', $withFeeType);
    }

    /**
     * @test
     *
     * Assumable reveals the section and the fields inside it.
     */
    public function assumable_reveals_its_complete_section(): void
    {
        $html = $this->sellerToTerms()
            ->set('offered_financing', ['Assumable'])
            ->lastRenderedDom;

        $this->assertOpen($html, 'seller-financing-assumable-section');

        foreach (['assumable_loan_type', 'assumable_loan_servicer', 'assumption_fee_responsibility'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
    }

    /**
     * @test
     *
     * Several financing types at once reveal all of their sections and nothing else.
     */
    public function multiple_financing_types_reveal_every_applicable_section(): void
    {
        $html = $this->sellerToTerms()
            ->set('offered_financing', ['Assumable', 'Seller Financing', 'Other'])
            ->lastRenderedDom;

        $this->assertOpen($html, 'seller-financing-assumable-section');
        $this->assertOpen($html, 'seller-financing-sellerfinancing-section');
        $this->assertOpen($html, 'seller-financing-other-section');

        $this->assertClosed($html, 'seller-financing-crypto-section');
        $this->assertClosed($html, 'seller-financing-exchange-section');
        $this->assertClosed($html, 'seller-financing-nft-section');
    }

    /**
     * @test
     *
     * A child value left behind by a financing type the seller has since removed
     * does not reopen the branch. The parent decides — the same rule the published
     * listing follows.
     */
    public function stale_child_data_does_not_reveal_a_branch(): void
    {
        $html = $this->sellerToTerms()
            ->set('assumable_loan_servicer', 'Some Servicer Left Behind')
            ->set('assumable_loan_type', 'FHA')
            ->set('offered_financing', [])
            ->lastRenderedDom;

        $this->assertClosed($html, 'seller-financing-assumable-section');
    }

    // ─── 3. Seller: the answer itself survives ───────────────────────────────

    /**
     * @test
     *
     * The half of the defect that was not visible: the parent answer used to be
     * discarded, so a seller who chose Assumable published a listing that offered
     * no financing at all. It must persist as ["Assumable"], not "[]".
     */
    public function the_selected_financing_persists_rather_than_being_discarded(): void
    {
        $component = $this->sellerToTerms()
            ->set('offered_financing', ['Assumable'])
            ->set('sale_provision', ['Assignment Contract'])
            ->call('continueToReview');

        $component->assertSet('step', 'review');

        $listing = \App\Models\SellerAgentAuction::query()->latest('id')->first();
        $this->assertNotNull($listing, 'Quick Import should have written a draft.');

        $stored = $listing->get->offered_financing;
        $this->assertSame(
            ['Assumable'],
            is_array($stored) ? $stored : json_decode((string) $stored, true),
            'The parent financing answer must reach storage, not be discarded as "[]".'
        );
        // sale_provision is declared 'raw' in the meta map but is an array property,
        // so it round-trips as a list. That is pre-existing and identical to what
        // manual Create writes; what matters here is that the answer survives at all.
        $provision = $listing->get->sale_provision;
        $this->assertSame(
            ['Assignment Contract'],
            is_array($provision) ? $provision : json_decode((string) $provision, true),
            'The parent provision answer must reach storage, not be discarded.'
        );
    }

    // ─── 4. Lifecycle ────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Review, then Back: the parent answers and therefore the revealed sections
     * are still there. wire:ignore.self keeps the browser's copy; this proves the
     * server agrees, which is what a re-render paints from.
     */
    public function review_then_back_preserves_answers_and_visibility(): void
    {
        $component = $this->sellerToTerms()
            ->set('offered_financing', ['Assumable'])
            ->call('continueToReview')
            ->call('backToTerms');

        $component->assertSet('step', 'terms');
        $component->assertSet('offered_financing', ['Assumable']);

        $this->assertOpen($component->lastRenderedDom, 'seller-financing-assumable-section');
    }

    /**
     * @test
     *
     * A validation failure re-renders the terms step. A revealed section must not
     * collapse, or the seller loses sight of the questions they were answering.
     */
    public function a_validation_failure_re_render_keeps_sections_open(): void
    {
        $component = $this->sellerToTerms()
            ->set('offered_financing', ['Exchange/Trade'])
            ->set('purchase_price', '')
            ->set('maximum_budget', '')
            ->call('continueToReview');

        $html = $component->lastRenderedDom;

        // Whether or not this particular listing was complete enough to advance,
        // the section the seller opened is still open in the markup they get back.
        $this->assertOpen($html, 'seller-financing-exchange-section');
    }

    // ─── 5. Landlord ─────────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider landlordPropertyTypes
     *
     * Desired Lease Term → Other reveals its custom field on both property types.
     */
    public function desired_lease_length_other_reveals_its_field(string $mls, string $feedType): void
    {
        $html = $this->landlordToTerms($mls, $feedType)
            ->set('desired_lease_length', ['Other'])
            ->lastRenderedDom;

        $this->assertOpen($html, 'other_desired_lease_length_wrapper');
    }

    public function landlordPropertyTypes(): array
    {
        return [
            'residential' => ['QI-LL-RES', 'Residential Lease'],
            'commercial'  => ['QI-LL-COM', 'Commercial Lease'],
        ];
    }

    /**
     * @test
     *
     * Commercial Owner Pays → Other reveals its custom field.
     */
    public function commercial_owner_pays_other_reveals_its_field(): void
    {
        $html = $this->landlordToTerms('QI-LL-OP', 'Commercial Lease')
            ->set('owner_pays', ['Other'])
            ->lastRenderedDom;

        $this->assertOpen($html, 'other_owner_pays_wrapper');
    }

    /**
     * @test
     *
     * The two visibility flags are derived from the parent answer rather than set
     * by whichever page happened to declare them, so every consumer of the tab
     * renders the same initial state.
     */
    public function the_landlord_visibility_flags_track_their_parents(): void
    {
        $component = $this->landlordToTerms('QI-LL-FLAGS', 'Commercial Lease');

        $component->assertSet('is_other_owner_pays_visible', false);
        $component->assertSet('is_update_lease_term_option_visible', false);

        $component->set('owner_pays', ['Water', 'Other'])
            ->assertSet('is_other_owner_pays_visible', true);

        $component->set('owner_pays', ['Water'])
            ->assertSet('is_other_owner_pays_visible', false);

        $component->set('desired_lease_length', ['Other'])
            ->assertSet('is_update_lease_term_option_visible', true);
    }

    /**
     * @test
     *
     * Dropping "Other" from Owner Pays clears the free text that described it.
     *
     * The manual pages' JS used to get this by calling updateOwnerPays(); that call
     * was removed with the duplicate binding, so the trait hook has to carry the
     * same semantics or a landlord who changes their mind keeps publishing a
     * sentence about an expense they no longer cover.
     */
    public function dropping_other_from_owner_pays_clears_its_free_text(): void
    {
        $component = $this->landlordToTerms('QI-LL-CLR', 'Commercial Lease')
            ->set('owner_pays', ['Other'])
            ->set('other_owner_pays', 'HOA dues and trash');

        $component->assertSet('other_owner_pays', 'HOA dues and trash');

        $component->set('owner_pays', ['Water'])
            ->assertSet('is_other_owner_pays_visible', false)
            ->assertSet('other_owner_pays', '');
    }

    /**
     * @test
     *
     * terms_of_lease reaches the component. It is a commercial Select2 with no
     * wire:model, so before the shared behaviour nothing carried it.
     */
    public function terms_of_lease_synchronises(): void
    {
        $this->landlordToTerms('QI-LL-TOL', 'Commercial Lease')
            ->set('terms_of_lease', ['Triple Net / NNN'])
            ->assertSet('terms_of_lease', ['Triple Net / NNN']);

        $this->assertStringContainsString(
            "on('change', '#terms_of_lease'",
            $this->source(self::LANDLORD_BEHAVIOUR_PARTIAL)
        );
    }

    /**
     * @test
     *
     * The controls that already worked still work, and were not converted to
     * jQuery for the sake of uniformity. Alpine survives a wizard step arriving by
     * AJAX, which is precisely why these never had the defect.
     */
    public function the_already_working_alpine_branches_are_untouched(): void
    {
        $residential = $this->landlordToTerms('QI-LL-ALP-R', 'Residential Lease')->lastRenderedDom;
        $commercial  = $this->landlordToTerms('QI-LL-ALP-C', 'Commercial Lease')->lastRenderedDom;

        $this->assertStringContainsString('other_rent_includes_wrapper', $residential);
        $this->assertStringContainsString("x-show=\"isSelected('Other')\"", $residential);

        $this->assertStringContainsString('other_tenant_pays_wrapper', $commercial);
        $this->assertStringContainsString("x-show=\"isSelected('Other')\"", $commercial);
    }

    /**
     * @test
     *
     * commercial_lease_type → Other is a plain @if on a wire:model scalar and
     * still resolves server-side.
     */
    public function commercial_lease_type_other_still_reveals_server_side(): void
    {
        $html = $this->landlordToTerms('QI-LL-CLT', 'Commercial Lease')
            ->set('commercial_lease_type', 'Other')
            ->lastRenderedDom;

        $this->assertOpen($html, 'commercial_lease_type_other_wrapper');
    }

    /**
     * @test
     *
     * Residential and commercial boundaries are unchanged.
     */
    public function property_type_boundaries_are_unchanged(): void
    {
        $residential = $this->landlordToTerms('QI-LL-B-R', 'Residential Lease')->lastRenderedDom;
        $commercial  = $this->landlordToTerms('QI-LL-B-C', 'Commercial Lease')->lastRenderedDom;

        $this->assertStringNotContainsString('id="owner_pays"', $residential);
        $this->assertStringNotContainsString('commercial_lease_type_other_wrapper', $residential);

        $this->assertStringContainsString('id="owner_pays"', $commercial);
        $this->assertStringContainsString('commercial_lease_type_other_wrapper', $commercial);
    }
}
