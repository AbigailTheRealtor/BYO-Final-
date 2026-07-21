<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * Pure, deterministic name normalisation + trigram similarity for the offline authority↔corpus
 * matcher. No DB, no network, no PostGIS.
 *
 * NORMALISATION (decision D1 — an AUTHORED convention; SSOT §8.2 names "normalised-name" but
 * does not define the rule):
 *   lowercase → transliterate common Unicode accents to ASCII → replace punctuation /
 *   non-alphanumerics with spaces → collapse whitespace → trim.
 * Transliteration runs BEFORE the non-alphanumeric strip so an accented letter becomes its ASCII
 * equivalent rather than being dropped to a space.
 *
 * TRIGRAM SIMILARITY (decision D2): trigrams follow the public PostgreSQL `pg_trgm` reference —
 * each word is padded with two leading spaces and one trailing space, then split into 3-grams;
 * similarity is the Jaccard ratio |A∩B| / |A∪B| of the trigram sets. This is a documented
 * APPROXIMATION of `pg_trgm.similarity()`; the AUTHORITATIVE rule is the Class-2 SQL manifest
 * (`spikes/.../sql/link_authority.sql`). Exact score parity with PostgreSQL is a Class-2 concern.
 *
 * @see \Tests\Unit\Spatial\NameNormalizerTest
 */
final class NameNormalizer
{
    /**
     * Lowercase accented Latin characters → ASCII. Applied after mb_strtolower, so only lowercase
     * keys are needed. Deterministic and dependency-free (no intl/iconv locale dependence).
     *
     * @var array<string,string>
     */
    private const TRANSLITERATION = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
    ];

    /** Normalise a name to the canonical comparison form (may be empty). */
    public function normalize(?string $name): string
    {
        $s = mb_strtolower((string) $name, 'UTF-8');
        $s = strtr($s, self::TRANSLITERATION);
        // Replace every run of non-[a-z0-9] (punctuation, spaces, residual non-ASCII) with a space.
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? '';

        return trim($s);
    }

    /**
     * pg_trgm-spec trigram set for a name (order-independent, de-duplicated).
     *
     * @return list<string>
     */
    public function trigrams(?string $name): array
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return [];
        }

        $set = [];
        foreach (explode(' ', $normalized) as $word) {
            if ($word === '') {
                continue;
            }
            $padded = '  ' . $word . ' '; // two leading spaces, one trailing (pg_trgm)
            $len = strlen($padded);       // ASCII after normalise → byte length is correct
            for ($i = 0; $i + 3 <= $len; $i++) {
                $set[substr($padded, $i, 3)] = true;
            }
        }

        return array_keys($set);
    }

    /**
     * Trigram-set Jaccard similarity in [0,1]. Two empty names (or two names with no shared
     * trigrams) return 0.0.
     */
    public function similarity(?string $a, ?string $b): float
    {
        $ta = $this->trigrams($a);
        $tb = $this->trigrams($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $setB = array_flip($tb);
        $intersection = 0;
        foreach ($ta as $t) {
            if (isset($setB[$t])) {
                $intersection++;
            }
        }

        $union = count($ta) + count($tb) - $intersection;

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}
