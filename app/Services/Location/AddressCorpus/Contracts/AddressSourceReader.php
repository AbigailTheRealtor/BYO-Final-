<?php

namespace App\Services\Location\AddressCorpus\Contracts;

use Generator;

/**
 * Streams an authoritative address distribution row by row, from a local file.
 *
 * THE CONTRACT IS AS MUCH ABOUT WHAT A READER MAY NOT DO
 * ------------------------------------------------------
 * An implementation reads a **caller-supplied local path** and nothing else. It
 * may not open a socket, resolve a host, download a distribution or touch a
 * database. That is not a convention to be checked in review — it is what lets a
 * test assert zero queries and zero requests across a full corpus scan and have
 * the result mean something.
 *
 * MEMORY IS PART OF THE CONTRACT TOO
 * ----------------------------------
 * `rows()` is a generator because the files are measured in gigabytes. An
 * implementation that materialises the file and yields from an array satisfies
 * the signature and breaks the design; the national NAD distribution is ~98M
 * rows and a county GeoJSON is hundreds of megabytes of nested JSON.
 *
 * FAIL CLOSED ON SHAPE
 * --------------------
 * `assertSchema()` is the gate. A source whose header or envelope does not carry
 * what the importer maps must stop the run rather than produce a corpus of rows
 * with empty streets — a silent 100% reject rate reads as "this jurisdiction has
 * no addresses" when the truth is "we are reading the wrong columns".
 */
interface AddressSourceReader
{
    /**
     * Every data row in the source, as an array keyed by field name.
     *
     * Readers whose native form is not tabular (GeoJSON) flatten to the same
     * shape, so a normalizer consumes one thing regardless of container format.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator;

    /**
     * Whether this source carries the fields the importer needs.
     *
     * @return array{
     *     ok: bool,
     *     missing_required: list<string>,
     *     missing_required_groups: list<string>,
     *     missing_optional: list<string>,
     *     header: list<string>
     * }
     */
    public function assertSchema(): array;

    /** Short human-readable description of the container, for the report. */
    public function describe(): string;
}
