<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Support\OfferListing\ConditionalTerms;
use App\Support\OfferListing\QuickImportTermsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Quick Import review step publishes ANSWERS, not stored keys.
 *
 * THE DEFECTS
 * -----------
 *   1. `$` / `%` selectors are never empty, so a seller reviewing a CASH sale
 *      was shown "Assumable fee type $", "Down payment type %", "Seller financing
 *      type $" and four more — rows about branches they never chose.
 *   2. A follow-up outlived its parent: choose Assumable, type a fee, deselect
 *      Assumable, and the review still printed the fee. The finished listing
 *      does not.
 *   3. The asking price read "Maximum budget 385000".
 *
 * WHAT THESE TESTS PIN
 * --------------------
 * The rules, not the symptoms: a selector is the UNIT of the amount beside it;
 * a follow-up is published only while its parent opens it, at any depth; money
 * and percentages read as money and percentages; labels are the ones the form
 * showed. And every list those rules depend on — the units, the money and
 * percent fields, and the parent/child map in ConditionalTerms — is re-derived
 * here from the canonical markup, so the review cannot drift from the tab.
 */
class MlsQuickImportReviewPresentationTest extends TestCase
{
    use RefreshDatabase;

    private const SELLER_TERMS       = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';
    private const LEASE_TERMS        = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php';
    private const LANDLORD_BEHAVIOUR = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/_lease-terms-behaviour.blade.php';
    private const SELLER_VIEW        = 'resources/views/offer-listing/seller/view.blade.php';

    private const PARTIALS = ['seller' => self::SELLER_TERMS, 'landlord' => self::LEASE_TERMS];

    /**
     * Reveals the markup states through a derived flag or client script rather
     * than a Blade condition naming the parent. Each is backed by the landlord
     * behaviour partial, asserted in the_derived_reveals_are_driven_by_their_parent.
     */
    private const DERIVED = [
        '$is_update_lease_term_option_visible' => ['desired_lease_length', 'Other'],
        '$is_other_owner_pays_visible'         => ['owner_pays', 'Other'],
        'hidden#otherLeaseContainer'           => ['terms_of_lease', 'Other'],
    ];

    /** Selects with no wire:model — bound by the behaviour partials — and the field each carries. */
    private const JS_BOUND = [
        '#sale_provision'     => 'sale_provision',
        '#offered_financing'  => 'offered_financing',
        '#exchange_item'      => 'exchange_item',
        '#owner_pays'         => 'owner_pays',
        '#terms_of_lease'     => 'terms_of_lease',
        '.lease_term_options' => 'desired_lease_length',
    ];

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return list<string> */
    private static function fields(string $role): array
    {
        return $role === 'seller'
            ? SellerMlsQuickImport::sellerSaleTermsFields()
            : LandlordMlsQuickImport::landlordLeasingTermsFields();
    }

    /** @param array<string,mixed> $state */
    private function rows(string $role, array $state): array
    {
        return QuickImportTermsReview::rows($role, self::fields($role), fn (string $f) => $state[$f] ?? '');
    }

    /** @param array<string,mixed> $state */
    private function sellerRows(array $state): array
    {
        return $this->rows('seller', $state);
    }

    /** @param array<string,mixed> $state */
    private function landlordRows(array $state): array
    {
        return $this->rows('landlord', $state);
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
            'gap_payment_type'        => 'flat',
            'initial_deposit_type'    => '$',
            'seller_financing_type'   => '$',
        ];
    }

    /**
     * Answers that open `$field`'s first alternative, all the way up its chain.
     *
     * @return array<string,mixed>
     */
    private static function openState(string $role, string $field, array $state = [], array $seen = []): array
    {
        $map = ConditionalTerms::followUps($role);

        if (! isset($map[$field]) || isset($seen[$field])) {
            return $state;
        }

        $seen[$field] = true;

        foreach ($map[$field][0] as [$parent, $op, $answers]) {
            $state[$parent] = match ($op) {
                'is', 'accepts' => $answers[0],
                'not'           => 'anything-but-' . $answers[0],
                'other'         => 'Other',
            };
            $state = self::openState($role, $parent, $state, $seen);
        }

        return $state;
    }

    /**
     * Answers that close every alternative of `$field` at its own level: an empty
     * parent closes an 'is' / 'other' / 'accepts' clause, and a 'not' clause is
     * closed by naming the excluded answer.
     *
     * @return array<string,mixed>
     */
    private static function closedState(string $role, string $field): array
    {
        $state = [];

        foreach (ConditionalTerms::followUps($role)[$field] as $clauses) {
            foreach ($clauses as [$parent, $op, $answers]) {
                if ($op === 'not') {
                    $state[$parent] = $answers[0];
                }
            }
        }

        return $state;
    }

    private static function valuesContain(array $rows, string $needle): bool
    {
        foreach ($rows as $value) {
            if (str_contains((string) $value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function markup(string $relative): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(base_path($relative))) ?? '';
    }

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

    // ─── A. Selectors are units, never rows ─────────────────────────────────

    /** @test */
    public function a_currency_toggle_never_becomes_a_row_of_its_own(): void
    {
        // Every financing branch OPEN, every toggle at a default: still no toggle rows.
        $state = $this->cashSaleState() + [
            'sale_provision'            => ['Assignment Contract'],
            'sale_provision_assignment' => 'Yes',
        ];
        $state['offered_financing'] = ['Cash', 'Assumable', 'Seller Financing'];

        $rows = $this->sellerRows($state);

        foreach (array_keys(QuickImportTermsReview::unitsFor('seller')) as $unit) {
            $this->assertArrayNotHasKey(QuickImportTermsReview::label($unit), $rows,
                "'{$unit}' is the unit of an amount, not an answer, and must never be its own row");
        }
    }

    /** @test Nothing in the review may publish a bare currency symbol, whatever the field is called. */
    public function no_row_publishes_an_orphan_currency_symbol(): void
    {
        $seller = $this->cashSaleState();
        $seller['offered_financing'] = ['Cash', 'Assumable', 'Seller Financing'];

        foreach ([
            $this->sellerRows($seller),
            $this->landlordRows(['desired_rental_amount' => '4321', 'pet_fee_type' => 'Monthly Pet Fee']),
        ] as $rows) {
            foreach ($rows as $label => $value) {
                $this->assertNotSame('$', trim($value), "{$label} published a bare dollar sign");
                $this->assertNotSame('%', trim($value), "{$label} published a bare percent sign");
            }
        }
    }

    /** @test A cash sale states its price and its financing choice, and nothing else. */
    public function an_inactive_branch_contributes_nothing_to_the_review(): void
    {
        $this->assertSame(['Desired Sale Price', 'Offered financing'], array_keys($this->sellerRows($this->cashSaleState())));
    }

    // ─── B. Human labels and formatting ─────────────────────────────────────

    /** @test */
    public function the_asking_price_uses_the_label_the_seller_saw_and_is_formatted(): void
    {
        $rows = $this->sellerRows($this->cashSaleState());

        $this->assertSame('$385,000', $rows['Desired Sale Price'] ?? null);
        $this->assertArrayNotHasKey('Maximum budget', $rows, 'the storage key must not reach the screen');
    }

    /** @test "Desired Lease Price" is the label the Leasing Terms tab and the listing page both use. */
    public function the_asking_rent_uses_the_label_the_landlord_saw_and_is_formatted(): void
    {
        $rows = $this->landlordRows(['desired_rental_amount' => '4321']);

        $this->assertSame('$4,321', $rows['Desired Lease Price'] ?? null);
        $this->assertArrayNotHasKey('Desired rental amount', $rows);
    }

    /** @test A field the tab prefixes with a fixed `$` reads as money. */
    public function a_fixed_money_field_is_formatted_as_money(): void
    {
        $rows = $this->sellerRows(['offered_financing' => ['Lease Option'], 'lease_option_price' => '250000']);
        $this->assertSame('$250,000', $rows['Lease option price'] ?? null);

        $rows = $this->landlordRows(['security_deposit_amount' => '2,500']);
        $this->assertSame('$2,500', $rows['Security Deposit'] ?? null);
    }

    /** @test A field the tab suffixes with a fixed `%` reads as a percentage, never as dollars. */
    public function a_fixed_percentage_field_is_formatted_as_a_percentage(): void
    {
        $rows = $this->sellerRows([
            'offered_financing' => ['Seller Financing', 'Cryptocurrency'],
            'interest_rate'     => '6.5',
            'crypto_percentage' => '40',
        ]);

        $this->assertSame('6.5%', $rows['Interest rate'] ?? null);
        $this->assertSame('40%', $rows['Crypto percentage'] ?? null);
    }

    // ─── C. Units format their amounts ──────────────────────────────────────

    /** @test */
    public function an_active_branch_publishes_its_amount_in_the_unit_that_was_chosen(): void
    {
        $rows = $this->sellerRows([
            'offered_financing'    => ['Assumable', 'Seller Financing'],
            'assumable_fee_amount' => '2500',
            'assumable_fee_type'   => '$',
            'down_payment_amount'  => '3',
            'down_payment_type'    => '%',
            'assumable_loan_type'  => 'FHA',
        ]);

        $this->assertSame('$2,500', $rows['Assumption Fee'] ?? null);
        $this->assertSame('3%', $rows['Down Payment'] ?? null, 'a percentage must not be published as dollars');
        $this->assertSame('FHA', $rows['Assumable loan type'] ?? null, 'a *_type that is a real answer keeps its row');
    }

    /**
     * @test
     *
     * `seller_financing_type` is not an orphan: it is the unit of the tab's
     * "Seller Financing" amount, `seller_down_payment_amount`.
     */
    public function the_seller_financing_selector_formats_the_seller_financing_amount(): void
    {
        $percent = $this->sellerRows(['offered_financing' => ['Seller Financing'], 'seller_financing_type' => '%', 'seller_down_payment_amount' => '80']);
        $this->assertSame('80%', $percent['Seller Financing Amount'] ?? null);

        $money = $this->sellerRows(['offered_financing' => ['Seller Financing'], 'seller_financing_type' => '$', 'seller_down_payment_amount' => '400000']);
        $this->assertSame('$400,000', $money['Seller Financing Amount'] ?? null);
    }

    /** @test `gap_payment_type` stores `flat` / `percent`, and still formats correctly. */
    public function a_word_spelled_unit_is_normalised_before_formatting(): void
    {
        $this->assertSame('3%', $this->sellerRows(['offered_financing' => ['Assumable'], 'gap_payment_amount' => '3', 'gap_payment_type' => 'percent'])['Gap Payment'] ?? null);
        $this->assertSame('$3,000', $this->sellerRows(['offered_financing' => ['Assumable'], 'gap_payment_amount' => '3000', 'gap_payment_type' => 'flat'])['Gap Payment'] ?? null);
    }

    /** @test The assignment fee exists only once a fee structure is chosen, and follows it. */
    public function the_assignment_fee_follows_its_structure(): void
    {
        $base = ['sale_provision' => ['Assignment Contract'], 'sale_provision_assignment' => 'Yes', 'assignment_fee_amount' => '3'];

        $this->assertSame('3%', $this->sellerRows($base + ['assignment_fee_type' => '%'])['Assignment Fee'] ?? null);
        $this->assertSame('$3', $this->sellerRows($base + ['assignment_fee_type' => '$'])['Assignment Fee'] ?? null);
        $this->assertArrayNotHasKey('Assignment Fee', $this->sellerRows($base + ['assignment_fee_type' => '']));
    }

    // ─── D. The parent decides ──────────────────────────────────────────────

    /** @test The reported case. */
    public function a_deselected_parent_hides_its_stale_children(): void
    {
        $stale = [
            'offered_financing'    => ['Cash'],
            'assumable_fee_amount' => '2500',
            'assumable_fee_type'   => '$',
            'assumable_loan_type'  => 'FHA',
        ];

        $rows = $this->sellerRows($stale);
        $this->assertArrayNotHasKey('Assumption Fee', $rows);
        $this->assertArrayNotHasKey('Assumable loan type', $rows);
        $this->assertArrayNotHasKey('Assumable fee type', $rows);

        $rows = $this->sellerRows(['offered_financing' => ['Assumable']] + $stale);
        $this->assertSame('$2,500', $rows['Assumption Fee'] ?? null);
        $this->assertSame('FHA', $rows['Assumable loan type'] ?? null);
    }

    /**
     * @test
     * @dataProvider everyFollowUp
     *
     * The rule applied to EVERY declared follow-up, both roles, both directions —
     * generated from the map so a branch added later is covered without anyone
     * remembering to add a case.
     */
    public function every_follow_up_is_hidden_while_closed_and_shown_once_open(string $role, string $field): void
    {
        $sentinel = 'SENTINEL-' . $field;

        $closed = $this->rows($role, self::closedState($role, $field) + [$field => $sentinel]);
        $this->assertFalse(self::valuesContain($closed, $sentinel),
            "{$role}.{$field} was published while its parent is closed");

        $open = $this->rows($role, [$field => $sentinel] + self::openState($role, $field));
        $this->assertTrue(self::valuesContain($open, $sentinel),
            "{$role}.{$field} was not published although its parent opens it");
    }

    public function everyFollowUp(): array
    {
        $cases = [];

        foreach (['seller', 'landlord'] as $role) {
            $units    = QuickImportTermsReview::unitsFor($role);
            $writeIns = ConditionalTerms::writeIns($role);

            foreach (array_keys(ConditionalTerms::followUps($role)) as $field) {
                // Units never have a row; write-ins are published inside their parent.
                if (isset($units[$field]) || isset($writeIns[$field])) {
                    continue;
                }
                $cases["{$role}.{$field}"] = [$role, $field];
            }
        }

        return $cases;
    }

    /**
     * @test
     * @dataProvider everyWriteIn
     *
     * "Other" text is published inside its parent's answer while "Other" is
     * chosen, never as a row of its own, and not at all once "Other" is dropped.
     */
    public function a_write_in_is_published_inside_its_parent_and_only_while_open(string $role, string $writeIn, string $parent): void
    {
        $sentinel = 'SENTINEL-' . $writeIn;

        $open = $this->rows($role, [$writeIn => $sentinel] + self::openState($role, $writeIn));
        $this->assertStringContainsString($sentinel, $open[QuickImportTermsReview::label($parent)] ?? '',
            "{$writeIn} must be published inside {$parent}'s answer");
        $this->assertArrayNotHasKey(QuickImportTermsReview::label($writeIn), $open);

        $closed = $this->rows($role, [$parent => 'Some other answer', $writeIn => $sentinel] + self::openState($role, $parent));
        $this->assertFalse(self::valuesContain($closed, $sentinel), "{$writeIn} outlived {$parent}'s \"Other\"");
    }

    public function everyWriteIn(): array
    {
        $cases = [];

        foreach (['seller', 'landlord'] as $role) {
            foreach (ConditionalTerms::writeIns($role) as $writeIn => $parent) {
                $cases["{$role}.{$writeIn}"] = [$role, $writeIn, $parent];
            }
        }

        return $cases;
    }

    /** @test A closed grandparent closes the grandchild, whatever its own parent says. */
    public function a_closed_branch_closes_everything_beneath_it(): void
    {
        $rows = $this->sellerRows([
            'offered_financing'         => ['Cash'],
            'prepayment_penalty'        => 'Yes',
            'prepayment_penalty_amount' => '5000',
        ]);

        $this->assertArrayNotHasKey('Prepayment penalty', $rows);
        $this->assertArrayNotHasKey('Prepayment penalty amount', $rows);

        $rows = $this->sellerRows([
            'offered_financing'         => ['Seller Financing'],
            'prepayment_penalty'        => 'Yes',
            'prepayment_penalty_amount' => '5000',
        ]);

        $this->assertSame('$5,000', $rows['Prepayment penalty amount'] ?? null);
    }

    /** @test Landlord: the property type is the parent of the whole commercial lease block. */
    public function a_residential_lease_does_not_publish_stale_commercial_terms(): void
    {
        $stale = [
            'desired_rental_amount' => '4000',
            'commercial_lease_type' => 'NNN',
            'tenant_pays'           => ['Electricity'],
            'rent_includes'         => ['Internet'],
        ];

        $residential = $this->landlordRows(['property_type' => 'Residential Property'] + $stale);
        $this->assertArrayNotHasKey('Commercial lease type', $residential);
        $this->assertArrayNotHasKey('Tenant pays', $residential);
        $this->assertSame('Internet', $residential['Rent includes'] ?? null);

        $commercial = $this->landlordRows(['property_type' => 'Commercial Property'] + $stale);
        $this->assertSame('NNN', $commercial['Commercial lease type'] ?? null);
        $this->assertSame('Electricity', $commercial['Tenant pays'] ?? null);
        $this->assertArrayNotHasKey('Rent includes', $commercial);
    }

    /**
     * @test
     *
     * `pet_fee_type` looks like a unit by name and is not one: it is an answer,
     * and the parent of the fee amount and the fee details.
     */
    public function pet_fee_type_is_an_answer_and_the_parent_of_the_fee(): void
    {
        $none = $this->landlordRows(['pet_fee_type' => 'No Pet Fee', 'pet_fee_amount' => '300', 'pet_fee_other' => 'stale']);
        $this->assertSame('No Pet Fee', $none['Pet fee type'] ?? null, 'the landlord\'s answer must be published');
        $this->assertArrayNotHasKey('Pet Fee', $none, 'No Pet Fee carries no amount');
        $this->assertArrayNotHasKey('Pet Fee Details', $none);

        $monthly = $this->landlordRows(['pet_fee_type' => 'Monthly Pet Fee', 'pet_fee_amount' => '300']);
        $this->assertSame('Monthly Pet Fee', $monthly['Pet fee type'] ?? null);
        $this->assertSame('$300', $monthly['Pet Fee'] ?? null);

        $other = $this->landlordRows(['pet_fee_type' => 'Other', 'pet_fee_other' => 'Refundable $200 plus $25/mo']);
        $this->assertSame('Refundable $200 plus $25/mo', $other['Pet Fee Details'] ?? null);
    }

    // ─── E. Every list is bound to the canonical markup ─────────────────────

    /**
     * @test
     *
     * A field is a UNIT exactly when its control offers `$` and `%` (or flat /
     * percent) and nothing else, and the amount it formats sits in the same form
     * group. Both directions, both roles: Leasing Terms has no such control.
     */
    public function the_declared_units_are_exactly_the_currency_toggles_in_the_markup(): void
    {
        foreach (self::PARTIALS as $role => $partial) {
            $markup = $this->markup($partial);

            preg_match_all('/<select\b[^>]*wire:model[a-z.]*="([a-z_]+)"[^>]*>(.*?)<\/select>/s', $markup, $selects, PREG_SET_ORDER);

            $toggles = [];

            foreach ($selects as [, $field, $body]) {
                preg_match_all('/<option[^>]*value="([^"]*)"/', $body, $o);
                $values = array_values(array_filter($o[1], fn ($v) => $v !== ''));
                sort($values);

                if ($values === ['$', '%'] || $values === ['flat', 'percent']) {
                    $toggles[] = $field;
                }
            }

            $this->assertEqualsCanonicalizing($toggles, array_keys(QuickImportTermsReview::unitsFor($role)),
                "{$role}: the declared units must be exactly the \$/% selects the tab renders");

            foreach (QuickImportTermsReview::unitsFor($role) as $unit => $amount) {
                $this->assertMatchesRegularExpression(
                    '/wire:model[a-z.]*="' . $amount . '"/',
                    $this->enclosingFormGroup($markup, 'wire:model="' . $unit . '"'),
                    "{$unit} must sit in the same form group as {$amount}, the amount it formats"
                );
            }
        }

        $this->assertSame([], QuickImportTermsReview::unitsFor('landlord'));
    }

    /** The `<div class="form-group…">` block that contains `$needle`. */
    private function enclosingFormGroup(string $markup, string $needle): string
    {
        $at = strpos($markup, $needle);
        $this->assertNotFalse($at, "{$needle} not found");

        $start = strrpos(substr($markup, 0, $at), '<div class="form-group');
        $this->assertNotFalse($start);

        $depth  = 0;
        $offset = $start;

        while (preg_match('/<div\b|<\/div>/', $markup, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $depth += $m[0][0] === '</div>' ? -1 : 1;
            $offset = $m[0][1] + strlen($m[0][0]);

            if ($depth === 0) {
                break;
            }
        }

        return substr($markup, $start, $offset - $start);
    }

    /**
     * @test
     *
     * The converse of the unit rule for Leasing Terms: the fee TYPE is a real
     * question whose options are fee arrangements, so it must keep its row.
     */
    public function pet_fee_type_is_a_real_question_not_a_unit(): void
    {
        preg_match('/<select\b[^>]*wire:model="pet_fee_type"[^>]*>(.*?)<\/select>/s', $this->markup(self::LEASE_TERMS), $m);
        $this->assertNotEmpty($m);

        preg_match_all('/<option[^>]*value="([^"]*)"/', $m[1], $o);
        $this->assertContains('Monthly Pet Fee', $o[1]);
        $this->assertNotContains('$', $o[1]);

        $this->assertArrayNotHasKey('pet_fee_type', QuickImportTermsReview::unitsFor('landlord'));
    }

    /**
     * @test
     *
     * MONEY / PERCENT are exactly the inputs the tab gives an unconditional `$`
     * prefix or `%` suffix — minus the unit-formatted amounts, whose symbol
     * follows their selector.
     */
    public function the_money_and_percent_lists_are_exactly_the_fixed_affixes_in_the_markup(): void
    {
        foreach (self::PARTIALS as $role => $partial) {
            $markup   = $this->markup($partial);
            $partners = array_values(QuickImportTermsReview::unitsFor($role));
            $money    = [];
            $percent  = [];

            preg_match_all('/<div class="(?:input-cover|input-group)[^"]*"[^>]*>(.*?)<\/div>/s', $markup, $groups);

            foreach ($groups[1] as $body) {
                if (! preg_match('/wire:model[a-z.]*="([a-z_]+)"/', $body, $f) || in_array($f[1], $partners, true)) {
                    continue;
                }

                $unconditional = preg_replace('/@if\b.*?@endif/s', '', $body);

                if (preg_match('/<span[^>]*>\s*\$\s*<\/span>/', $unconditional)) {
                    $money[] = $f[1];
                }
                if (preg_match('/<span[^>]*>\s*%\s*<\/span>/', $unconditional)) {
                    $percent[] = $f[1];
                }
            }

            $this->assertEqualsCanonicalizing(array_values(array_unique($money)), QuickImportTermsReview::moneyFor($role), "{$role} money");
            $this->assertEqualsCanonicalizing(array_values(array_unique($percent)), QuickImportTermsReview::percentFor($role), "{$role} percent");
        }
    }

    /**
     * @test
     * @dataProvider roles
     *
     * THE PARENT/CHILD MAP IS THE MARKUP'S CONDITIONAL STRUCTURE — both ways.
     *
     * Every bound field in the canonical partial is walked with the conditions of
     * the regions that enclose it (@if blocks, `display: {{ … }}` sections, Alpine
     * x-show, and the three derived reveals). Then:
     *
     *   1. a field the markup renders conditionally must be declared, and each
     *      enclosing condition must be explained by its declared parents (at any
     *      depth);
     *   2. each declared alternative must be backed by a rendered occurrence;
     *   3. each declared answer must be one the markup actually names.
     *
     * So a follow-up added to the tab without a line in ConditionalTerms, or a
     * line there the tab does not back, fails here, naming the field.
     */
    public function the_follow_up_map_is_the_markup_s_conditional_structure(string $role): void
    {
        $map    = ConditionalTerms::followUps($role);
        $fields = self::fields($role);

        $occurrences = array_values(array_filter(
            $this->occurrences(self::PARTIALS[$role]),
            fn (array $o) => in_array($o['field'], $fields, true) || isset($map[$o['field']])
        ));

        // The payment-assumptions card is a disclosure, not a question.
        foreach ($occurrences as &$o) {
            $o['conds'] = array_values(array_filter($o['conds'], fn (string $c) => ! str_contains($c, '$showPaymentAssumptions')));
        }
        unset($o);

        $problems = [];

        foreach ($occurrences as $o) {
            foreach ($o['conds'] as $c) {
                if (str_starts_with($c, 'hidden#') && ! isset(self::DERIVED[$c])) {
                    $problems[] = "{$o['field']}: sits in an undeclared script-revealed wrapper [{$c}]";
                }
            }

            if ($o['conds'] === []) {
                continue;
            }

            if (! isset($map[$o['field']])) {
                $problems[] = "{$o['field']}: conditional in the markup [" . implode(' AND ', $o['conds']) . '] but not declared in ConditionalTerms';
                continue;
            }

            $closure = $this->clauseClosure($map, $o['field']);

            foreach ($o['conds'] as $c) {
                if (! array_filter($closure, fn (array $k) => $this->conditionMatches($c, $k))) {
                    $problems[] = "{$o['field']}: markup condition [{$c}] is not explained by its declared parents";
                }
            }
        }

        foreach ($map as $field => $alternatives) {
            $mine = array_filter($occurrences, fn (array $o) => $o['field'] === $field);

            if ($mine === []) {
                $problems[] = "{$field}: declared in ConditionalTerms but not rendered by the partial";
                continue;
            }

            foreach ($alternatives as $i => $clauses) {
                $backed = false;

                foreach ($mine as $o) {
                    $all = true;
                    foreach ($clauses as $k) {
                        if (! array_filter($o['conds'], fn (string $c) => $this->conditionMatches($c, $k))) {
                            $all = false;
                            break;
                        }
                    }
                    if ($all) {
                        $backed = true;
                        break;
                    }
                }

                if (! $backed) {
                    $problems[] = "{$field}: declared alternative #{$i} is not backed by any markup region";
                }

                foreach ($clauses as [$parent, $op, $answers]) {
                    if ($op !== 'is') {
                        continue;
                    }
                    $named = [];
                    foreach ($mine as $o) {
                        foreach ($o['conds'] as $c) {
                            if ($this->conditionMentions($c, $parent)) {
                                $named = array_merge($named, $this->conditionAnswers($c));
                            }
                        }
                    }
                    foreach (array_diff($answers, $named) as $missing) {
                        $problems[] = "{$field}: declares {$parent} = '{$missing}', which no markup condition names";
                    }
                }
            }
        }

        $this->assertSame([], $problems, "ConditionalTerms and the canonical {$role} markup disagree:\n - " . implode("\n - ", $problems));
    }

    public function roles(): array
    {
        return ['seller' => ['seller'], 'landlord' => ['landlord']];
    }

    /** @test Each derived reveal named above is driven by the parent it is attributed to. */
    public function the_derived_reveals_are_driven_by_their_parent(): void
    {
        $behaviour = $this->markup(self::LANDLORD_BEHAVIOUR);

        $this->assertStringContainsString("\$('.lease_term_options').val()", $behaviour);
        $this->assertStringContainsString("\$('#other_desired_lease_length_wrapper')", $behaviour);
        $this->assertStringContainsString("\$('#owner_pays').val()", $behaviour);
        $this->assertStringContainsString("\$('#other_owner_pays_wrapper')", $behaviour);
        $this->assertStringContainsString("\$('#terms_of_lease').val()", $behaviour);
        $this->assertStringContainsString("getElementById('otherLeaseContainer')", $behaviour);
        $this->assertSame(3, substr_count($behaviour, "includes('Other')"));
    }

    /**
     * @test
     *
     * Where the finished listing page gates a branch, it gates it on the same
     * parent and the same option the map declares — so "exactly like the
     * listing" is a checked claim for those branches.
     */
    public function the_listing_page_gates_the_same_branches_on_the_same_parents(): void
    {
        $view    = file_get_contents(base_path(self::SELLER_VIEW));
        $options = [];

        foreach (ConditionalTerms::followUps('seller') as $alternatives) {
            foreach ($alternatives as $clauses) {
                foreach ($clauses as [$parent, $op, $answers]) {
                    if ($parent === 'offered_financing' && $op === 'is') {
                        $options = array_merge($options, $answers);
                    }
                }
            }
        }

        foreach (array_unique($options) as $option) {
            $this->assertStringContainsString("\$terms::chose(\$ofFin, '{$option}')", $view,
                "the listing page must gate the {$option} branch on Offered Financing");
        }

        $this->assertStringContainsString("\$terms::chose(\$val('sale_provision'), 'Assignment Contract')", $view);
        $this->assertStringContainsString("@if(\$str('prepayment_penalty') === 'Yes')", $view);
        $this->assertStringContainsString("@if(\$str('balloon_payment') === 'Yes')", $view);
    }

    /** @test Every field the map names is a real canonical field (or a flow-level parent). */
    public function every_follow_up_names_real_fields(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $known = array_merge(self::fields($role), ['property_type', 'auction_type']);

            foreach (ConditionalTerms::followUps($role) as $field => $alternatives) {
                $this->assertContains($field, self::fields($role), "{$role}: {$field} is not a canonical field");

                foreach ($alternatives as $clauses) {
                    foreach ($clauses as [$parent]) {
                        $this->assertContains($parent, $known, "{$role}: {$field} names an unknown parent {$parent}");
                    }
                }
            }
        }
    }

    // ─── Markup walker ──────────────────────────────────────────────────────

    /**
     * Every bound field in a partial, with the condition text of each region
     * enclosing it.
     *
     * @return list<array{field: string, conds: list<string>}>
     */
    private function occurrences(string $partial): array
    {
        $src = $this->markup($partial);

        $pattern = '/@(?<dir>if|elseif)\s*(?<p>\((?:[^()]++|(?&p))*\))'
            . '|(?<else>@else\b)'
            . '|(?<endif>@endif\b)'
            . '|(?<div><div\b(?:[^>"]|"[^"]*")*>)'
            . '|(?<close><\/div>)'
            . '|wire:model[a-z.]*="(?<wm>[a-z0-9_]+)"'
            . '|\bid="(?<jsid>[a-z_]+)"'
            . '|class="(?<jscls>lease_term_options)\b/s';

        preg_match_all($pattern, $src, $tokens, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $stack = [];
        $out   = [];

        $record = function (string $field) use (&$stack, &$out): void {
            $conds = [];
            foreach ($stack as $frame) {
                if ($frame['cond'] !== null) {
                    $conds[] = $frame['cond'];
                }
            }
            $out[] = ['field' => $field, 'conds' => $conds];
        };

        $top = function (string $kind) use (&$stack): ?int {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i]['kind'] === $kind) {
                    return $i;
                }
            }
            return null;
        };

        foreach ($tokens as $t) {
            if ($t['dir'] === 'if') {
                $c = substr($t['p'], 1, -1);
                $stack[] = ['kind' => 'if', 'cond' => $c, 'prior' => [$c], 'alpine' => null];
            } elseif ($t['dir'] === 'elseif') {
                $i = $top('if');
                $c = substr($t['p'], 1, -1);
                $stack[$i]['cond']    = $c;
                $stack[$i]['prior'][] = $c;
            } elseif ($t['else'] !== null) {
                $i = $top('if');
                $stack[$i]['cond'] = 'else: !(' . implode(') && !(', $stack[$i]['prior']) . ')';
            } elseif ($t['endif'] !== null) {
                array_splice($stack, $top('if'), 1);
            } elseif ($t['div'] !== null) {
                $tag    = $t['div'];
                $alpine = null;
                foreach (array_reverse($stack) as $frame) {
                    if ($frame['alpine'] !== null) {
                        $alpine = $frame['alpine'];
                        break;
                    }
                }

                if (preg_match("/entangle\\('([a-z_]+)'\\)/", $tag, $e)) {
                    $record($e[1]);
                    $alpine = $e[1];
                }

                $cond = null;
                if (preg_match('/style="display:\s*\{\{\s*(.*?)\s+\?\s+\'/s', $tag, $s)) {
                    $cond = $s[1];
                } elseif (preg_match('/x-show="([^"]+)"/', $tag, $x)) {
                    $cond = 'x-show[' . $alpine . ']: ' . $x[1];
                } elseif (preg_match('/class="[^"]*\bd-none\b/', $tag) || preg_match('/style="[^"{]*display:\s*none/', $tag)) {
                    preg_match('/id="([^"]+)"/', $tag, $id);
                    $cond = 'hidden#' . ($id[1] ?? '?');
                }

                $stack[] = ['kind' => 'div', 'cond' => $cond, 'alpine' => $alpine];
            } elseif ($t['close'] !== null) {
                $i = $top('div');
                if ($i !== null) {
                    array_splice($stack, $i, 1);
                }
            } elseif ($t['wm'] !== null) {
                $record($t['wm']);
            } elseif ($t['jsid'] !== null && isset(self::JS_BOUND['#' . $t['jsid']])) {
                $record(self::JS_BOUND['#' . $t['jsid']]);
            } elseif ($t['jscls'] !== null) {
                $record(self::JS_BOUND['.' . $t['jscls']]);
            }
        }

        return $out;
    }

    /** Every clause that can open `$field`: its own, and its parents', at any depth. */
    private function clauseClosure(array $map, string $field, array $seen = []): array
    {
        if (! isset($map[$field]) || isset($seen[$field])) {
            return [];
        }

        $seen[$field] = true;
        $clauses      = [];

        foreach ($map[$field] as $alternative) {
            foreach ($alternative as $clause) {
                $clauses[] = $clause;
                $clauses   = array_merge($clauses, $this->clauseClosure($map, $clause[0], $seen));
            }
        }

        return $clauses;
    }

    private function conditionMentions(string $cond, string $parent): bool
    {
        if (preg_match('/^x-show\[([a-z_]*)\]/', $cond, $m)) {
            return $m[1] === $parent;
        }

        foreach (self::DERIVED as $needle => [$derivedParent]) {
            if (str_contains($cond, $needle)) {
                return $derivedParent === $parent;
            }
        }

        return (bool) preg_match('/\$(?:this->)?' . preg_quote($parent, '/') . '\b/', $cond);
    }

    /** @return list<string> */
    private function conditionAnswers(string $cond): array
    {
        foreach (self::DERIVED as $needle => [, $answer]) {
            if (str_contains($cond, $needle)) {
                return [$answer];
            }
        }

        preg_match_all("/'([^']*)'/", $cond, $m);

        return array_values(array_filter($m[1], fn ($a) => $a !== ''));
    }

    private function conditionMatches(string $cond, array $clause): bool
    {
        [$parent, $op, $answers] = $clause;

        if (! $this->conditionMentions($cond, $parent)) {
            return false;
        }

        $named   = $this->conditionAnswers($cond);
        $negated = str_starts_with($cond, 'else:') || str_contains($cond, '!=');

        return match ($op) {
            'is'      => ! $negated && $named !== [] && array_diff($named, $answers) === [],
            'not'     => $negated && array_intersect($named, $answers) !== [],
            'other'   => ! $negated && in_array('Other', $named, true),
            'accepts' => str_contains($cond, '__showsPeriod($' . $parent . ')'),
            default   => false,
        };
    }

    // ─── F. One presenter ───────────────────────────────────────────────────

    /** @test Seller and Landlord reach review through the same presenter. */
    public function both_roles_share_one_review_presenter(): void
    {
        foreach ([SellerMlsQuickImport::class, LandlordMlsQuickImport::class] as $component) {
            $source = file_get_contents((new \ReflectionClass($component))->getFileName());

            $this->assertStringContainsString('QuickImportTermsReview::rows(', $source);
            $this->assertStringNotContainsString('private function humaniseTermField', $source);
        }
    }

    // ─── G. End to end ──────────────────────────────────────────────────────

    /**
     * @test
     *
     * Through the real component: an Assumable fee shows on review; after Back,
     * deselecting Assumable and returning, it does not — and the stored answer is
     * kept, not deleted. Display logic only.
     */
    public function the_review_step_drops_a_stale_follow_up_end_to_end(): void
    {
        $this->seedRecord('QI-REVIEW-S', 'Residential', 385000);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', 'QI-REVIEW-S')
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->set('offered_financing', ['Assumable'])
            ->set('assumable_fee_type', '$')
            ->set('assumable_fee_amount', '2500')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $review = $component->viewData('termsReview');
        $this->assertSame('$2,500', $review['Assumption Fee'] ?? null);
        $this->assertSame('$385,000', $review['Desired Sale Price'] ?? null);

        $component->call('backToTerms')
            ->set('offered_financing', ['Cash'])
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $review = $component->viewData('termsReview');
        $this->assertArrayNotHasKey('Assumption Fee', $review);
        $this->assertArrayNotHasKey('Assumable fee type', $review);
        $this->assertSame('Cash', $review['Offered financing'] ?? null);

        $component->assertSet('assumable_fee_amount', '2500');
    }
}
