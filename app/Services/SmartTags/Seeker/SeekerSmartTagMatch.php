<?php

namespace App\Services\SmartTags\Seeker;

use App\Support\SmartTags\SmartTagTaxonomy;

/**
 * How one listing compares with a seeker's selected Smart Tags.
 *
 * Every selected tag is exactly one of: MATCHED (the listing has it), KNOWN ABSENT
 * (the listing's data was checked and it does not), or UNKNOWN (the data could not
 * answer). Only matched and known-absent tags are CHECKABLE; the share is taken over
 * those alone, so a tag nobody could check is neither credit nor a miss.
 *
 * Carries canonical KEYS for the scorer and exposes only human LABELS for
 * presentation — no key, id or weight is meant to reach a page.
 */
final class SeekerSmartTagMatch
{
    /**
     * @param list<string> $selectedKeys
     * @param list<string> $matchedKeys     subset of $selectedKeys, same order
     * @param list<string> $knownAbsentKeys subset of $selectedKeys, same order, disjoint from matched
     */
    public function __construct(
        public readonly array $selectedKeys,
        public readonly array $matchedKeys,
        public readonly array $knownAbsentKeys = [],
    ) {
    }

    public function selectedCount(): int
    {
        return count($this->selectedKeys);
    }

    public function matchedCount(): int
    {
        return count($this->matchedKeys);
    }

    /** Selected tags this listing's data could answer: matched + known absent. */
    public function checkableCount(): int
    {
        return count($this->matchedKeys) + count($this->knownAbsentKeys);
    }

    /** Whether any selected tag could be checked on this listing. */
    public function hasCheckablePicks(): bool
    {
        return $this->checkableCount() > 0;
    }

    /** @return list<string> selected keys nobody could check here, in selection order */
    public function unknownKeys(): array
    {
        return array_values(array_diff($this->selectedKeys, $this->matchedKeys, $this->knownAbsentKeys));
    }

    /**
     * Fraction of the CHECKABLE selection this listing has, 0.0–1.0. Unknown tags are
     * in neither numerator nor denominator; with nothing checkable it is 0.0 and the
     * scorer does not use it (the picks are then not an expressed amenity at all).
     */
    public function share(): float
    {
        $checkable = $this->checkableCount();

        return $checkable === 0 ? 0.0 : $this->matchedCount() / $checkable;
    }

    /** @return list<string> */
    public function matchedLabels(): array
    {
        return self::labels($this->matchedKeys);
    }

    /** @return list<string> known absent only — never an unknown tag */
    public function knownAbsentLabels(): array
    {
        return self::labels($this->knownAbsentKeys);
    }

    /** @return list<string> */
    public function unknownLabels(): array
    {
        return self::labels($this->unknownKeys());
    }

    /**
     * The governed compliance notices that must accompany the known-absent labels
     * (config `compliance.notice` — e.g. the assistance-animal notice on
     * `pets_allowed`). Only tags that declare one contribute; deduplicated.
     *
     * @return list<string>
     */
    public function knownAbsentNotices(): array
    {
        return self::notices($this->knownAbsentKeys);
    }

    /** @return list<string> the notices that must accompany the unknown labels */
    public function unknownNotices(): array
    {
        return self::notices($this->unknownKeys());
    }

    /**
     * @param  list<string> $keys
     * @return list<string>
     */
    private static function labels(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $definition = SmartTagTaxonomy::get($key);

            if ($definition !== null && trim($definition->label) !== '') {
                $out[] = $definition->label;
            }
        }

        return $out;
    }

    /**
     * @param  list<string> $keys
     * @return list<string>
     */
    private static function notices(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $notice = SmartTagTaxonomy::get($key)?->complianceNotice;

            if ($notice !== null && trim($notice) !== '' && ! in_array($notice, $out, true)) {
                $out[] = $notice;
            }
        }

        return $out;
    }
}
