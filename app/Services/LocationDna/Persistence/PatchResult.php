<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\LocationDnaDocument;

/**
 * G1f-1 — the explicit result of a persistence attempt.
 *
 * Returned rather than thrown for every expected outcome, so a caller cannot accidentally treat a
 * refusal as a success by omitting a catch. §6.3's `unavailable` exists precisely so failure has
 * somewhere to go other than data loss, and this type is that somewhere.
 *
 * `errorCode` values are drawn from the §6.3 closed set. G1f-1 uses `capability_denied`,
 * `invalid_value`, `empty_set_rejected`, `unknown_schema_version`, `provenance_forbidden` and
 * `unavailable`. `revision_conflict` is reserved and unused until token transport exists (§11.3).
 */
final class PatchResult
{
    private function __construct(
        public readonly PersistenceOutcome $outcome,
        public readonly ?LocationDnaDocument $document,
        public readonly ?string $revisionToken,
        public readonly ?string $errorCode,
        public readonly ?string $reason,
    ) {
    }

    public static function changed(LocationDnaDocument $document, string $revisionToken): self
    {
        return new self(PersistenceOutcome::Changed, $document, $revisionToken, null, null);
    }

    /**
     * Nothing was written.
     *
     * The document and token are supplied where they are known — a no-op still has a current
     * canonical meaning — and are null when there was no document to read.
     */
    public static function noOp(?LocationDnaDocument $document = null, ?string $revisionToken = null): self
    {
        return new self(PersistenceOutcome::NoOp, $document, $revisionToken, null, null);
    }

    public static function rejected(string $errorCode, string $reason): self
    {
        return new self(PersistenceOutcome::Rejected, null, null, $errorCode, $reason);
    }

    public function isChanged(): bool
    {
        return $this->outcome === PersistenceOutcome::Changed;
    }

    public function isNoOp(): bool
    {
        return $this->outcome === PersistenceOutcome::NoOp;
    }

    public function isRejected(): bool
    {
        return $this->outcome === PersistenceOutcome::Rejected;
    }

    /** True when something was persisted. The only condition under which storage changed. */
    public function wrote(): bool
    {
        return $this->outcome === PersistenceOutcome::Changed;
    }
}
