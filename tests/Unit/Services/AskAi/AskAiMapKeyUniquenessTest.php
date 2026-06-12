<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiRunnerV2Service;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guards against silent phrase loss caused by duplicate keys in
 * LISTING_KEY_KEYWORD_MAP, FAQ_KEY_KEYWORD_MAP, and deriveFieldLabel.
 *
 * PHP last-key-wins: a duplicate key in a literal array silently drops the
 * first declaration's value. Phrases defined only in the first entry become
 * permanently unreachable at runtime — they appear in source but never match.
 *
 * This test uses bracket-depth source scanning to isolate each const/method
 * block precisely, avoiding false positives from keys in other file sections.
 */
class AskAiMapKeyUniquenessTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $rc           = new ReflectionClass(AskAiRunnerV2Service::class);
        $this->source = (string) file_get_contents($rc->getFileName());
    }

    // -------------------------------------------------------------------------
    // Case A — LISTING_KEY_KEYWORD_MAP source-level key uniqueness
    // -------------------------------------------------------------------------

    /** @test */
    public function case_a_listing_key_keyword_map_has_no_duplicate_keys(): void
    {
        $duplicates = $this->findDuplicatesInConst('LISTING_KEY_KEYWORD_MAP');

        $this->assertEmpty(
            $duplicates,
            'Duplicate keys in LISTING_KEY_KEYWORD_MAP (first entry\'s phrases are silently dropped at runtime): '
            . implode(', ', $duplicates)
        );
    }

    // -------------------------------------------------------------------------
    // Case B — FAQ_KEY_KEYWORD_MAP source-level key uniqueness
    // -------------------------------------------------------------------------

    /** @test */
    public function case_b_faq_key_keyword_map_has_no_duplicate_keys(): void
    {
        $duplicates = $this->findDuplicatesInConst('FAQ_KEY_KEYWORD_MAP');

        $this->assertEmpty(
            $duplicates,
            'Duplicate keys in FAQ_KEY_KEYWORD_MAP (first entry\'s phrases are silently dropped at runtime): '
            . implode(', ', $duplicates)
        );
    }

    // -------------------------------------------------------------------------
    // Case C — deriveFieldLabel array has no duplicate keys
    // -------------------------------------------------------------------------

    /** @test */
    public function case_c_derive_field_label_array_has_no_duplicate_keys(): void
    {
        $duplicates = $this->findDuplicatesInMethodReturn('deriveFieldLabel');

        $this->assertEmpty(
            $duplicates,
            'Duplicate keys in deriveFieldLabel lookup array (last-wins silently drops earlier labels): '
            . implode(', ', $duplicates)
        );
    }

    // -------------------------------------------------------------------------
    // Case D — runtime key count matches source unique count
    // -------------------------------------------------------------------------

    /** @test */
    public function case_d_listing_key_map_runtime_count_matches_source_unique_count(): void
    {
        $rc  = new ReflectionClass(AskAiRunnerV2Service::class);
        $map = $rc->getConstant('LISTING_KEY_KEYWORD_MAP');
        $this->assertIsArray($map, 'LISTING_KEY_KEYWORD_MAP must be an array constant');

        $runtimeCount = count($map);
        $sourceKeys   = $this->extractKeysFromConstBlock('LISTING_KEY_KEYWORD_MAP');
        $sourceUnique = count(array_unique($sourceKeys));

        $this->assertSame(
            $sourceUnique,
            $runtimeCount,
            "Runtime key count ({$runtimeCount}) !== source unique key count ({$sourceUnique}). "
            . 'This means duplicate source keys were collapsed by PHP last-key-wins, '
            . 'silently discarding phrases from earlier definitions.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the first balanced bracket block for a named const and return all
     * associative array key occurrences within that block.
     *
     * @return list<string>
     */
    private function extractKeysFromConstBlock(string $constName): array
    {
        $block = $this->extractConstBlock($constName);
        if ($block === '') {
            return [];
        }

        // Match any quoted string key followed by =>
        preg_match_all("/^\s*'([^']+)'\s*=>/m", $block, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Find duplicate keys within a named const array block.
     *
     * @return list<string>
     */
    private function findDuplicatesInConst(string $constName): array
    {
        $keys   = $this->extractKeysFromConstBlock($constName);
        $counts = array_count_values($keys);

        return array_keys(array_filter($counts, fn (int $c) => $c > 1));
    }

    /**
     * Find duplicate keys in the return array of a named method.
     * Scans from the method name to its balanced closing brace.
     *
     * @return list<string>
     */
    private function findDuplicatesInMethodReturn(string $methodName): array
    {
        // Find the method definition
        $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
        if (! preg_match($pattern, $this->source, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $methodStart = $m[0][1];
        $segment     = substr($this->source, $methodStart);

        // Find the return [ statement
        $returnPos = strpos($segment, 'return [');
        if ($returnPos === false) {
            $returnPos = strpos($segment, "return\n");
        }
        if ($returnPos === false) {
            return [];
        }

        $arrayStart = strpos($segment, '[', $returnPos);
        if ($arrayStart === false) {
            return [];
        }

        $block = $this->extractBracketBlock($segment, $arrayStart);

        preg_match_all("/^\s*'([^']+)'\s*=>/m", $block, $matches);
        $keys   = $matches[1] ?? [];
        $counts = array_count_values($keys);

        return array_keys(array_filter($counts, fn (int $c) => $c > 1));
    }

    /**
     * Extract the source of a PHP const array block using bracket-depth tracking.
     * Only the first-level array is returned (does not recurse into nested arrays).
     */
    private function extractConstBlock(string $constName): string
    {
        $marker = $constName . ' = [';
        $pos    = strpos($this->source, $marker);
        if ($pos === false) {
            return '';
        }

        $arrayStart = strpos($this->source, '[', $pos);
        if ($arrayStart === false) {
            return '';
        }

        return $this->extractBracketBlock($this->source, $arrayStart);
    }

    /**
     * Extract the content of a balanced bracket block starting at $offset.
     */
    private function extractBracketBlock(string $source, int $offset): string
    {
        $depth = 0;
        $len   = strlen($source);
        $i     = $offset;

        while ($i < $len) {
            if ($source[$i] === '[') {
                $depth++;
            } elseif ($source[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $offset, $i - $offset + 1);
                }
            }
            $i++;
        }

        return '';
    }
}
