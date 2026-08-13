<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\Search\MatchType;
use App\Services\LocationDna\Criteria\Search\TermMatcher;
use PHPUnit\Framework\TestCase;

/**
 * M1 — the one classifier both implementations share.
 *
 * SQL decides only whether a row is a CANDIDATE; this decides what kind of match it is, and the
 * ranker orders on the answer. Written twice it would drift, and every ranking test would then be
 * testing whichever copy the test double happened to use.
 */
class TermMatcherTest extends TestCase
{
    /** @test */
    public function an_identical_name_is_exact(): void
    {
        $this->assertSame(MatchType::Exact, TermMatcher::classify('Clearwater', 'clearwater'));
    }

    /** @test */
    public function a_leading_fragment_is_a_prefix(): void
    {
        $this->assertSame(MatchType::Prefix, TermMatcher::classify('Clearwater', 'clear'));
        $this->assertSame(MatchType::Prefix, TermMatcher::classify('Clearwater Beach', 'clearwater'));
    }

    /**
     * "beach" finds "Clearwater Beach" — the behaviour that makes a multi-word place findable by
     * the part of its name the user actually remembers.
     *
     * @test
     */
    public function a_later_word_start_is_a_word_match(): void
    {
        $this->assertSame(MatchType::Word, TermMatcher::classify('Clearwater Beach', 'beach'));
    }

    /**
     * MID-WORD SUBSTRINGS ARE NOT MATCHES, and this is the assertion that keeps the search
     * explainable. "each" inside "Beach" is a result the user cannot account for, and an
     * unaccountable result reads as a broken search rather than a generous one.
     *
     * @test
     */
    public function a_mid_word_substring_is_not_a_match(): void
    {
        $this->assertNull(TermMatcher::classify('Clearwater Beach', 'each'));
        $this->assertNull(TermMatcher::classify('Clearwater', 'water'));
    }

    /**
     * Hyphens split words because place names use them structurally.
     *
     * @test
     */
    public function a_hyphen_starts_a_new_word(): void
    {
        $this->assertSame(MatchType::Word, TermMatcher::classify('Winston-Salem', 'salem'));
        $this->assertSame(MatchType::Prefix, TermMatcher::classify('Winston-Salem', 'winston'));
    }

    /**
     * Both sides normalise, so the published spelling and the typed one meet in the middle.
     *
     * @test
     */
    public function the_saint_variants_all_classify_as_exact(): void
    {
        $this->assertSame(MatchType::Exact, TermMatcher::classify('St. Petersburg', 'st petersburg'));
        $this->assertSame(MatchType::Exact, TermMatcher::classify('Saint Petersburg', 'st petersburg'));
    }

    /** @test */
    public function an_unrelated_name_does_not_match(): void
    {
        $this->assertNull(TermMatcher::classify('Tampa', 'clearwater'));
    }

    /** @test */
    public function an_empty_term_never_matches(): void
    {
        $this->assertNull(TermMatcher::classify('Clearwater', ''));
    }

    /** @test */
    public function matching_ignores_case_on_both_sides(): void
    {
        $this->assertSame(MatchType::Exact, TermMatcher::classify('CLEARWATER', 'clearwater'));
    }
}
