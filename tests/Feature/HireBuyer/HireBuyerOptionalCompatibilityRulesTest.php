<?php

namespace Tests\Feature\HireBuyer;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Hire Buyer — compatibility preferences and Pets no longer block submission.
 *
 * WHAT WAS WRONG
 * --------------
 * Five Representation Preferences fields and Pets were `required` on the Hire Buyer submit path.
 * They are MATCHING PREFERENCES, not listing facts: a buyer who had described the property they
 * wanted in full still could not submit without also saying how they like to communicate. The
 * listing is complete without them; it simply matches on fewer dimensions.
 *
 * TWO ENFORCEMENT LAYERS, AND ONLY FIXING ONE WOULD HAVE CHANGED NOTHING
 * ---------------------------------------------------------------------
 * Representation Priorities was ALSO blocked client-side: `buyerGetInvalidItems()` pushed it into
 * the invalid list and the form's submit listener calls `e.preventDefault()` whenever that list is
 * non-empty. The browser refused the submit before Livewire was reached, so relaxing the PHP alone
 * would have left the field looking exactly as required as before. Both layers moved together, and
 * both are pinned here.
 *
 * PETS CARRIED A SECOND, SEPARATE DEFECT
 * --------------------------------------
 * Its rule fired for property types `['Residential', 'Income']` while its input renders only behind
 * `$property_type === 'Residential Property'`. A buyer listing holding the short spelling therefore
 * had a REQUIRED FIELD WITH NO VISIBLE INPUT — unfillable, and blocking with an error pointing at a
 * control that was not on the page. `nullable` removes the block; widening the property-type list to
 * both spellings removes the mismatch, so the rule can never again apply to a state the UI cannot
 * satisfy.
 *
 * BUYER ONLY. `TenantAgentAuction` serves all four roles from one method. The seller, landlord and
 * tenant branches keep their own `required` rules, and that is asserted here — a relaxation that
 * leaked into another role would be a silent loss of validation nobody asked for.
 *
 * NO DATABASE AND NO LIVEWIRE MOUNT. These rules are plain arrays handed to Laravel's validator, so
 * the contract is testable directly — which is what lets the ASSERTIONS BE ABOUT BEHAVIOUR (does a
 * blank value pass?) rather than about the text of a rule string.
 */
class HireBuyerOptionalCompatibilityRulesTest extends TestCase
{
    /** The five compatibility keys this change made optional, minus their `compatibility_preferences.` prefix. */
    private const COMPAT_FIELDS = [
        'primary_transaction_goal',
        'representation_priorities',
        'communication_style',
        'negotiation_style',
        'preferred_agent_working_style',
    ];

    /**
     * Every field key that must not block a buyer submit, keyed the way the client-side blocker
     * keys them: `wire:model` where there is one, element id for the `wire:ignore` Select2 selects
     * that have none.
     *
     * The first six are what the browser-reported banner listed. `pets` / `pets_income` are the two
     * ids the same optional field renders under, added when Pets turned out to be blocked by a
     * different mechanism in a different file — see section 3d.
     */
    private const NON_BLOCKING_KEYS = [
        'working_with_agent',                      // "Current Representation Status with Broker"
        'compat_primary_transaction_goal',
        'compat_representation_priorities',
        'compat_communication_style',
        'compat_negotiation_style',
        'compat_preferred_agent_working_style',
        'pets',                                    // Residential
        'pets_income',                             // Income
    ];

    private const TENANT_AGENT_AUCTION = 'app/Http/Livewire/TenantAgentAuction.php';
    private const HIRE_BUYER_AGENT     = 'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php';
    private const HIRE_BUYER_VIEW      = 'resources/views/livewire/hire-buyer-agent/hire-buyer-agent.blade.php';

    /**
     * The OTHER Hire Buyer shell.
     *
     * Hire Buyer is reachable through two views. `/buyer/add-auction` renders
     * {@see self::HIRE_BUYER_VIEW}; `/hire/agent/auction/buyer` renders this one, which serves all
     * four roles from one file and branches on `curUT`. A client-side fix applied to only one of
     * them changes the flow for only some of the buyers who use it.
     */
    private const TENANT_SHELL_VIEW = 'resources/views/livewire/tenant-agent-auction.blade.php';

    private const TENANT_OFFER_LISTING = 'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php';

    private const BUYER_COMPAT_PARTIAL =
        'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/representation-compatibility.blade.php';
    private const BUYER_LISTING_DETAILS =
        'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/listing-details.blade.php';
    private const BUYER_PROPERTY_PREFS =
        'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php';

    /** Every Hire Agent partial that renders a Pets select, by role. */
    private const PETS_PARTIALS = [
        'buyer'    => self::BUYER_PROPERTY_PREFS,
        'tenant'   => 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/pre-screening.blade.php',
        'seller'   => 'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php',
        'landlord' => 'resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php',
    ];

    /** The same tab partial for each of the three roles that must keep its required markers. */
    private const OTHER_ROLE_LISTING_DETAILS = [
        'tenant'   => 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/listing-details.blade.php',
        'seller'   => 'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/listing-details.blade.php',
        'landlord' => 'resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/listing-details.blade.php',
    ];

    private function source(string $path): string
    {
        $full = base_path($path);
        $this->assertFileExists($full, "Expected file missing: {$path}");

        return (string) file_get_contents($full);
    }

    /**
     * The file's markup with Blade comments removed.
     *
     * Every one of these removals is annotated with a comment that NAMES the attribute it took
     * out, so a naive search for `required` matches the explanation and reports the opposite of
     * the truth. Only the markup the browser actually receives is evidence here.
     */
    private function markup(string $path): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $this->source($path));
    }

    /**
     * The view as Blade compiles it — what actually reaches PHP and, through it, the browser.
     *
     * Blade directives can compile to something the source does not read like. Assertions about
     * anything a directive produces belong here rather than against the raw file.
     */
    private function compiledView(string $path): string
    {
        return \Illuminate\Support\Facades\Blade::compileString($this->source($path));
    }

    /**
     * The opening tag of `<select id="...">` (or `<input>` / `<textarea>`), so a test can ask
     * whether THAT control is marked required rather than whether the word appears in the file.
     */
    private function controlTag(string $markup, string $id): string
    {
        $this->assertMatchesRegularExpression(
            '/<(?:select|input|textarea)\b[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/',
            $markup,
            "Expected a control with id=\"{$id}\" to be rendered."
        );

        preg_match('/<(?:select|input|textarea)\b[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/', $markup, $m);

        return $m[0];
    }

    /**
     * The buyer rules exactly as the components now build them.
     *
     * Mirrored rather than invoked because `validateOnlyFilledFields()` is a protected method on a
     * 5,000-line Livewire component that cannot be exercised without a full mount. The mirror is
     * kept honest by {@see self::test_the_shipped_buyer_rules_match_the_rules_asserted_here}, which
     * fails if the component and this array ever disagree.
     *
     * @return array<string, string>
     */
    private function buyerCompatRules(): array
    {
        return [
            'compatibility_preferences.buyer_specific.primary_transaction_goal'      => 'nullable|string',
            'compatibility_preferences.buyer_specific.representation_priorities'     => 'nullable|array',
            'compatibility_preferences.buyer_specific.communication_style'           => 'nullable|string',
            'compatibility_preferences.buyer_specific.negotiation_style'             => 'nullable|string',
            'compatibility_preferences.buyer_specific.preferred_agent_working_style' => 'nullable|string',
            'pets'                                                                   => 'nullable',
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · BLANK COMPATIBILITY FIELDS SUBMIT
    // ═════════════════════════════════════════════════════════════════════════

    /** Every compatibility field absent from the payload entirely — the "never opened the tab" case. */
    public function test_a_payload_with_no_compatibility_fields_at_all_passes(): void
    {
        $validator = Validator::make([], $this->buyerCompatRules());

        $this->assertFalse($validator->fails(), 'A submit with no compatibility preferences must pass: '
            . json_encode($validator->errors()->all()));
    }

    /** Present but blank — the "opened the tab, chose nothing" case. Blank must be as good as absent. */
    public function test_blank_compatibility_values_pass(): void
    {
        $payload = ['compatibility_preferences' => ['buyer_specific' => [
            'primary_transaction_goal'      => '',
            'representation_priorities'     => [],
            'communication_style'           => '',
            'negotiation_style'             => '',
            'preferred_agent_working_style' => '',
        ]]];

        $validator = Validator::make($payload, $this->buyerCompatRules());

        $this->assertFalse($validator->fails(), 'Blank compatibility values must not block submit: '
            . json_encode($validator->errors()->all()));
    }

    /** Null is the shape Livewire sends for a cleared select; it must behave like blank. */
    public function test_null_compatibility_values_pass(): void
    {
        $payload = ['compatibility_preferences' => ['buyer_specific' => [
            'primary_transaction_goal'      => null,
            'representation_priorities'     => null,
            'communication_style'           => null,
            'negotiation_style'             => null,
            'preferred_agent_working_style' => null,
        ]]];

        $this->assertFalse(Validator::make($payload, $this->buyerCompatRules())->fails());
    }

    /**
     * Each field individually, so a future regression names itself.
     *
     * A single all-blank assertion would report "something failed"; this reports WHICH.
     */
    public function test_each_compatibility_field_is_individually_optional(): void
    {
        foreach (self::COMPAT_FIELDS as $field) {
            $blank   = $field === 'representation_priorities' ? [] : '';
            $payload = ['compatibility_preferences' => ['buyer_specific' => [$field => $blank]]];

            $validator = Validator::make($payload, $this->buyerCompatRules());

            $this->assertFalse(
                $validator->errors()->has("compatibility_preferences.buyer_specific.{$field}"),
                "{$field} must be optional on the Hire Buyer submit path."
            );
        }
    }

    /**
     * OPTIONAL IS NOT UNVALIDATED. A supplied value still has to be the right shape, because the
     * match scorer reads these — a string where an array belongs would break it just as surely
     * after this change as before.
     */
    public function test_a_supplied_value_is_still_type_checked(): void
    {
        $wrongTypes = Validator::make(
            ['compatibility_preferences' => ['buyer_specific' => [
                'representation_priorities' => 'not-an-array',
                'communication_style'       => ['not', 'a', 'string'],
            ]]],
            $this->buyerCompatRules()
        );

        $this->assertTrue($wrongTypes->fails(), 'Supplied values must still be type-checked.');
        $this->assertTrue($wrongTypes->errors()->has('compatibility_preferences.buyer_specific.representation_priorities'));
        $this->assertTrue($wrongTypes->errors()->has('compatibility_preferences.buyer_specific.communication_style'));
    }

    /** And a fully-filled payload still passes — the change must not have broken the happy path. */
    public function test_a_fully_completed_compatibility_payload_still_passes(): void
    {
        $payload = ['compatibility_preferences' => ['buyer_specific' => [
            'primary_transaction_goal'      => 'Best price',
            'representation_priorities'     => ['Negotiation', 'Market knowledge'],
            'communication_style'           => 'Text',
            'negotiation_style'             => 'Assertive',
            'preferred_agent_working_style' => 'Collaborative',
        ]], 'pets' => 'Yes'];

        $this->assertFalse(Validator::make($payload, $this->buyerCompatRules())->fails());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · PETS
    // ═════════════════════════════════════════════════════════════════════════

    /** Blank Pets does not block. */
    public function test_blank_pets_does_not_block_submit(): void
    {
        foreach ([[], ['pets' => ''], ['pets' => null]] as $payload) {
            $validator = Validator::make($payload, $this->buyerCompatRules());

            $this->assertFalse(
                $validator->errors()->has('pets'),
                'Blank Pets must not block the Hire Buyer submit: ' . json_encode($payload)
            );
        }
    }

    /**
     * `Pets = "No"` submits.
     *
     * Worth stating even though `required` never rejected the string "No": the reported symptom was
     * that choosing No appeared to block, and a test that pins the accepted VALUES is what makes the
     * answer checkable instead of arguable.
     */
    public function test_pets_no_submits(): void
    {
        $validator = Validator::make(['pets' => 'No'], $this->buyerCompatRules());

        $this->assertFalse($validator->fails(), 'Pets = "No" must submit.');
        $this->assertFalse($validator->errors()->has('pets'));
    }

    /** Both selectable values are accepted; neither is treated as empty. */
    public function test_both_pets_values_are_accepted(): void
    {
        foreach (['Yes', 'No'] as $value) {
            $this->assertFalse(
                Validator::make(['pets' => $value], $this->buyerCompatRules())->errors()->has('pets'),
                "Pets = \"{$value}\" must be a valid selection."
            );
        }
    }

    /**
     * The property-type mismatch is gone: the Pets rule's gate now names BOTH spellings.
     *
     * The input renders behind `'Residential Property'`; the rule used to fire only for the short
     * forms. A hidden required field cannot recur while the two agree.
     */
    public function test_the_pets_rule_covers_both_property_type_spellings(): void
    {
        $src = $this->source(self::TENANT_AGENT_AUCTION);

        $this->assertStringContainsString(
            "in_array(\$this->property_type, ['Residential Property', 'Residential', 'Income Property', 'Income'])",
            $src,
            'The Pets rule must cover both property-type spellings so its input is always reachable.'
        );

        $this->assertStringNotContainsString(
            "\$rules['pets'] = 'required'",
            $src,
            'Pets must no longer be required on any branch of this component.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2b · PETS WAS BLOCKED CLIENT-SIDE, IN THE OTHER SHELL
    // ═════════════════════════════════════════════════════════════════════════
    //
    // The `nullable` rule above was correct and still did not unblock Pets, for the same reason the
    // compatibility fields stayed blocked — but in a different file, so the earlier fix did not
    // reach it.
    //
    // HIRE BUYER IS REACHABLE THROUGH TWO VIEWS. `/buyer/add-auction` renders
    // `hire-buyer-agent.blade.php`, which mentions Pets nowhere. `/hire/agent/auction/buyer` renders
    // `tenant-agent-auction.blade.php`, which serves all four roles and, inside its
    // `curUT === 'buyer'` branch, pushed `pets` into `invalidItems` whenever property_type was
    // Residential or Income. That handler calls `e.preventDefault()` on a non-empty list, so Pets
    // blocked submission on that route and only that route.
    //
    // The push also had no counterpart in `tenantGetInvalidItems()`, which refreshes the banner on
    // each Livewire round-trip. Pets was therefore named on the blocked submit and then vanished
    // from the list while still blocking — the field the buyer was told to fix stopped being the
    // field the banner showed.

    /** Pets is no longer pushed into the buyer invalid-items list. */
    public function test_pets_is_not_pushed_into_the_buyer_invalid_items_list(): void
    {
        $markup = $this->markup(self::TENANT_SHELL_VIEW);

        $this->assertStringNotContainsString(
            "key: 'pets'",
            $markup,
            'Pets must not be pushed into the buyer invalid-items list — that push is a hard '
            . 'submit block regardless of the server rule.'
        );

        $this->assertStringNotContainsString(
            "comp.get('pets')",
            $markup,
            'The Livewire-state read that fed the Pets block must be gone with it.'
        );
    }

    /** The three blank / Yes / No cases the browser report named, against the shipped buyer rule. */
    public function test_a_buyer_submits_with_pets_blank_yes_or_no(): void
    {
        foreach ([
            'blank (never touched the select)' => [],
            'blank (opened, chose nothing)'    => ['pets' => ''],
            'cleared'                          => ['pets' => null],
            'Yes'                              => ['pets' => 'Yes'],
            'No'                               => ['pets' => 'No'],
        ] as $case => $payload) {
            $validator = Validator::make($payload, $this->buyerCompatRules());

            $this->assertFalse(
                $validator->errors()->has('pets'),
                "A buyer submitting with Pets {$case} must not be blocked: "
                . json_encode($validator->errors()->all())
            );
        }
    }

    /** Both Pets selects still render, still bind, and still offer both values. */
    public function test_the_buyer_pets_selects_are_still_rendered_and_bound(): void
    {
        $markup = $this->markup(self::BUYER_PROPERTY_PREFS);

        // Residential renders #pets, Income renders #pets_income; both write the same property.
        foreach (['pets', 'pets_income'] as $id) {
            $tag = $this->controlTag($markup, $id);

            $this->assertStringContainsString('wire:model="pets"', $tag,
                "#{$id} must still bind to the pets property so a supplied value is saved and matched.");
            $this->assertStringNotContainsString('required', $tag,
                "#{$id} must not carry the HTML required attribute: {$tag}");
        }

        foreach (['<option value="Yes">Yes</option>', '<option value="No">No</option>'] as $option) {
            $this->assertStringContainsString($option, $markup,
                'Both Pets values must remain selectable.');
        }
    }

    /**
     * NO OTHER ROLE LOSES A PETS REQUIREMENT, BECAUSE IN THIS FLOW NO OTHER ROLE HAS ONE.
     *
     * Worth stating explicitly rather than leaving as an absence. `$rules['pets']` appears exactly
     * once in the four-role component, inside the buyer branch; the tenant and landlord branches
     * never set it, and no role's Pets select carries a `required` attribute. So "other roles keep
     * requiring Pets" is vacuous HERE — the thing that must not move is in a different flow, pinned
     * by the next test.
     */
    public function test_no_other_role_in_the_hire_agent_flow_requires_pets(): void
    {
        $src = $this->source(self::TENANT_AGENT_AUCTION);

        $this->assertSame(
            1,
            preg_match_all("/\\\$rules\['pets'\]\s*=/", $src),
            'Exactly one pets rule may exist in this component — the buyer one, which is nullable.'
        );
        $this->assertMatchesRegularExpression("/\\\$rules\['pets'\]\s*=\s*'nullable'/", $src);

        foreach (self::PETS_PARTIALS as $role => $path) {
            $tag = $this->controlTag($this->markup($path), 'pets');

            $this->assertStringNotContainsString('required', $tag,
                "The {$role} Pets select carries no required attribute, and none was added: {$tag}");
        }
    }

    /**
     * The one flow that DOES require Pets still does.
     *
     * Tenant Create Offer Listing is a different component and a different form; nothing here
     * touches it, and a change that reached it would be a silent loss of validation.
     */
    public function test_the_tenant_offer_listing_flow_still_requires_pets(): void
    {
        $this->assertMatchesRegularExpression(
            "/\\\$rules\['pets'\]\s*=\s*'required'/",
            $this->source(self::TENANT_OFFER_LISTING),
            'Tenant Create Offer Listing must keep its required Pets rule — different flow, untouched.'
        );
    }

    /** The removal was scoped to the buyer branch; the neighbouring buyer checks still stand. */
    public function test_the_buyer_branch_keeps_its_other_property_type_checks(): void
    {
        $markup = $this->markup(self::TENANT_SHELL_VIEW);

        foreach (['bedrooms', 'bathrooms', 'real_estate_purchase'] as $key) {
            $this->assertStringContainsString(
                "key: '" . $key . "'",
                $markup,
                "{$key} is still required for the buyer and must still block — only Pets was removed."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE CLIENT-SIDE BLOCKER IS GONE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Representation Priorities no longer joins the invalid-items list.
     *
     * `buyerGetInvalidItems()` feeds a submit listener that calls `e.preventDefault()` when the list
     * is non-empty, so an entry here is a hard block regardless of the server rules.
     */
    public function test_representation_priorities_is_not_pushed_into_the_invalid_items_list(): void
    {
        $src = $this->source(self::HIRE_BUYER_VIEW);

        $this->assertStringNotContainsString(
            "key: 'compat_representation_priorities'",
            $src,
            'Representation Priorities must not be pushed into buyerGetInvalidItems() any more.'
        );
    }

    /** The FIELD is still there — optional means optional, not removed. */
    public function test_the_representation_priorities_control_is_still_rendered(): void
    {
        $src = $this->source(self::HIRE_BUYER_VIEW);

        $this->assertStringContainsString('#compat_representation_priorities', $src,
            'The multi-select must still render and initialise.');
        $this->assertStringContainsString(
            'compatibility_preferences.buyer_specific.representation_priorities',
            $src,
            'It must still round-trip to the same property so a supplied value is persisted and matched.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3b · THE CLIENT-SIDE LAYER THAT SURVIVED THE SERVER-SIDE FIX
    // ═════════════════════════════════════════════════════════════════════════
    //
    // Browser verification after the `nullable` commit showed the submit STILL blocked, and the
    // banner still named all six fields. Nothing about the server rules was wrong — the block
    // never reached them.
    //
    // `getAllRequiredFields()` collects `[required]` controls from the active tabs.
    // `buyerGetInvalidItems()` walks that list and reports every empty one.
    // The form's submit listener calls `e.preventDefault()` whenever that list is non-empty.
    //
    // So an HTML `required` attribute is a submit blocker in its own right, independent of PHP and
    // independent of the explicit `items.push()` that was already removed for Representation
    // Priorities. Removing the attributes is the fix; the exemption set asserted below is the
    // backstop, because all three consumers of `getAllRequiredFields()` — the submit blocker, the
    // save-button enable, and `validateAllTabsStrictly()` — would be restored together by a single
    // `required` reintroduced by a Select2 re-init or a morphdom patch.

    /** Not one of the five buyer compatibility selects carries `required` any more. */
    public function test_no_buyer_compatibility_control_is_marked_required_in_html(): void
    {
        $markup = $this->markup(self::BUYER_COMPAT_PARTIAL);

        $this->assertStringNotContainsString(
            'required',
            $markup,
            'No control in the buyer compatibility tab may carry the HTML required attribute — '
            . 'it blocks submit client-side regardless of the server rules.'
        );
    }

    /**
     * Current Representation Status is not required client-side either.
     *
     * It is the one banner entry that is NOT a compatibility preference, and it was the one field
     * with no server rule at all — `working_with_agent` is written straight to meta. The `required`
     * attribute was therefore the whole of its enforcement, which is exactly why relaxing PHP could
     * never have unblocked it.
     */
    public function test_the_buyer_representation_status_select_is_not_marked_required(): void
    {
        $tag = $this->controlTag($this->markup(self::BUYER_LISTING_DETAILS), 'working_with_agent');

        $this->assertStringNotContainsString('required', $tag,
            'working_with_agent must not block the buyer submit: ' . $tag);
        $this->assertStringContainsString('wire:model="working_with_agent"', $tag,
            'It must still bind — the value is still saved when the buyer picks one.');
    }

    /**
     * The removal was SURGICAL, not a sweep of the tab.
     *
     * Listing Title sits in the same partial and is genuinely required. If a future edit widened the
     * change to "drop required from listing-details", this fails.
     */
    public function test_the_buyer_listing_details_tab_keeps_its_genuinely_required_fields(): void
    {
        $tag = $this->controlTag($this->markup(self::BUYER_LISTING_DETAILS), 'listing_title');

        $this->assertStringContainsString('required', $tag,
            'Listing Title is a listing fact and must still be required.');
    }

    /**
     * The submit blocker exempts all six reported fields by key.
     *
     * ASSERTED AGAINST THE COMPILED VIEW, not its source. The first version of this exemption was
     * written as `@json($user_type === 'buyer' ? [ ...six keys... ] : [])`; Blade's `@json` argument
     * parser stops at the first `)` and cannot span a multi-line array literal, so it compiled to a
     * SYNTAX ERROR that had silently swallowed the last three keys. The source read correctly. Only
     * the compiled output showed it, so that is what is checked here.
     */
    public function test_the_submit_blocker_exempts_every_field_named_in_the_banner(): void
    {
        $compiled = $this->compiledView(self::HIRE_BUYER_VIEW);

        $this->assertStringContainsString('const BUYER_NON_BLOCKING_KEYS', $compiled,
            'The buyer submit path must declare its non-blocking keys explicitly.');

        foreach (self::NON_BLOCKING_KEYS as $key) {
            $this->assertStringContainsString(
                "'" . $key . "'",
                $compiled,
                "{$key} must survive compilation into the buyer exemption list."
            );
        }

        // The list must reach the browser through one variable. Inlining the literal into @json is
        // what truncated it, and a truncation leaves the source looking complete.
        $this->assertStringContainsString(
            'new Set(<?php echo json_encode($buyerNonBlockingKeys',
            $compiled,
            'The exemption list must be built in a @php block and passed to @json as a variable.'
        );
    }

    /**
     * The view still compiles to valid PHP.
     *
     * The truncated `@json` above produced an unclosed `[`, which would have been a fatal error on
     * every render of the Hire Buyer form — not a subtle behaviour change. `token_get_all()` with
     * `TOKEN_PARSE` raises `ParseError` on exactly that.
     */
    public function test_the_hire_buyer_view_compiles_to_valid_php(): void
    {
        foreach ([self::HIRE_BUYER_VIEW, self::BUYER_COMPAT_PARTIAL, self::BUYER_LISTING_DETAILS] as $view) {
            try {
                token_get_all($this->compiledView($view), TOKEN_PARSE);
            } catch (\ParseError $e) {
                $this->fail("{$view} does not compile to valid PHP: {$e->getMessage()}");
            }
        }

        $this->addToAssertionCount(1);
    }

    /** The exemption is actually applied — `getAllRequiredFields()` filters through it. */
    public function test_the_exemption_is_applied_when_required_fields_are_collected(): void
    {
        $src = $this->source(self::HIRE_BUYER_VIEW);

        $this->assertMatchesRegularExpression(
            '/function getAllRequiredFields\(\).*?querySelectorAll\(\'\[required\]\'\).*?'
            . 'if \(isBuyerNonBlockingField\(field\)\) return;/s',
            $src,
            'getAllRequiredFields() must skip the exempt fields — it is the single choke point for '
            . 'buyerGetInvalidItems(), updateSaveButton() and validateAllTabsStrictly().'
        );

        // And the resolver keys fields the same way the blocker's label lookup does, so an id-keyed
        // wire:ignore select and a wire:model-keyed select are both matched.
        $this->assertMatchesRegularExpression(
            "/function isBuyerNonBlockingField\(field\).*?getAttribute\('wire:model'\).*?field\.id/s",
            $src,
            'isBuyerNonBlockingField() must resolve keys the same way resolveBuyerFieldKey() does.'
        );
    }

    /**
     * BUYER ONLY — enforced by the view, not by convention.
     *
     * `hire-buyer-agent.blade.php` also renders the seller / tenant / landlord compatibility
     * partials, which reuse the SAME `compat_*` element ids. An ungated exemption would silently
     * unblock another role's required fields if this view were ever rendered for one. Gating the
     * list on `$user_type === 'buyer'` makes that impossible rather than unlikely.
     */
    public function test_the_client_side_exemption_is_scoped_to_buyers(): void
    {
        $src = $this->source(self::HIRE_BUYER_VIEW);

        $this->assertMatchesRegularExpression(
            "/\\\$buyerNonBlockingKeys\s*=\s*\\\$user_type === 'buyer' \? \[/",
            $src,
            'The exemption list must be gated on user_type — this view also renders the seller, '
            . 'tenant and landlord compatibility partials, which reuse the same compat_* ids.'
        );

        $this->assertMatchesRegularExpression(
            "/\\\$buyerNonBlockingKeys\s*=\s*\\\$user_type === 'buyer' \?\s*\[.*?\]\s*:\s*\[\];/s",
            $src,
            'The non-buyer branch of the exemption must be an empty list.'
        );
    }

    /** No other role's shell gained an exemption. */
    public function test_no_other_role_shell_carries_the_exemption(): void
    {
        foreach ([
            'resources/views/livewire/tenant-agent-auction.blade.php',
            'resources/views/livewire/tenant-agent-auction-edit.blade.php',
            'resources/views/livewire/hire-seller-agent/hire-seller-agent.blade.php',
            'resources/views/livewire/hire-landlord-agent/hire-landlord-agent.blade.php',
        ] as $view) {
            $this->assertStringNotContainsString('BUYER_NON_BLOCKING_KEYS', $this->source($view),
                "{$view} must not carry the buyer exemption — this change was buyer-only.");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3c · THE LABELS AGREE WITH THE BEHAVIOUR
    // ═════════════════════════════════════════════════════════════════════════
    //
    // The Listing Details tab states "Fields marked with * are required." That makes the asterisk a
    // PROMISE, not decoration: leaving one on a field that no longer blocks tells the buyer to fill
    // in something the form does not want, which is the same confusion the block itself caused —
    // just quieter, because nothing stops them to explain it.

    /** Not one label in the buyer compatibility tab claims to be required. */
    public function test_no_buyer_compatibility_label_carries_a_required_asterisk(): void
    {
        $markup = $this->markup(self::BUYER_COMPAT_PARTIAL);

        $this->assertStringNotContainsString(
            '<span class="text-danger">*</span>',
            $markup,
            'Every field in the buyer compatibility tab is optional, so no label may carry an asterisk.'
        );
    }

    /** Current Representation Status drops its asterisk with the rest. */
    public function test_the_buyer_representation_status_label_carries_no_asterisk(): void
    {
        $markup = $this->markup(self::BUYER_LISTING_DETAILS);

        $this->assertMatchesRegularExpression(
            '/Current Representation Agreement Status with Broker:\s*\n?\s*<span class="ms-2"/',
            $markup,
            'The representation status label must run straight into its tooltip with no asterisk between.'
        );
    }

    /**
     * And the asterisk still means what the legend says for everything else in that tab.
     *
     * Five controls in Listing Details are genuinely required; each keeps its marker. A sweep that
     * stripped the asterisks tab-wide would pass the test above and fail this one.
     */
    public function test_the_buyer_listing_details_tab_keeps_the_asterisks_it_still_earns(): void
    {
        $markup = $this->markup(self::BUYER_LISTING_DETAILS);

        $this->assertStringContainsString(
            'Fields marked with <span class="text-danger">*</span> are required',
            $markup,
            'The legend that gives the asterisk its meaning must stay.'
        );

        foreach ([
            'Listing Title: ',
            'Desired Agent Hire Date:',
            'Listing Date:',
            'Expiration Date:',
            'Meeting Preference:',
        ] as $label) {
            $this->assertStringContainsString(
                $label . '<span class="text-danger">*</span>',
                $markup,
                "\"{$label}\" is genuinely required and must keep its asterisk."
            );
        }
    }

    /**
     * Seller, landlord and tenant labels are untouched — their fields are still required.
     *
     * Their label reads "Current Representation Status with Broker"; the buyer's reads "Current
     * Representation **Agreement** Status with Broker". The wording has always differed, so each is
     * matched on its own text rather than on a shared pattern that would quietly match neither.
     */
    public function test_other_roles_keep_their_representation_status_asterisk(): void
    {
        foreach (self::OTHER_ROLE_LISTING_DETAILS as $role => $path) {
            $this->assertStringContainsString(
                'Current Representation Status with Broker:<span class="text-danger">*</span>',
                $this->markup($path),
                "The {$role} label must keep its asterisk — that field is still required."
            );
        }
    }

    /** The other roles' compatibility tabs keep theirs too. */
    public function test_the_tenant_compatibility_labels_keep_their_asterisks(): void
    {
        $this->assertStringContainsString(
            '<span class="text-danger">*</span>',
            $this->markup(
                'resources/views/livewire/tenant-agent-auction-tabs/commission-based/representation-compatibility.blade.php'
            ),
            'The tenant compatibility tab must keep its required markers.'
        );
    }

    /** Each select still exists, still carries its binding, and still offers its options. */
    public function test_every_buyer_compatibility_control_is_still_rendered(): void
    {
        $src = $this->source(self::BUYER_COMPAT_PARTIAL);

        foreach ([
            'compat_primary_transaction_goal',
            'compat_representation_priorities',
            'compat_communication_style',
            'compat_negotiation_style',
            'compat_preferred_agent_working_style',
        ] as $id) {
            $this->assertStringContainsString(
                'id="' . $id . '"',
                $src,
                "{$id} must still render — optional means optional, not removed."
            );
        }

        // The bindings the values are saved and matched through.
        foreach (['primary_transaction_goal', 'communication_style', 'negotiation_style',
                  'preferred_agent_working_style'] as $field) {
            $this->assertStringContainsString('data-compat-field="' . $field . '"', $src);
        }

        // Representation Priorities is a Select2 multi-select with no data-compat-field; it syncs
        // through an explicit change handler in the shell instead.
        $this->assertStringContainsString(
            "debouncedSet('compatibility_preferences.buyer_specific.representation_priorities'",
            $this->source(self::HIRE_BUYER_VIEW),
            'Representation Priorities must still write through to its Livewire property.'
        );
    }

    /**
     * The partial is BUYER-ONLY, which is what makes editing it a buyer-scoped change.
     *
     * Every include of it is either a buyer-specific view or sits behind `$user_type === 'buyer'`.
     * Tenant, seller and landlord each render their own compatibility partial, untouched.
     */
    public function test_the_buyer_compatibility_partial_is_only_rendered_for_buyers(): void
    {
        foreach ([
            'resources/views/livewire/tenant-agent-auction.blade.php',
            'resources/views/livewire/tenant-agent-auction-edit.blade.php',
        ] as $view) {
            $src = $this->source($view);

            $this->assertMatchesRegularExpression(
                "/@elseif\s*\(\\\$user_type === 'buyer'\)\s*\n\s*@include\('livewire\.hire-buyer-agent\."
                . "buyer-agent-auction-tabs\.commission-based\.representation-compatibility'\)/",
                $src,
                "{$view} must include the buyer compatibility partial only on the buyer branch."
            );
        }
    }

    /** The other roles' compatibility partials keep their own required attributes. */
    public function test_the_tenant_compatibility_partial_still_marks_its_fields_required(): void
    {
        $tenant = $this->source(
            'resources/views/livewire/tenant-agent-auction-tabs/commission-based/representation-compatibility.blade.php'
        );

        $this->assertStringContainsString('required', $tenant,
            'The tenant compatibility tab must keep its client-side required markers.');
    }

    /** And every other role keeps a REQUIRED Current Representation Status select. */
    public function test_other_roles_keep_a_required_representation_status_select(): void
    {
        foreach (self::OTHER_ROLE_LISTING_DETAILS as $role => $path) {
            $tag = $this->controlTag($this->markup($path), 'working_with_agent');

            $this->assertStringContainsString('required', $tag,
                "The {$role} listing-details tab must keep its required representation status: {$tag}");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · SELLER / LANDLORD / TENANT ARE UNCHANGED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The other three roles keep every `required` compatibility rule.
     *
     * `TenantAgentAuction` builds all four role rule-sets in one method, so a careless edit here
     * silently drops validation for roles nobody was asked to change.
     */
    public function test_seller_landlord_and_tenant_compatibility_rules_remain_required(): void
    {
        $src = $this->source(self::TENANT_AGENT_AUCTION);

        foreach (['tenant_specific', 'landlord_specific', 'seller_specific'] as $role) {
            foreach (['communication_style', 'negotiation_style', 'preferred_agent_working_style'] as $field) {
                $this->assertMatchesRegularExpression(
                    '/compatibility_preferences\.' . $role . '\.' . $field . "'\]\s*=\s*'required\|string'/",
                    $src,
                    "{$role}.{$field} must still be required — this change was buyer-only."
                );
            }

            $this->assertMatchesRegularExpression(
                '/compatibility_preferences\.' . $role . "\.representation_priorities'\]\s*=\s*'required\|array\|min:1'/",
                $src,
                "{$role}.representation_priorities must still be required."
            );
        }
    }

    /** The dedicated Seller and Landlord components are untouched too. */
    public function test_the_seller_and_landlord_components_are_untouched(): void
    {
        $seller = $this->source('app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php');
        $this->assertStringContainsString(
            "\$rules['compatibility_preferences.seller_specific.communication_style']           = 'required|string';",
            $seller
        );

        $landlord = $this->source('app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php');
        $this->assertStringContainsString(
            "'compatibility_preferences.landlord_specific.communication_style'          => 'required|string',",
            $landlord
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · THE MIRROR ABOVE MATCHES WHAT SHIPS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Both Hire Buyer components carry the relaxed rules, and this file's mirror agrees with them.
     *
     * Hire Buyer is served by two components — `TenantAgentAuction` (user_type `buyer`) and
     * `HireBuyerAgent\BuyerAgentAuction` — each with its own copy of these rules. If only one had
     * moved, the flow would validate differently depending on which rendered.
     */
    public function test_the_shipped_buyer_rules_match_the_rules_asserted_here(): void
    {
        $catchAll = $this->source(self::TENANT_AGENT_AUCTION);
        $hire     = $this->source(self::HIRE_BUYER_AGENT);

        foreach (self::COMPAT_FIELDS as $field) {
            $expected = $field === 'representation_priorities' ? 'nullable\|array' : 'nullable\|string';
            $pattern  = '/compatibility_preferences\.buyer_specific\.' . $field . "'\]\s*=\s*'" . $expected . "'/";

            $this->assertMatchesRegularExpression($pattern, $catchAll,
                "TenantAgentAuction must declare buyer {$field} as optional.");
            $this->assertMatchesRegularExpression($pattern, $hire,
                "HireBuyerAgent\\BuyerAgentAuction must declare buyer {$field} as optional.");
        }

        // No `.required` message may survive for a rule that no longer raises one.
        $this->assertStringNotContainsString(
            'compatibility_preferences.buyer_specific.communication_style.required',
            $hire,
            'Unreachable validation messages must not outlive their rules.'
        );
    }
}
