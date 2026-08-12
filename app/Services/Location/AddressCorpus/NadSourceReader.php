<?php

namespace App\Services\Location\AddressCorpus;

use Generator;
use RuntimeException;
use ZipArchive;

/**
 * Streams a NAD text distribution row by row, without extracting it.
 *
 * WHY IT READS THE ARCHIVE IN PLACE
 * ---------------------------------
 * The national text distribution is single-digit GB compressed and tens of GB
 * extracted. Extracting it to read it would need both at once, which is more
 * than the development volume has. PHP's `zip://` stream wrapper reads an entry
 * as a stream, so the footprint stays at the size of the download and the
 * decompression happens a buffer at a time.
 *
 * The same generator handles a plain `.csv`/`.txt` and a `.gz`, because the
 * fixtures used by the tests are plain files and the production input is not —
 * a reader that only understood the production shape would be a reader no test
 * ever exercised.
 *
 * HEADERS ARE MATCHED, NOT ASSUMED
 * --------------------------------
 * NAD field names are read from the file's own header row and matched
 * case-insensitively, so `Zip_Code`, `ZIP_CODE` and `zip_code` are one field.
 * {@see self::assertSchema()} refuses a file whose header lacks the fields the
 * importer depends on, and names what it actually found — a release that renamed
 * a column must stop the run rather than silently produce a corpus of rows with
 * empty streets.
 */
final class NadSourceReader
{
    /** Fields without which an import cannot proceed. */
    public const REQUIRED_HEADERS = ['UUID', 'StNam_Full', 'State', 'Latitude', 'Longitude'];

    /** Fields the importer reads when present. Absence is reported, not fatal. */
    public const OPTIONAL_HEADERS = [
        'AddNo_Full', 'Add_Number', 'SubAddress', 'Unit', 'Post_City',
        'Inc_Muni', 'County', 'Zip_Code', 'Placement',
    ];

    private const DELIMITERS = [',', '|', "\t", ';'];

    public function __construct(private readonly string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Source file not found: {$this->path}");
        }
    }

    /**
     * Every data row across every data entry in the source, as a header-keyed
     * array.
     *
     * A generator on purpose: the caller sees one row at a time and the file is
     * never materialised. Header keys are returned in their canonical NAD
     * spelling regardless of the file's casing.
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(): Generator
    {
        foreach ($this->streams() as $entryName => $handle) {
            try {
                $delimiter = $this->sniffDelimiter($entryName);
                $header    = fgetcsv($handle, 0, $delimiter);

                if ($header === false || $header === [null]) {
                    continue;
                }

                $map = $this->canonicalHeaderMap($header);

                while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                    // A blank line reads as [null]; it is not a malformed row.
                    if ($values === [null]) {
                        continue;
                    }

                    yield $this->combine($map, $values);
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }

    /**
     * The header of the first data entry, in the file's own spelling.
     *
     * @return list<string>
     */
    public function header(): array
    {
        foreach ($this->streams() as $entryName => $handle) {
            try {
                $header = fgetcsv($handle, 0, $this->sniffDelimiter($entryName));

                return $header === false ? [] : array_map(static fn ($h) => trim((string) $h), $header);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        return [];
    }

    /**
     * Refuses a source whose header does not carry the required fields.
     *
     * @return array{ok: bool, missing_required: list<string>, missing_optional: list<string>, header: list<string>}
     */
    public function assertSchema(): array
    {
        $header    = $this->header();
        $canonical = array_map(static fn (string $h) => strtolower(trim($h)), $header);

        $missingRequired = array_values(array_filter(
            self::REQUIRED_HEADERS,
            static fn (string $f) => ! in_array(strtolower($f), $canonical, true)
        ));

        $missingOptional = array_values(array_filter(
            self::OPTIONAL_HEADERS,
            static fn (string $f) => ! in_array(strtolower($f), $canonical, true)
        ));

        return [
            'ok'               => $missingRequired === [],
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'header'           => $header,
        ];
    }

    /** Names of the data entries this source will read. */
    public function entryNames(): array
    {
        if (! $this->isZip()) {
            return [basename($this->path)];
        }

        $zip = new ZipArchive();

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException("Cannot open archive: {$this->path}");
        }

        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if ($this->looksLikeData($name)) {
                $names[] = $name;
            }
        }

        $zip->close();

        return $names;
    }

    /**
     * Open read handles for each data entry, keyed by entry name.
     *
     * @return Generator<string, resource>
     */
    private function streams(): Generator
    {
        if ($this->isZip()) {
            foreach ($this->entryNames() as $name) {
                $handle = @fopen('zip://' . $this->path . '#' . $name, 'r');

                if ($handle === false) {
                    throw new RuntimeException("Cannot stream archive entry: {$name}");
                }

                yield $name => $handle;
            }

            return;
        }

        $handle = str_ends_with(strtolower($this->path), '.gz')
            ? @gzopen($this->path, 'r')
            : @fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open source: {$this->path}");
        }

        yield basename($this->path) => $handle;
    }

    private function isZip(): bool
    {
        return str_ends_with(strtolower($this->path), '.zip');
    }

    private function looksLikeData(string $name): bool
    {
        if (str_ends_with($name, '/') || str_contains($name, '__MACOSX')) {
            return false;
        }

        return (bool) preg_match('/\.(txt|csv|psv|tsv)$/i', $name);
    }

    /** @var array<string, string> */
    private array $delimiters = [];

    /**
     * Guess the delimiter by reading the header line through a handle of its
     * own.
     *
     * NAD has shipped comma- and pipe-delimited text across releases, so this is
     * read from the file rather than configured — a wrong guess produces one
     * enormous column and a header that matches nothing.
     *
     * A dedicated handle rather than a peek-and-rewind because `zip://` streams
     * are not seekable: rewinding one fails, and consuming the header line from
     * the handle the caller is about to parse would silently eat it. Reading one
     * short line twice is cheaper than either bug.
     */
    private function sniffDelimiter(string $entryName): string
    {
        if (isset($this->delimiters[$entryName])) {
            return $this->delimiters[$entryName];
        }

        $handle = $this->openEntry($entryName);
        $line   = $handle === null ? false : fgets($handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        $best  = ',';
        $count = 0;

        if ($line !== false) {
            foreach (self::DELIMITERS as $candidate) {
                $n = substr_count($line, $candidate);

                if ($n > $count) {
                    $count = $n;
                    $best  = $candidate;
                }
            }
        }

        return $this->delimiters[$entryName] = $best;
    }

    /** @return resource|null */
    private function openEntry(string $entryName)
    {
        if ($this->isZip()) {
            $handle = @fopen('zip://' . $this->path . '#' . $entryName, 'r');
        } elseif (str_ends_with(strtolower($this->path), '.gz')) {
            $handle = @gzopen($this->path, 'r');
        } else {
            $handle = @fopen($this->path, 'r');
        }

        return $handle === false ? null : $handle;
    }

    /**
     * Map each column position to its canonical NAD field name.
     *
     * @param  list<string|null> $header
     * @return array<int, string>
     */
    private function canonicalHeaderMap(array $header): array
    {
        $known = [];

        foreach (array_merge(self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS) as $field) {
            $known[strtolower($field)] = $field;
        }

        $map = [];

        foreach ($header as $i => $name) {
            $key = strtolower(trim((string) $name));
            // Unknown columns keep their own name; the normalizer ignores them,
            // and keeping them makes a header dump readable.
            $map[$i] = $known[$key] ?? trim((string) $name);
        }

        return $map;
    }

    /**
     * @param  array<int, string>      $map
     * @param  list<string|null>       $values
     * @return array<string, string|null>
     */
    private function combine(array $map, array $values): array
    {
        $row = [];

        foreach ($map as $i => $field) {
            $row[$field] = $values[$i] ?? null;
        }

        return $row;
    }
}
