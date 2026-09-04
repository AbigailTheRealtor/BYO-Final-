<?php

namespace App\Services\ListingImport\Mls;

/**
 * The feed's own answer to "may this be shown to the public?", read once and
 * asked everywhere.
 *
 * WHY THIS EXISTS
 * ---------------
 * Stellar publishes per-listing display controls inside the Property record —
 * `IDXParticipationYN`, `InternetEntireListingDisplayYN`,
 * `InternetAddressDisplayYN`, `InternetAutomatedValuationDisplayYN`,
 * `InternetConsumerCommentYN`. Before this class, exactly one of them was read
 * in exactly one place: `StellarPropertyDetailController` checked
 * `IDXParticipationYN` and nothing else, and the import path checked nothing at
 * all. The 2026-09-04 payload audit found `InternetAddressDisplayYN` false on
 * 71 of 1,202 cached records — listings whose address the feed says must not be
 * displayed, and which an import would have published.
 *
 * PRESERVATION AND DISPLAY ARE DIFFERENT PERMISSIONS
 * --------------------------------------------------
 * Nothing here deletes anything. `bridge_properties.raw_json` keeps the whole
 * record exactly as it arrived, and the listing keeps every fact it imported.
 * This class answers only whether a given class of value may be RENDERED. A
 * restricted address is still stored, still drives the coordinate ladder, still
 * matches — it simply is not printed.
 *
 * FAIL CLOSED, WITH ONE DELIBERATE EXCEPTION
 * ------------------------------------------
 * An explicit `false` always means no. An explicit `true` means yes. A missing
 * or unparseable flag means YES for the address and the listing, and that is a
 * deliberate choice rather than an oversight: these columns are near-universally
 * populated in this feed (1,202/1,202 for both), so absence signals "this record
 * predates the column" rather than "permission was withheld", and treating
 * absence as a refusal would blank the address on every listing the day Stellar
 * renames a column. The refusal that matters — an explicit false — is honoured
 * absolutely and cannot be overridden by any caller.
 *
 * Read through the named methods, never by comparing raw values: an
 * unrecognised value must resolve in one place, and that place is here.
 */
final class MlsDisplayPermissions
{
    private function __construct(
        private readonly ?bool $idxParticipation,
        private readonly ?bool $entireListingDisplay,
        private readonly ?bool $addressDisplay,
        private readonly ?bool $automatedValuationDisplay,
        private readonly ?bool $consumerCommentDisplay,
    ) {}

    /**
     * Read the display controls out of a raw Bridge/RESO property record.
     *
     * @param array<string,mixed> $raw
     */
    public static function fromRecord(array $raw): self
    {
        return new self(
            idxParticipation:          self::flag($raw, 'IDXParticipationYN'),
            entireListingDisplay:      self::flag($raw, 'InternetEntireListingDisplayYN'),
            addressDisplay:            self::flag($raw, 'InternetAddressDisplayYN'),
            automatedValuationDisplay: self::flag($raw, 'InternetAutomatedValuationDisplayYN'),
            consumerCommentDisplay:    self::flag($raw, 'InternetConsumerCommentYN'),
        );
    }

    /**
     * Rebuild from the compact array persisted alongside an imported listing.
     *
     * @param array<string,mixed> $stored
     */
    public static function fromStored(mixed $stored): self
    {
        $stored = is_array($stored) ? $stored : [];

        $read = static function (string $key) use ($stored): ?bool {
            if (! array_key_exists($key, $stored) || $stored[$key] === null) {
                return null;
            }

            return filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        return new self(
            idxParticipation:          $read('idx_participation'),
            entireListingDisplay:      $read('entire_listing_display'),
            addressDisplay:            $read('address_display'),
            automatedValuationDisplay: $read('automated_valuation_display'),
            consumerCommentDisplay:    $read('consumer_comment_display'),
        );
    }

    /**
     * A permission set that permits nothing.
     *
     * Used where a record cannot be read at all. "We could not determine the
     * feed's permissions" is not the same statement as "the feed set no
     * permissions", and the two must not resolve the same way.
     */
    public static function denyAll(): self
    {
        return new self(false, false, false, false, false);
    }

    /**
     * The shape persisted with the listing. Deliberately snake_case and
     * boolean-or-null so it round-trips through JSON meta unchanged.
     *
     * @return array<string,bool|null>
     */
    public function toArray(): array
    {
        return [
            'idx_participation'           => $this->idxParticipation,
            'entire_listing_display'      => $this->entireListingDisplay,
            'address_display'             => $this->addressDisplay,
            'automated_valuation_display' => $this->automatedValuationDisplay,
            'consumer_comment_display'    => $this->consumerCommentDisplay,
        ];
    }

    /**
     * May any MLS-sourced content be shown publicly for this listing?
     *
     * Two independent refusals: the listing's broker has withdrawn it from IDX,
     * or the feed says the entire listing may not be displayed on the internet.
     * Either one alone is decisive.
     */
    public function listingDisplayable(): bool
    {
        return $this->idxParticipation !== false
            && $this->entireListingDisplay !== false;
    }

    /**
     * May the street address be shown?
     *
     * `InternetAddressDisplayYN = false` is the MLS instructing us to publish
     * the listing without its address — the common "no address on the internet"
     * seller instruction. The listing still renders; the street line, unit,
     * and anything that reconstructs them do not.
     */
    public function addressDisplayable(): bool
    {
        return $this->listingDisplayable() && $this->addressDisplay !== false;
    }

    /**
     * May automated valuation / price-derived estimates be shown?
     *
     * Not currently consumed by any surface — recorded so that a future
     * valuation widget asks the feed rather than assuming.
     */
    public function automatedValuationDisplayable(): bool
    {
        return $this->listingDisplayable() && $this->automatedValuationDisplay !== false;
    }

    /**
     * May third-party consumer comments be shown against this listing?
     *
     * Also not yet consumed, and false on 531 of 1,201 records — which is
     * precisely why it is captured now rather than discovered later.
     */
    public function consumerCommentDisplayable(): bool
    {
        return $this->listingDisplayable() && $this->consumerCommentDisplay !== false;
    }

    /**
     * A short, human-readable reason a surface is withholding something, or
     * null when nothing is withheld. Shown to the listing's own owner so an
     * absent address reads as a rule rather than a bug.
     */
    public function addressWithheldReason(): ?string
    {
        if (! $this->listingDisplayable()) {
            return 'The MLS does not permit this listing to be displayed publicly.';
        }

        if ($this->addressDisplay === false) {
            return 'The MLS does not permit this address to be displayed publicly.';
        }

        return null;
    }

    /**
     * One flag, read defensively.
     *
     * `filter_var(FILTER_VALIDATE_BOOLEAN)` does not recognise the bare "Y"/"N"
     * spellings that RESO feeds use for YN columns — both come back as null,
     * which this class reads as "no statement" and therefore as permission. On a
     * gate whose whole job is to catch a refusal, a refusal that reads as
     * silence is the one failure mode worth writing extra code for, so Y and N
     * are handled explicitly before the filter sees them.
     *
     * @param array<string,mixed> $raw
     */
    private static function flag(array $raw, string $key): ?bool
    {
        if (! array_key_exists($key, $raw) || $raw[$key] === null || $raw[$key] === '') {
            return null;
        }

        $value = $raw[$key];

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            if ($normalised === 'y') {
                return true;
            }

            if ($normalised === 'n') {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
