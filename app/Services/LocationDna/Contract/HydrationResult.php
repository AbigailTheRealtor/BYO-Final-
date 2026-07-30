<?php

namespace App\Services\LocationDna\Contract;

/**
 * HydrationResult — an explicit result object rather than a nullable document.
 *
 * G1c contract core. INERT.
 *
 * WHY A RESULT OBJECT AND NOT AN EXCEPTION-ONLY DESIGN
 * ---------------------------------------------------
 * Malformed and unsupported-version records must be *surfaced* (§5.4 S3, §5.5) but they are
 * also ordinary, expected states for legacy data — a reader has to be able to render "we
 * cannot interpret this" without an exception unwinding a page render. So the hydrator
 * returns a result, and the caller decides. Where a caller wants the strict form,
 * {@see self::documentOrFail()} throws the same precise domain exceptions.
 *
 * QUARANTINE
 * ----------
 * A malformed result retains the raw input verbatim. G1b F-G1B-6 established that the raw
 * bytes survive today and that the real damage is a silent blob/mirror divergence, so
 * keeping the original available is what lets a caller avoid rewriting it.
 */
final class HydrationResult
{
    private function __construct(
        public readonly HydrationOutcome $outcome,
        private readonly ?LocationDnaDocument $document,
        public readonly ?string $reason,
        private readonly mixed $rawInput,
        public readonly ?int $foundSchemaVersion,
    ) {
    }

    public static function hydrated(LocationDnaDocument $document): self
    {
        return new self(HydrationOutcome::Hydrated, $document, null, null, $document->schemaVersion());
    }

    public static function absent(): self
    {
        return new self(HydrationOutcome::Absent, null, null, null, null);
    }

    public static function malformed(string $reason, mixed $rawInput): self
    {
        return new self(HydrationOutcome::Malformed, null, $reason, $rawInput, null);
    }

    public static function unsupportedVersion(int $foundVersion): self
    {
        return new self(
            HydrationOutcome::UnsupportedVersion,
            null,
            "schema_version {$foundVersion} is newer than the supported version "
            .LocationDnaDocument::CURRENT_SCHEMA_VERSION.'.',
            null,
            $foundVersion,
        );
    }

    public function isHydrated(): bool
    {
        return $this->outcome === HydrationOutcome::Hydrated;
    }

    public function isAbsent(): bool
    {
        return $this->outcome === HydrationOutcome::Absent;
    }

    public function isMalformed(): bool
    {
        return $this->outcome === HydrationOutcome::Malformed;
    }

    public function isUnsupportedVersion(): bool
    {
        return $this->outcome === HydrationOutcome::UnsupportedVersion;
    }

    /** The document, or null for any non-hydrated outcome. */
    public function document(): ?LocationDnaDocument
    {
        return $this->document;
    }

    /** The quarantined raw input for a malformed result, so a caller can avoid overwriting it. */
    public function rawInput(): mixed
    {
        return $this->rawInput;
    }

    /**
     * The document, or the precise domain exception for the outcome.
     *
     * @throws MalformedDocumentException
     * @throws UnsupportedSchemaVersionException
     * @throws LocationDnaContractException on an absent document
     */
    public function documentOrFail(): LocationDnaDocument
    {
        return match ($this->outcome) {
            HydrationOutcome::Hydrated  => $this->document,
            HydrationOutcome::Malformed => throw new MalformedDocumentException((string) $this->reason),
            HydrationOutcome::UnsupportedVersion => throw new UnsupportedSchemaVersionException(
                (int) $this->foundSchemaVersion,
                LocationDnaDocument::CURRENT_SCHEMA_VERSION,
            ),
            HydrationOutcome::Absent => throw new LocationDnaContractException(
                ContractViolation::MalformedDocument,
                'No Location DNA document is present for this record.',
            ),
        };
    }
}
