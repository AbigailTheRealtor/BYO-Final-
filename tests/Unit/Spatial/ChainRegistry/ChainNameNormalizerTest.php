<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use App\Services\Spatial\ChainRegistry\ChainNameNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * `chain-name-norm-v1` (design §4.1). Pure; no container.
 */
class ChainNameNormalizerTest extends TestCase
{
    public function test_version_is_pinned(): void
    {
        $this->assertSame('chain-name-norm-v1', ChainNameNormalizer::VERSION);
    }

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function cases(): array
    {
        return [
            'store number stripped'        => ['PUBLIX #1029', 'publix'],
            'store number with space'      => ['Publix # 1029', 'publix'],
            'only a trailing number'       => ['#12 Publix', '12 publix'],
            'hyphenated chain'             => ['7-Eleven', '7 eleven'],
            'no-space variant stays apart' => ['7ELEVEN', '7eleven'],
            'slash co-brand + number'      => ['7-ELEVEN/SPEEDWAY #46807', '7 eleven speedway'],
            'apostrophe removed'           => ["Wendy's", 'wendys'],
            'curly apostrophe removed'     => ["Trader Joe\u{2019}s", 'trader joes'],
            'ampersand'                    => ['Winn-Dixie Wine & Spirits', 'winn dixie wine and spirits'],
            'chick-fil-a'                  => ['Chick-fil-A', 'chick fil a'],
            'mcdonalds'                    => ["McDonald's", 'mcdonalds'],
            'whitespace collapsed'         => ["  Burger   King \t", 'burger king'],
            'nfkc fullwidth'               => ["\u{FF37}\u{FF41}\u{FF57}\u{FF41}", 'wawa'],
            'letters kept, not dropped'    => ['Café Bustelo', 'café bustelo'],
            'empty'                        => ['', null],
            'punctuation only'             => ['-- # --', null],
            'null'                         => [null, null],
        ];
    }

    /** @dataProvider cases */
    public function test_normalises_per_contract(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, ChainNameNormalizer::normalize($input));
    }

    public function test_is_idempotent(): void
    {
        foreach (self::cases() as [$input]) {
            $once = ChainNameNormalizer::normalize($input);
            $this->assertSame($once, ChainNameNormalizer::normalize($once));
        }
    }

    public function test_no_fuzzy_folding(): void
    {
        // Different spellings stay different: identity never comes from similarity.
        $this->assertNotSame(ChainNameNormalizer::normalize('Walmart'), ChainNameNormalizer::normalize('Wallmart'));
        $this->assertNotSame(ChainNameNormalizer::normalize('7-Eleven'), ChainNameNormalizer::normalize('7Eleven'));
    }

    public function test_qid_shape(): void
    {
        $this->assertTrue(ChainNameNormalizer::isQidShaped('q672170'));
        $this->assertTrue(ChainNameNormalizer::isQidShaped(ChainNameNormalizer::normalize('Q672170')));
        $this->assertFalse(ChainNameNormalizer::isQidShaped('publix'));
        $this->assertFalse(ChainNameNormalizer::isQidShaped(null));
    }

    public function test_wikidata_canonicalises_and_refuses_malformed(): void
    {
        $this->assertSame('Q672170', ChainNameNormalizer::wikidata(' q672170 '));
        $this->assertNull(ChainNameNormalizer::wikidata(null));
        $this->assertNull(ChainNameNormalizer::wikidata('  '));
        $this->assertFalse(ChainNameNormalizer::wikidata('Publix'));
        $this->assertFalse(ChainNameNormalizer::wikidata('Q0'));
        $this->assertFalse(ChainNameNormalizer::wikidata('Q12a'));
    }
}
