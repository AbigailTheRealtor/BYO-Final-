<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NameNormalizer;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — name normalisation + pg_trgm-spec trigram similarity (decisions D1/D2).
 * Pure; no DB, no network.
 */
class NameNormalizerTest extends TestCase
{
    private NameNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new NameNormalizer();
    }

    /** @test */
    public function normalize_lowercases_transliterates_and_strips_punctuation(): void
    {
        $this->assertSame('cafe del mar', $this->n->normalize('Café Del Mar!!'));
        $this->assertSame('pena nino', $this->n->normalize('Peña Niño'));            // ñ → n
        $this->assertSame('a b', $this->n->normalize("  A —  B  "));                 // collapse + punctuation
        // Per D1, punctuation (incl. the apostrophe) becomes a space: "Mary's" → "mary s".
        $this->assertSame('st mary s hospital', $this->n->normalize("St. Mary's Hospital"));
        $this->assertSame('', $this->n->normalize(null));
        $this->assertSame('', $this->n->normalize('  '));
    }

    /** @test */
    public function transliteration_covers_common_accents(): void
    {
        $this->assertSame('aaaeeeiiiooouuu', $this->n->normalize('àáâéèêíìîóòôúùû'));
        $this->assertSame('strasse', $this->n->normalize('Straße'));                 // ß → ss
    }

    /** @test */
    public function trigrams_follow_the_pg_trgm_padding_spec(): void
    {
        // "abc" → padded "  abc " → {"  a"," ab","abc","bc "}
        $this->assertSame(['  a', ' ab', 'abc', 'bc '], $this->n->trigrams('abc'));
        $this->assertSame([], $this->n->trigrams(''));
        $this->assertSame([], $this->n->trigrams(null));
    }

    /** @test */
    public function similarity_is_one_for_equal_names_after_normalisation(): void
    {
        $this->assertSame(1.0, $this->n->similarity('Synthetic Regional Hospital', 'synthetic  regional   hospital'));
        $this->assertSame(1.0, $this->n->similarity('Café', 'cafe'));
    }

    /** @test */
    public function similarity_is_zero_for_disjoint_names_and_for_empty(): void
    {
        $this->assertSame(0.0, $this->n->similarity('abc', 'xyz'));
        $this->assertSame(0.0, $this->n->similarity('', 'anything'));
        $this->assertSame(0.0, $this->n->similarity(null, null));
    }

    /** @test */
    public function similarity_is_symmetric_and_bounded(): void
    {
        $a = 'Riverside Medical Center';
        $b = 'Riverside Medical Clinic';
        $ab = $this->n->similarity($a, $b);
        $ba = $this->n->similarity($b, $a);

        $this->assertSame($ab, $ba);
        $this->assertGreaterThan(0.0, $ab);
        $this->assertLessThan(1.0, $ab);
    }
}
