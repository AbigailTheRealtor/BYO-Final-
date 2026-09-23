<?php

namespace Tests\Unit\ListingPreferences\Taste;

use PHPUnit\Framework\TestCase;

/**
 * Phase 5's structural promises, read from the source (governance §14).
 *
 * A flag gates a caller; these guards pin WHICH caller, WHERE in the pipeline
 * it sits, and what it can never read. Code is compared with comments stripped
 * where prose describing a prohibition would otherwise look like a violation.
 */
class TasteRerankingArchitectureGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    /**
     * THE PAGINATION BOUNDARY. The rerank runs over the whole mapped list —
     * after mapping, before the page is sliced — and the total, pins and slice
     * are all taken from its output.
     *
     * @test
     */
    public function the_rerank_sits_between_mapping_and_the_page_slice(): void
    {
        $code = $this->code('app/Http/Controllers/Stellar/StellarBuyerResultsController.php');

        $map    = strpos($code, '$this->viewMapper->map($matchedCollection)');
        $rerank = strpos($code, '$this->tasteReranking->rerankStellarResults(');
        $assign = strpos($code, '$mapped = $taste->cards;');
        $total  = strpos($code, '$total     = count($mapped);');
        $slice  = strpos($code, 'array_slice($mapped');
        $pins   = strpos($code, 'foreach ($mapped as $card)');

        foreach (compact('map', 'rerank', 'assign', 'total', 'slice', 'pins') as $name => $pos) {
            $this->assertNotFalse($pos, "{$name} must exist in the controller");
        }

        $this->assertLessThan($rerank, $map);
        $this->assertLessThan($assign, $rerank);
        $this->assertLessThan($total, $assign);
        $this->assertLessThan($slice, $assign);
        $this->assertLessThan($pins, $assign);
        $this->assertSame(1, substr_count($code, 'rerankStellarResults('), 'exactly one rerank call');
    }

    /** @test */
    public function the_matcher_the_scorer_and_the_score_config_never_see_taste(): void
    {
        foreach ([
            'app/Services/Stellar/Matching/BuyerMatchService.php',
            'app/Services/Stellar/Matching/BuyerMatchScorer.php',
            'app/Services/Stellar/Matching/BuyerMatchResultBuilder.php',
            'app/Services/Stellar/BuyerResultViewMapper.php',
            'config/match_scoring.php',
        ] as $path) {
            $this->assertDoesNotMatchRegularExpression('/taste|rerank|listing_?preference/i', (string) file_get_contents($this->root . '/' . $path), "{$path} must know nothing of Taste");
        }
    }

    /**
     * BYO search is out of Phase 5: no score, only explicit sorts, and SQL
     * pagination that a per-page reorder would misrepresent.
     *
     * @test
     */
    public function byo_search_is_untouched(): void
    {
        foreach ([
            'app/Http/Controllers/SellerOfferListingController.php',
            'app/Http/Controllers/LandlordOfferListingController.php',
            'resources/views/offer-listing/seller/search.blade.php',
            'resources/views/offer-listing/landlord/search.blade.php',
        ] as $path) {
            $this->assertDoesNotMatchRegularExpression('/taste|rerank/i', (string) file_get_contents($this->root . '/' . $path), "{$path} must not be personalized");
        }
    }

    /**
     * NO DOUBLE COUNTING. Learned taste may reorder; the customer's CURRENT
     * Save / Maybe / Pass on a candidate is UI state and is never read into the
     * order — the same feedback already reached the profile once.
     *
     * @test
     */
    public function reranking_never_reads_current_listing_state(): void
    {
        foreach ($this->rerankFiles() as $path => $code) {
            foreach (['ListingPreferenceReader', 'currentMany', 'ListingPreference::', 'ListingPreferencePrefetch', "'listing_preferences'"] as $token) {
                $this->assertStringNotContainsString($token, $code, "{$path} must not read current preference state ({$token})");
            }
        }
    }

    /** @test */
    public function reranking_never_reaches_location_ask_ai_or_other_customers(): void
    {
        foreach ($this->rerankFiles() as $path => $code) {
            foreach (['AskAi', 'ImportantPlace', 'LocationDna', 'Explore', 'GreatCircle', 'latitude', 'city', 'zip', 'User::', 'similar', 'OpenAI'] as $token) {
                $this->assertStringNotContainsStringIgnoringCase($token, $code, "{$path} must not contain {$token}");
            }
        }
    }

    /** @test */
    public function the_reranking_flag_has_exactly_one_reader(): void
    {
        $this->assertSame(
            ['app/Support/ListingPreferences/Taste/TasteDnaAvailability.php'],
            $this->grep("taste_reranking_enabled['\"]", ['app', 'routes', 'resources']),
        );
    }

    /** @test */
    public function the_card_component_only_includes_the_worded_partial(): void
    {
        $card = (string) file_get_contents($this->root . '/resources/views/components/stellar/buyer-result-card.blade.php');

        $this->assertSame(1, substr_count($card, "@include('listing-preferences.taste._rerank-explanation'"));
        $this->assertDoesNotMatchRegularExpression('/Taste(DnaReranker|Rerank|Profile|Signal)|\\\\Taste\\\\/', $card);
    }

    /** @test */
    public function customer_copy_is_never_called_ai_and_carries_no_numbers(): void
    {
        foreach ([
            'resources/views/listing-preferences/taste/_rerank-caption.blade.php',
            'resources/views/listing-preferences/taste/_rerank-explanation.blade.php',
            'app/Support/ListingPreferences/Taste/TasteRerankExplanation.php',
        ] as $path) {
            $code = $this->code($path);

            $this->assertDoesNotMatchRegularExpression('/\bAI\b|artificial|machine learning|algorithm/i', $code, "{$path} must not call it AI");
        }

        // The partials print only worded strings — no adjustment, weight or score.
        foreach (['_rerank-caption', '_rerank-explanation'] as $partial) {
            $code = $this->code("resources/views/listing-preferences/taste/{$partial}.blade.php");
            $this->assertDoesNotMatchRegularExpression('/adjustment|weight|total_score|score_display|subject_key|listing_id/i', $code);
        }
    }

    /** @test */
    public function phase_five_adds_no_table_and_touches_no_migration(): void
    {
        $this->assertSame([], $this->grep('taste|rerank', ['database/migrations']));
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, string> comment-stripped code of the Phase 5 PHP files */
    private function rerankFiles(): array
    {
        $out = [];

        foreach ([
            'app/Support/ListingPreferences/Taste/TasteDnaReranker.php',
            'app/Support/ListingPreferences/Taste/TasteRerankCandidate.php',
            'app/Support/ListingPreferences/Taste/TasteRerankContribution.php',
            'app/Support/ListingPreferences/Taste/TasteRerankInfluence.php',
            'app/Support/ListingPreferences/Taste/TasteRerankResult.php',
            'app/Support/ListingPreferences/Taste/TasteRerankExplanation.php',
            'app/Services/ListingPreferences/Taste/TasteRerankingService.php',
            'app/Services/ListingPreferences/Taste/TasteRerankOutcome.php',
        ] as $path) {
            $out[$path] = $this->code($path);
        }

        return $out;
    }

    private function code(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) {
            return (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($this->root . '/' . $path));
        }

        $code = '';

        foreach (token_get_all((string) file_get_contents($this->root . '/' . $path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @param  list<string> $paths
     * @return list<string>
     */
    private function grep(string $pattern, array $paths): array
    {
        $cmd = sprintf(
            'cd %s && grep -rliE %s %s 2>/dev/null',
            escapeshellarg($this->root),
            escapeshellarg($pattern),
            implode(' ', array_map('escapeshellarg', $paths)),
        );

        $out = array_values(array_filter(array_map('trim', explode("\n", (string) shell_exec($cmd)))));
        sort($out);

        return $out;
    }
}
