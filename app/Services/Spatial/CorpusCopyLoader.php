<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * The ONLY module that owns the PostgreSQL COPY wire format for a corpus load —
 * the load-side counterpart of NormalizedExtractIo. It authors the `COPY … FROM
 * STDIN` statement and serializes materialized rows into COPY *text* format
 * (tab-delimited, `\N` for NULL, backslash-escaped specials). It NEVER opens a
 * connection and NEVER streams to a server; the offline command writes the
 * payload to a file for the later Class-2 live `\copy`.
 *
 * COPY text-format contract (PostgreSQL default): columns joined by TAB, rows by
 * LF, and the escape set { \\ \t \n \r } is backslash-encoded — backslash FIRST
 * so a literal backslash never eats a following escape. NULL is the two-byte
 * token `\N`, distinct from an empty string. Deterministic and idempotent.
 */
final class CorpusCopyLoader
{
    private const NULL_TOKEN = '\\N';

    /**
     * `COPY <target> (<cols>) FROM STDIN` — the server-side form. $columns
     * defaults to the materializer's canonical order.
     *
     * @param list<string>|null $columns
     */
    public function copyStatement(string $target, ?array $columns = null): string
    {
        $columns ??= PlaceRowMaterializer::COLUMNS;

        return sprintf('COPY %s (%s) FROM STDIN', $target, implode(', ', $columns));
    }

    /**
     * `\copy <target> (<cols>) FROM '<file>' WITH (FORMAT text)` — the psql
     * client form that streams a local payload file without server-side file
     * access. This is the recipe the live operator runs.
     *
     * @param list<string>|null $columns
     */
    public function psqlCopyStatement(string $target, string $payloadPath, ?array $columns = null): string
    {
        $columns ??= PlaceRowMaterializer::COLUMNS;

        return sprintf(
            "\\copy %s (%s) FROM '%s' WITH (FORMAT text)",
            $target,
            implode(', ', $columns),
            $payloadPath
        );
    }

    /**
     * Serialize one ordered scalar row into a COPY text line (no trailing LF).
     *
     * @param list<mixed> $row
     */
    public function encodeRow(array $row): string
    {
        return implode("\t", array_map([$this, 'encodeValue'], $row));
    }

    /**
     * Full COPY payload for a set of rows (each row LF-terminated, or '' when
     * empty). Byte-identical for identical input.
     *
     * @param iterable<list<mixed>> $rows
     */
    public function toCopyText(iterable $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = $this->encodeRow($row);
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /**
     * Write the COPY payload to $path (creating the directory). Returns rows written.
     *
     * @param iterable<list<mixed>> $rows
     */
    public function writePayload(string $path, iterable $rows): int
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $this->toCopyText($rows));

        return count($rows);
    }

    private function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return self::NULL_TOKEN;
        }
        if (is_bool($value)) {
            return $value ? 't' : 'f';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            // Plain decimal, no scientific notation, trailing zeros trimmed.
            $s = rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');

            return $s === '' || $s === '-0' ? '0' : $s;
        }

        return $this->escape((string) $value);
    }

    /** Backslash-escape the COPY text special set. Backslash MUST come first. */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\t", "\n", "\r"],
            ['\\\\', '\\t', '\\n', '\\r'],
            $value
        );
    }
}
