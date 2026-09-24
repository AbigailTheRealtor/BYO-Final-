<?php

namespace Tests\Unit\Spatial\OvertureExtractV2;

use App\Services\Spatial\OvertureExtractV2\OvertureV2Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * The committed DuckDB recipe agrees with config/overture_extract_v2.php — same release, same box —
 * projects exactly the fields the normalizer accepts, and filters on the box ALONE, so no
 * eligibility decision can hide in SQL where it would escape the accounting.
 */
class OvertureExtractV2SqlManifestTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../../scripts/overture-v2';

    private static function sql(string $file): string
    {
        return (string) file_get_contents(self::DIR . '/' . $file);
    }

    /** SQL with `--` comments removed. */
    private static function code(string $file): string
    {
        return (string) preg_replace('/--[^\n]*/', '', self::sql($file));
    }

    private static function config(): array
    {
        return require __DIR__ . '/../../../../config/overture_extract_v2.php';
    }

    public function test_release_pin_agrees_with_config(): void
    {
        $release = self::config()['release'];
        foreach (['extract_bbox_raw.sql', 'count_release_rows.sql'] as $file) {
            $this->assertStringContainsString("/release/{$release}/theme=places/type=place/", self::code($file), $file);
            $this->assertSame(1, preg_match_all('#/release/[^/]+/#', self::code($file)), "{$file} names exactly one release");
        }
    }

    public function test_bbox_is_containment_of_the_row_bbox_and_agrees_with_config(): void
    {
        $b = self::config()['bbox'];
        $code = preg_replace('/\s+/', ' ', self::code('extract_bbox_raw.sql'));
        $this->assertStringContainsString(sprintf('bbox.xmin >= %.2f', $b['west']), $code);
        $this->assertStringContainsString(sprintf('bbox.xmax <= %.2f', $b['east']), $code);
        $this->assertStringContainsString(sprintf('bbox.ymin >= %.2f', $b['south']), $code);
        $this->assertStringContainsString(sprintf('bbox.ymax <= %.2f', $b['north']), $code);
    }

    public function test_the_box_is_the_only_filter(): void
    {
        $code = strtolower(self::code('extract_bbox_raw.sql'));
        $where = substr($code, strpos($code, 'where'), strpos($code, 'order by') - strpos($code, 'where'));
        foreach (['confidence', 'operating_status', 'taxonomy', 'categories', 'basic_category', 'brand', 'names', ' in ('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $where, "the SQL WHERE must not decide on {$forbidden}");
        }
        $this->assertSame(1, substr_count($code, 'where'));
    }

    public function test_projection_is_exactly_the_normalizer_raw_fields(): void
    {
        preg_match_all('/\bAS\s+([a-z_]+)\s*,?\s*$/mi', self::code('extract_bbox_raw.sql'), $m);
        $this->assertSame(OvertureV2Normalizer::RAW_FIELDS, $m[1]);
    }

    public function test_output_is_ordered_and_scratch_only(): void
    {
        $code = self::code('extract_bbox_raw.sql');
        $this->assertMatchesRegularExpression('/ORDER BY id\s*\)/', $code);
        $this->assertStringContainsString("TO 'overture_v2_raw_bbox.ndjson'", $code);
        $this->assertStringNotContainsString('COPY', self::code('count_release_rows.sql'), 'the count script writes nothing');
    }

    public function test_the_sql_blanks_ambient_credentials_for_the_anonymous_read(): void
    {
        foreach (['extract_bbox_raw.sql', 'count_release_rows.sql'] as $file) {
            $this->assertStringContainsString("SET s3_access_key_id = ''", self::code($file), $file);
        }
    }
}
