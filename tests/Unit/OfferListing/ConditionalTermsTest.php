<?php

namespace Tests\Unit\OfferListing;

use App\Support\OfferListing\ConditionalTerms;
use PHPUnit\Framework\TestCase;

/**
 * The parent/child rules a listing page follows when it publishes Your Terms.
 *
 * Extends PHPUnit's TestCase directly, with no application: the helper is called
 * from Blade during a render and must not depend on a booted container or on
 * config, exactly as LandlordScreeningPolicy must not. A test that boots Laravel
 * would not notice the day someone adds a `config()` call here.
 */
class ConditionalTermsTest extends TestCase
{
    // ─── The three stored shapes ─────────────────────────────────────────────

    /**
     * @test
     *
     * A multi-select answer arrives as an array from a live component, as a JSON
     * string from EAV meta, and occasionally as a bare string from an older row.
     * All three are the same answer.
     */
    public function a_selection_is_recognised_in_every_shape_it_is_stored_in(): void
    {
        $this->assertTrue(ConditionalTerms::chose(['Cash', 'Assumable'], 'Assumable'));
        $this->assertTrue(ConditionalTerms::chose('["Cash","Assumable"]', 'Assumable'));
        $this->assertTrue(ConditionalTerms::chose('Assumable', 'Assumable'));

        // Doubly-encoded, which meta genuinely holds on some rows.
        $this->assertTrue(ConditionalTerms::chose('"[\"Assumable\"]"', 'Assumable'));
    }

    /** @test */
    public function an_unchosen_option_is_not_reported_as_chosen(): void
    {
        $this->assertFalse(ConditionalTerms::chose(['Cash'], 'Assumable'));
        $this->assertFalse(ConditionalTerms::chose([], 'Assumable'));
        $this->assertFalse(ConditionalTerms::chose(null, 'Assumable'));
        $this->assertFalse(ConditionalTerms::chose('', 'Assumable'));
        $this->assertFalse(ConditionalTerms::chose('null', 'Assumable'));
    }

    /**
     * @test
     *
     * "Lease Option" must not answer for "Lease Purchase". They are separate
     * branches with separate follow-up questions, and a substring match would
     * open both from either.
     */
    public function matching_is_exact_and_not_a_substring(): void
    {
        $this->assertTrue(ConditionalTerms::chose(['Lease Purchase'], 'Lease Purchase'));
        $this->assertFalse(ConditionalTerms::chose(['Lease Purchase'], 'Lease Option'));
        $this->assertFalse(ConditionalTerms::chose(['Lease'], 'Lease Option'));
    }

    /** @test */
    public function chose_any_answers_for_a_group_of_options(): void
    {
        $this->assertTrue(ConditionalTerms::choseAny(['Cash'], ['Assumable', 'Cash']));
        $this->assertFalse(ConditionalTerms::choseAny(['Cash'], ['Assumable', 'Seller Financing']));
    }

    // ─── "Other" and the text box behind it ──────────────────────────────────

    /**
     * @test
     *
     * THE BUG THIS EXISTS FOR. Special Sale Provision is a multi-select, and the
     * page used to ask whether the whole joined string equalled "Other". A seller
     * choosing "Short Sale" AND "Other" therefore published the literal word
     * "Other" and never the provision they typed.
     */
    public function other_is_replaced_by_its_text_even_alongside_another_choice(): void
    {
        $this->assertSame(
            ['Short Sale', 'Divorce sale'],
            ConditionalTerms::withOther(['Short Sale', 'Other'], 'Divorce sale'),
        );
    }

    /**
     * @test
     *
     * The literal survives when nothing was typed: the seller did choose "Other",
     * and dropping it would state that no provision applies.
     */
    public function other_survives_when_its_text_box_was_left_empty(): void
    {
        $this->assertSame(['Short Sale', 'Other'], ConditionalTerms::withOther(['Short Sale', 'Other'], ''));
        $this->assertSame(['Other'], ConditionalTerms::withOther(['Other'], null));
    }

    /** @test */
    public function other_text_is_not_applied_to_an_answer_that_is_not_other(): void
    {
        $this->assertSame(['Short Sale'], ConditionalTerms::withOther(['Short Sale'], 'Divorce sale'));
    }

    /** @test */
    public function the_scalar_form_substitutes_the_same_way(): void
    {
        $this->assertSame('Divorce sale', ConditionalTerms::valueWithOther('Other', 'Divorce sale'));
        $this->assertSame('Other', ConditionalTerms::valueWithOther('Other', ''));
        $this->assertSame('Short Sale', ConditionalTerms::valueWithOther('Short Sale', 'Divorce sale'));
        $this->assertSame('', ConditionalTerms::valueWithOther(null, 'Divorce sale'));
    }

    // ─── Emptiness ───────────────────────────────────────────────────────────

    /** @test */
    public function nothing_empty_counts_as_an_answer(): void
    {
        foreach ([null, '', '   ', [], ['', '  '], '[]'] as $empty) {
            $this->assertFalse(
                ConditionalTerms::answered($empty),
                'expected ' . var_export($empty, true) . ' to read as unanswered',
            );
        }
    }

    /**
     * @test
     *
     * Zero is an answer. "0 pets allowed" and "$0 application fee" are facts, and
     * a truthiness check would delete both.
     */
    public function zero_is_an_answer(): void
    {
        $this->assertTrue(ConditionalTerms::answered('0'));
        $this->assertTrue(ConditionalTerms::answered(0));
    }

    // ─── Amounts and their $ / % toggle ──────────────────────────────────────

    /**
     * @test
     *
     * The other bug this exists for: a seller asking for a 3% initial deposit
     * published "$3", because the page formatted every deposit as currency and
     * never consulted the type control beside it.
     */
    public function an_amount_is_formatted_by_the_control_beside_it(): void
    {
        $this->assertSame('$2,500', ConditionalTerms::amount('2500', '$'));
        $this->assertSame('3%', ConditionalTerms::amount('3', '%'));
        $this->assertSame('2.5%', ConditionalTerms::amount('2.5', '%'));
        $this->assertSame('$2,500', ConditionalTerms::amount('2,500', null));
    }

    /** @test */
    public function an_absent_amount_produces_null_so_the_row_is_omitted(): void
    {
        $this->assertNull(ConditionalTerms::amount('', '$'));
        $this->assertNull(ConditionalTerms::amount(null, '%'));
    }

    /**
     * @test
     *
     * A value that is not a number is returned unchanged rather than turned into
     * "$0" — "To be negotiated" is a real answer some sellers type.
     */
    public function a_non_numeric_amount_is_left_alone(): void
    {
        $this->assertSame('To be negotiated', ConditionalTerms::amount('To be negotiated', '$'));
    }

    /** @test */
    public function a_list_drops_empties_and_keeps_order(): void
    {
        $this->assertSame(['Cash', 'Assumable'], ConditionalTerms::toList(['Cash', '', 'Assumable', null]));
        $this->assertSame([], ConditionalTerms::toList([['nested']]));
    }
}
