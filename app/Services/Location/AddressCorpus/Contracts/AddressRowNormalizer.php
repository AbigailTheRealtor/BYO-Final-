<?php

namespace App\Services\Location\AddressCorpus\Contracts;

use App\Services\Location\AddressCorpus\CorpusAddressRecord;

/**
 * One raw source row → an accepted {@see CorpusAddressRecord}, or a named reject.
 *
 * PURE, AND THAT IS THE POINT
 * ---------------------------
 * No database, no container, no clock, no filesystem, no network. That is what
 * lets the dry run stream a national file through an implementation and lets
 * every edge case be a unit test with a literal array. It is also what makes
 * "this command cannot write" a structural fact rather than a promise: there is
 * no persistence code on this path to review.
 *
 * EVERY REJECT HAS A NAME
 * -----------------------
 * `normalize()` returns a reason from {@see \App\Services\Location\AddressCorpus\CorpusRejectReason}
 * for every row it does not accept. The reasons are shared across sources on
 * purpose — an operator comparing two jurisdictions needs one vocabulary, not
 * two that happen to look alike.
 *
 * ONE NORMALIZATION, MANY SOURCES
 * -------------------------------
 * Implementations differ in which columns they read. They must not differ in how
 * an address becomes a string: every one builds a
 * {@see \App\Services\Location\Coordinates\PropertyAddress} and lets
 * `coordinateLookupLine()` do the normalizing. A second street normalizer would
 * mean a corpus that cannot be matched against the addresses people type.
 */
interface AddressRowNormalizer
{
    /**
     * @param  array<string, mixed> $row
     * @return array{record: CorpusAddressRecord|null, reject: string|null}
     */
    public function normalize(array $row, string $stateFips): array;

    /**
     * Whether this row belongs to the requested jurisdiction.
     *
     * Checked before `normalize()` so an out-of-scope row costs nothing and is
     * not counted among the in-state rejects — a national file scanned for
     * Florida should report Georgia rows as absent, not as failures.
     *
     * @param array<string, mixed> $row
     */
    public function matchesState(array $row, string $stateFips): bool;

    /** The source identity these records carry, e.g. 'nad' or 'pinellas'. */
    public function source(): string;
}
