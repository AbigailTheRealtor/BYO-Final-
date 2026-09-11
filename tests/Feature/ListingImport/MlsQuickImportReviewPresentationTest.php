<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Support\OfferListing\QuickImportTermsReview;
use Tests\TestCase;

/**
 * The Quick Import review step publishes ANSWERS, not stored keys.
 *
 * THE DEFECT
 * ----------
 * The review list looped every canonical terms field and printed any non-empty
 * value. The `$` / `%` selectors that sit beside an amount are never empty — they
 * default to a symbol — so a seller reviewing a CASH sale was shown:
 *
 *     Additional deposit type    $
 *     Assumable fee type         $
 *     Down payment type          %
 *     Gap payment type           $
 *     Initial deposit type       $
 *     Seller financing type      $
 *
 * six rows about assumable mortgages and seller financing they had not selected,
 * each answering with a currency symbol. The asking price was published as
 * "Maximum budget 385000" — the storage key and an unformatted number.
 *
 * WHAT THESE TESTS PIN
 * --------------------
 * Not "the six rows are gone" — that is the symptom. They pin the rule: a `$`/`%`
 * control is the UNIT of the amount beside it and never an answer in its own
 * right, which is exactly how the finished listing page already treats it via
 * {@see \App\Support\OfferListing\ConditionalTerms::amount()}. The review screen
 * and the published page must not be able to disagree.
 *
 * The modifier list is additionally bound to the canonical MARKUP below, so a
 * control that changes shape fails the build instead of quietly reappearing as a
 * bare `$`.
 */
class MlsQuickImportReviewPresentationTest extends TestCase
{
    private const SELLER_TERMS = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';

    /** @param array<string,mixed> $state */
    private function sellerRows(array $state): array
    {
        return QuickImportTermsReview::rows(
            'seller',
            SellerMlsQuickImport::sellerSaleTermsFields(),
            fn (string $f) => $state[$f] ?? '',
        );
    }

    /** @param array<string,mixed> $state */
    private function landlordRows(array $state): array
    {
        return QuickImportTermsReview::rows(
            'landlord',
            LandlordMlsQuickImport::landlordLeasingTermsFields(),
            fn (string $f) => $state[$f] ?? '',
        );
    }

    /** The state a seller reaches review with on a plain cash sale. */
    private function cashSaleState(): array
    {
        return [
            'maximum_budget'          => '385000',
            'offered_financing'       => ['Cash'],
            // Every $/% control at its default, which is what produced the bug.
            'additional_deposit_type' => '$',
            'assignment_fee_type'     => '$',
            'assumable_fee_type'      => '$',
            'down_payment_type'       => '%',
            'gap_payment_type'        => '$',
            'initial_deposit_type'    => '$',
            'seller_financing_type'   => '$',
        ];
    }

    // ─── A. The reported rows ────────────────────────────────────────────────

    /** @test */
    public function a_currency_toggle_never_becomes_a_row_of_its_own(): void
    {
        $rows = $this->sellerRows($this->cashSaleState());

        foreach ([
            'Additional deposit type',
            'Assignment fee type',
            'Assumable fee type',
            'Down payment type',
            'Gap payment type',
            'Initial deposit type',
            'Seller financing type',
        ] as $label) {
            $this->assertArrayNotHasKey(
                $label,
                $rows,
                "'{$label}' is the unit of an amount, not an answer, and must never be its own row"
            );
        }
    }

    /**
     * @test
     *
     * The general rule behind the symptom: nothing in the review may publish a
     * bare currency symbol as its value, whatever the field is called.
     */
    public function no_row_publishes_an_orphan_currency_symbol(): void
    {
        foreach ([$this->sellerRows($this->cashSaleState()), $this->landlordRows(['desired_rental_amount' => '4321', 'pet_fee_type' => '$'])] as $rows) {
            foreach ($rows as $label => $value) {
                $this->assertNotSame('$', trim($value), "{$label} published a bare dollar sign");
                $this->assertNotSame('%', trim($value), "{$label} published a bare percent sign");
            }
        }
    }

    /** @test */
    public function an_inactive_branch_contributes_nothing_to_the_review(): void
    {
        $rows = $this->sellerRows($this->cashSaleState());

        // A cash sale states its price and its financing choice, and nothing else.
        $this->assertSame(['Desired Sale Price', 'Offered financing'], array_keys($rows));
    }

    // ─── B. Human labels and formatting ─────────────────────────────────────

    /** @test */
    public function the_asking_price_uses_the_label_the_seller_saw_and_is_formatted(): void
    {
        $rows = $this->sellerRows($this->cashSaleState());

        $this->assertSame('$385,000', $rows['Desired Sale Price'] ?? null);
        $this->assertArrayNotHasKey('Maximum budget', $rows, 'the storage key must not reach the screen');
    }

    /** @test */
    public function the_asking_rent_uses_the_label_the_landlord_saw_and_is_formatted(): void
    {
        $rows = $this->landlordRows(['desired_rental_amount' => '4321']);

        $this->assertSame('$4,321', $rows['Desired Rental Amount'] ?? null);
    }

    // ─── C. Active branches DO publish, with their unit ─────────────────────

    /**
     * @test
     *
     * The other half of the rule. Suppressing the toggle must not suppress the
     * amount — it must attach to it. `%` is the case
     * ConditionalTerms::amount() exists for: a 3% deposit once published as $3.
     */
    public function an_active_branch_publishes_its_amount_in_the_unit_that_was_chosen(): void
    {
        $rows = $this->sellerRows([
            'maximum_budget'       => '385000',
            'offered_financing'    => ['Assumable'],
            'assumable_fee_amount' => '2500',
            'assumable_fee_type'   => '$',
            'down_payment_amount'  => '3',
            'down_payment_type'    => '%',
            'assumable_loan_type'  => 'FHA',
        ]);

        $this->assertSame('$2,500', $rows['Assumption Fee'] ?? null);
        $this->assertSame('3%', $rows['Down Payment'] ?? null, 'a percentage must not be published as dollars');

        // A `*_type` that is a genuine answer keeps its row.
        $this->assertSame('FHA', $rows['Assumable loan type'] ?? null);
    }

    /** @test */
    public function a_multi_select_answer_is_flattened_for_display(): void
    {
        $rows = $this->landlordRows([
            'desired_rental_amount' => '4321',
            'tenant_pays'           => ['Electric', 'Water'],
        ]);

        $this->assertSame('Electric, Water', $rows['Tenant pays'] ?? null);
    }

    // ─── D. The modifier list is bound to the markup ────────────────────────

    /**
     * @test
     *
     * The classification is not a judgement call and must not become one. A field
     * is a MODIFIER exactly when its control offers `$` and `%` and nothing else.
     * This reads the canonical partial and asserts the declared list matches what
     * the markup actually renders, so the two cannot drift.
     */
    public function every_declared_modifier_is_a_currency_toggle_in_the_markup(): void
    {
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(base_path(self::SELLER_TERMS))) ?? '';

        foreach (array_keys(QuickImportTermsReview::modifiersFor('seller')) as $field) {
            preg_match(
                '/<select\b[^>]*wire:model[^"]*="' . preg_quote($field, '/') . '"[^>]*>(.*?)<\/select>/s',
                $markup,
                $m
            );

            $this->assertNotEmpty($m, "{$field} is declared a currency toggle but renders no select");

            preg_match_all('/<option[^>]*value="([^"]*)"/', $m[1], $opts);
            $values = array_values(array_filter($opts[1], fn ($v) => $v !== ''));

            sort($values);

            // Two spellings exist in the markup and both mean the same thing:
            // six controls store the symbol, `gap_payment_type` stores the word.
            // QuickImportTermsReview::normaliseUnit() reconciles them before
            // ConditionalTerms sees the token.
            $this->assertContains(
                $values,
                [['$', '%'], ['flat', 'percent']],
                "{$field} is treated as a currency-unit selector, but its options are: "
                . implode(', ', $values) . ' — if this control has become a real question, '
                . 'remove it from the modifier list so its answer is published again'
            );
        }
    }

    /**
     * @test
     *
     * The converse: a `*_type` field that is a REAL answer must not be silently
     * swallowed. These three offer loan types, amortisation types and free text —
     * none is a currency unit — and all keep their rows.
     */
    public function a_type_field_that_is_a_real_answer_is_not_suppressed(): void
    {
        $modifiers = QuickImportTermsReview::modifiersFor('seller');

        foreach (['assumable_loan_type', 'seller_amortization_type', 'cryptocurrency_type'] as $answer) {
            $this->assertArrayNotHasKey(
                $answer,
                $modifiers,
                "{$answer} is a real question and must keep its row on the review screen"
            );
        }

        $rows = $this->sellerRows([
            'seller_amortization_type' => 'Fully Amortizing',
            'cryptocurrency_type'      => 'Bitcoin',
        ]);

        $this->assertSame('Fully Amortizing', $rows['Seller amortization type'] ?? null);
        $this->assertSame('Bitcoin', $rows['Cryptocurrency type'] ?? null);
    }

    /**
     * @test
     *
     * Seller and Landlord reach review through the same presenter, so their term
     * semantics cannot diverge — the defect existed on both sides because both
     * had their own copy of the same loop.
     */
    public function both_roles_share_one_review_presenter(): void
    {
        foreach ([SellerMlsQuickImport::class, LandlordMlsQuickImport::class] as $component) {
            $source = file_get_contents((new \ReflectionClass($component))->getFileName());

            $this->assertStringContainsString(
                'QuickImportTermsReview::rows(',
                $source,
                $component . ' must build its review through the shared presenter'
            );

            $this->assertStringNotContainsString(
                'private function humaniseTermField',
                $source,
                $component . ' must not keep a private label/format routine'
            );
        }
    }

    /**
     * @test
     *
     * The word-spelled unit must reach ConditionalTerms as a symbol, or a
     * percentage falls to the dollar branch. `gap_payment_type` stores
     * `flat`/`percent`, and a 3% gap payment publishing as "$3" is the very
     * defect ConditionalTerms::amount() was written to prevent — reached through
     * a vocabulary it does not speak.
     */
    public function a_word_spelled_unit_is_normalised_before_formatting(): void
    {
        $percent = $this->sellerRows([
            'offered_financing'   => ['Assumable'],
            'gap_payment_amount'  => '3',
            'gap_payment_type'    => 'percent',
        ]);

        $this->assertSame('3%', $percent['Gap Payment'] ?? null);

        $flat = $this->sellerRows([
            'offered_financing'   => ['Assumable'],
            'gap_payment_amount'  => '3000',
            'gap_payment_type'    => 'flat',
        ]);

        $this->assertSame('$3,000', $flat['Gap Payment'] ?? null);
    }
}
