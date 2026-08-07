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

    private const TENANT_AGENT_AUCTION = 'app/Http/Livewire/TenantAgentAuction.php';
    private const HIRE_BUYER_AGENT     = 'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php';
    private const HIRE_BUYER_VIEW      = 'resources/views/livewire/hire-buyer-agent/hire-buyer-agent.blade.php';

    private function source(string $path): string
    {
        $full = base_path($path);
        $this->assertFileExists($full, "Expected file missing: {$path}");

        return (string) file_get_contents($full);
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
