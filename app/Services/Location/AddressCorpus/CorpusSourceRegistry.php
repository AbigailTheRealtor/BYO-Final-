<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\Contracts\AddressRowNormalizer;
use App\Services\Location\AddressCorpus\Contracts\AddressSourceReader;
use App\Services\Location\AddressCorpus\Ng911\HillsboroughColumnMap;
use App\Services\Location\AddressCorpus\Ng911\Ng911ColumnMap;
use App\Services\Location\AddressCorpus\Ng911\Ng911RowNormalizer;
use InvalidArgumentException;

/**
 * Which reader and normalizer serve a `--source` value.
 *
 * WHY THE COMMAND DOES NOT KNOW ABOUT COUNTIES
 * --------------------------------------------
 * `AddressImportCorpus` constructed `new NadSourceReader` and
 * `new NadRowNormalizer` directly, which made NAD the only thing it could read
 * and would have made every future jurisdiction an edit to the command. The
 * command now asks here and gets back a pair; it never names a county, a state
 * or a file format.
 *
 * That is the test this whole design has to pass. Adding a NENA jurisdiction
 * should be a column map plus one line in {@see self::NG911_MAPS} — not a
 * parser, not a coordinate adapter, not a branch in a console command.
 *
 * FAIL CLOSED
 * -----------
 * An unknown source raises. There is no default and no nearest match: reading a
 * file with the wrong parser produces a report about a schema that was never
 * parsed, and every number in it would be wrong in a way that looks fine.
 */
final class CorpusSourceRegistry
{
    /**
     * NENA NG9-1-1 jurisdictions, by `--source` value.
     *
     * @var array<string, callable(): Ng911ColumnMap>
     */
    private const NG911_MAPS = [
        Ng911\PinellasColumnMap::SOURCE     => [Ng911\PinellasColumnMap::class, 'map'],
        HillsboroughColumnMap::SOURCE       => [HillsboroughColumnMap::class, 'map'],
    ];

    /** The national tabular source, which is not NENA-shaped. */
    public const NAD = 'nad';

    /** @return list<string> */
    public static function supported(): array
    {
        return array_merge([self::NAD], array_keys(self::NG911_MAPS));
    }

    public static function supports(string $source): bool
    {
        return in_array(strtolower(trim($source)), self::supported(), true);
    }

    /**
     * The reader and normalizer for a source, or an exception.
     *
     * @return array{reader: AddressSourceReader, normalizer: AddressRowNormalizer, label: string}
     */
    public static function open(string $source, string $file): array
    {
        $key = strtolower(trim($source));

        if ($key === self::NAD) {
            return [
                'reader'     => new NadSourceReader($file),
                'normalizer' => new NadRowNormalizer(),
                'label'      => 'National Address Database (text distribution)',
            ];
        }

        if (isset(self::NG911_MAPS[$key])) {
            $map = call_user_func(self::NG911_MAPS[$key]);

            return [
                'reader'     => new GeoJsonSourceReader($file),
                'normalizer' => new Ng911RowNormalizer($map),
                'label'      => $map->jurisdiction . ' — NENA NG9-1-1 address points',
            ];
        }

        throw new InvalidArgumentException(
            "Unsupported corpus source [{$source}]. Supported: " . implode(', ', self::supported())
        );
    }

    /** The jurisdiction a NENA source describes, or null when not NENA. */
    public static function columnMap(string $source): ?Ng911ColumnMap
    {
        $key = strtolower(trim($source));

        return isset(self::NG911_MAPS[$key]) ? call_user_func(self::NG911_MAPS[$key]) : null;
    }
}
