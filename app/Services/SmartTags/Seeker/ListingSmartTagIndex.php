<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\ListingSmartTagFacts;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagVersion;

/**
 * The seeker's selected tags, answered per candidate listing BEFORE scoring and handed
 * to the scorer as {@see ListingSmartTagFacts}: present, known absent — or neither,
 * which is unknown.
 *
 * THIS IS INPUT CONSTRUCTION, NOT SCORING. BuyerMatchScorer::scoreFacts() and its
 * category rules are pure and read facts only; the one Smart Tag read in matching
 * is here, called by the adapters — BuyerMatchScorer::scoreAll() for a result
 * set, and each single-listing surface (property-detail match context, Match
 * Check) for its one row — so every surface scores from the same facts.
 *
 * WHAT DECIDES EACH ANSWER. The resolved assignment when there is one; otherwise
 * {@see BridgeSmartTagCheckability}: a governed structured Bridge rule for the tag
 * in the listing's context, its source field populated on this row, and stored
 * assignments derived from exactly this row's inputs. Nothing is derived here and
 * no rule is evaluated — a missing row becomes a known miss only when the rule had
 * something to read and the current derivation read it. Anything else is unknown,
 * so missing MLS data is never scored as the listing lacking a feature.
 *
 * AT MOST THREE QUERIES PER 500 CANDIDATES, WHATEVER THE SELECTION: the resolved
 * assignments for the selected keys; and — only when some selected key is
 * unresolved on some candidate — the derivation states and the structured present
 * evidence (a tag with evidence but no assignment was dropped by a conflict).
 *
 * No BYO Seller/Landlord listing is a Stellar candidate — BYO search has no score
 * by design — so no native assignment is read here. A Bridge row is never merged
 * with a BYO listing that happens to share its MLS key: that would be a second,
 * unreviewed identity rule.
 */
final class ListingSmartTagIndex
{
    /**
     * @param array<int, list<string>> $presentByBridgeId     selected keys each listing has
     * @param array<int, list<string>> $knownAbsentByBridgeId selected keys each listing's data checked and did not find
     * @param bool                     $read                  whether any key was asked about (a read happened)
     */
    private function __construct(
        private readonly array $presentByBridgeId,
        private readonly array $knownAbsentByBridgeId,
        private readonly bool $read,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], false);
    }

    /**
     * The candidates' answers for the picks this search SCORES — after the structured-criterion
     * deduplication ({@see BuyerMatchScorer::scoredSeekerTags()}). No scored picks (including
     * matching switched off, which empties the payload's picks) reads nothing.
     *
     * @param iterable<BridgeProperty> $rows
     */
    public static function forCandidates(iterable $rows, BuyerCriteriaPayload $criteria): self
    {
        return self::forBridgeRows($rows, BuyerMatchScorer::scoredSeekerTags($criteria));
    }

    /**
     * @param iterable<BridgeProperty> $rows
     * @param list<string>             $tagKeys the seeker's selection; empty reads nothing
     */
    public static function forBridgeRows(iterable $rows, array $tagKeys): self
    {
        $byId = [];

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);

            if ($id > 0) {
                $byId[$id] = $row;
            }
        }

        $tagKeys = array_values(array_unique($tagKeys));

        if ($byId === [] || $tagKeys === []) {
            return self::empty();
        }

        $deriver = new BridgeStructuredTagDeriver();
        $version = SmartTagVersion::taggerVersion();
        $present = [];
        $knownAbsent = [];

        foreach (array_chunk($byId, 500, true) as $chunk) {
            $ids = array_keys($chunk);

            /** @var array<int, array<string, SmartTagState>> $resolved */
            $resolved = [];
            foreach (SmartTagAssignment::query()
                ->where('listing_type', SmartTagListingType::Bridge->value)
                ->whereIn('listing_id', $ids)
                ->whereIn('tag_key', $tagKeys)
                ->orderBy('listing_id')
                ->orderBy('tag_key')
                ->get(['listing_id', 'tag_key', 'state']) as $assignment) {
                $state = SmartTagState::tryFrom((string) $assignment->state);
                if ($state !== null) {
                    $resolved[(int) $assignment->listing_id][(string) $assignment->tag_key] = $state;
                }
            }

            // Only a listing with an unresolved selected key needs checkability.
            $unresolvedIds = array_values(array_filter(
                $ids,
                static fn (int $id): bool => count($resolved[$id] ?? []) < count($tagKeys),
            ));

            $states = [];
            $dropped = [];

            if ($unresolvedIds !== []) {
                $states = SmartTagDerivationState::query()
                    ->where('listing_type', SmartTagListingType::Bridge->value)
                    ->whereIn('listing_id', $unresolvedIds)
                    ->get(['listing_id', 'context', 'tagger_version', 'structured_inputs_hash'])
                    ->keyBy('listing_id')
                    ->all();

                foreach (SmartTagEvidence::query()
                    ->where('listing_type', SmartTagListingType::Bridge->value)
                    ->whereIn('listing_id', $unresolvedIds)
                    ->whereIn('tag_key', $tagKeys)
                    ->where('source', SmartTagSource::StructuredMls->value)
                    ->where('state', SmartTagState::Present->value)
                    ->get(['listing_id', 'tag_key']) as $evidence) {
                    $id = (int) $evidence->listing_id;
                    if (! isset($resolved[$id][(string) $evidence->tag_key])) {
                        $dropped[$id][(string) $evidence->tag_key] = true;
                    }
                }
            }

            foreach ($chunk as $id => $row) {
                $listingResolved = $resolved[$id] ?? [];
                $record = null;
                $context = null;
                $current = false;

                if (count($listingResolved) < count($tagKeys) && isset($states[$id])) {
                    $record = BridgeRecordAccessor::fromModel($row);
                    $context = $deriver->contextFor($record);
                    $state = $states[$id];

                    // The derivation service's own staleness rule, asked in reverse: were
                    // the stored assignments derived from exactly this row's inputs?
                    $current = $context !== null
                        && $state->tagger_version === $version
                        && $state->context === $context->value
                        && $state->structured_inputs_hash === $deriver->inputsHash($record);
                }

                $answers = BridgeSmartTagCheckability::classify($record, $context, $tagKeys, $listingResolved, $dropped[$id] ?? [], $current);

                foreach ($answers as $key => $answer) {
                    if ($answer === BridgeSmartTagCheckability::PRESENT) {
                        $present[$id][] = $key;
                    } elseif ($answer === BridgeSmartTagCheckability::KNOWN_ABSENT) {
                        $knownAbsent[$id][] = $key;
                    }
                }
            }
        }

        return new self($present, $knownAbsent, true);
    }

    /**
     * One listing's Smart Tag facts for the scorer — or null when nothing was asked about, in
     * which case the scorer has no picks to compare and needs none.
     */
    public function factsFor(BridgeProperty $row): ?ListingSmartTagFacts
    {
        if (! $this->read) {
            return null;
        }

        $id = (int) ($row->id ?? 0);

        return new ListingSmartTagFacts(
            presentKeys:     $this->presentByBridgeId[$id] ?? [],
            knownAbsentKeys: $this->knownAbsentByBridgeId[$id] ?? [],
        );
    }
}
