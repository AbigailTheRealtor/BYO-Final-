<?php

namespace App\Services\Spatial\OvertureExtractV2;

/**
 * One normalized `overture-extract-v2` row — the offline contract a later import (PR 4) loads.
 * Immutable; built only by {@see OvertureV2Normalizer}. {@see toArray()}'s key order IS the wire
 * format of the NDJSON files.
 *
 * LANES ARE NOT INTERCHANGEABLE.
 *   base           one of the 16 import tokens; carries its canonical `category_key`. The corpus.
 *   supplementary  any other token (or none); `category_key` is ALWAYS null — it is not a corpus
 *                  row and has no Location DNA or brand-search category of its own. It exists for
 *                  offline matcher analysis. A `diagnostic` row is matcher-only forever.
 *
 * RESCUE IS DECIDED PER ROW, AND THE DECISION IS WRITTEN DOWN. The role `rescue_candidate` is
 * assigned by TOKEN alone: every selected row whose `taxonomy.primary` a rescue lane claims is a
 * candidate, whatever its name — a Winn-Dixie filed under `shopping` is a candidate for
 * `cvs_shopping` exactly as a CVS is. So the role never means "rescued". The normalizer emits a
 * candidate as `pending`; {@see OvertureV2MatcherCensus} then runs the chain matcher and resolves
 * it to `admitted` (policy `rescued`, with the lane, chain, target category and format the matcher
 * produced) or `refused` (policy `matcher_only`). A written file never carries `pending`, and
 * `rescued` is the ONLY supplementary policy a later import may materialize: it never has to
 * re-derive the verdict, and it can never read a token-level label as an admission.
 *
 * `operating_status` is the raw value; `operating_status_known` separates an explicit `open` from
 * NULL, which is eligible but UNKNOWN and never coerced to open. `source_category` is the raw
 * `taxonomy.primary`; `legacy_category` (`categories.primary`) and `basic_category` are provenance
 * only and never decide anything. The address is internal (dedup / site identity), never a display
 * contract; Overture places carry no separate house-number or street field, so `freeform` is kept
 * verbatim rather than parsed.
 */
final class OvertureV2Record
{
    public const LANE_BASE = 'base';
    public const LANE_SUPPLEMENTARY = 'supplementary';

    public const ROLE_RESCUE_CANDIDATE = 'rescue_candidate';
    public const ROLE_DIAGNOSTIC = 'diagnostic';

    public const POLICY_CORPUS = 'corpus';
    public const POLICY_PENDING_RESCUE_VERDICT = 'pending_rescue_verdict';
    public const POLICY_RESCUED = 'rescued';
    public const POLICY_MATCHER_ONLY = 'matcher_only';

    /** `rescue_verdict`: null on a base row. */
    public const RESCUE_NOT_CANDIDATE = 'not_candidate';
    public const RESCUE_PENDING = 'pending';
    public const RESCUE_ADMITTED = 'admitted';
    public const RESCUE_REFUSED = 'refused';

    public const ELIGIBILITY_BASE = 'base_category_eligible';
    public const ELIGIBILITY_SUPPLEMENTARY = 'supplementary_selector';

    public const SOURCE = 'overture';

    /**
     * @param list<string> $rescueLanes
     * @param array{freeform: ?string, locality: ?string, postcode: ?string, region: ?string, country: ?string} $address
     */
    public function __construct(
        public readonly string $lane,
        public readonly ?string $supplementaryRole,
        public readonly array $rescueLanes,
        public readonly string $materializationPolicy,
        public readonly string $sourceRef,
        public readonly string $sourceRelease,
        public readonly string $extractRecipeVersion,
        public readonly string $taxonomyMapVersion,
        public readonly ?string $sourceCategory,
        public readonly ?string $categoryKey,
        public readonly ?string $legacyCategory,
        public readonly ?string $basicCategory,
        public readonly ?string $name,
        public readonly ?string $brandName,
        public readonly ?string $brandWikidata,
        public readonly float $confidence,
        public readonly ?string $operatingStatus,
        public readonly bool $operatingStatusKnown,
        public readonly float $lon,
        public readonly float $lat,
        public readonly string $geometryType,
        public readonly array $address,
        public readonly string $eligibility,
        public readonly ?string $rescueVerdict = null,
        public readonly ?string $rescuedLane = null,
        public readonly ?string $rescuedChain = null,
        public readonly ?string $rescuedAsCategory = null,
        public readonly ?string $rescuedFormat = null,
    ) {
    }

    /**
     * The census's resolution of a `pending` candidate. Only a pending rescue candidate can be
     * resolved, and only to `admitted` (with every rescue field) or `refused` (with none).
     */
    public function withRescueVerdict(string $verdict, ?string $lane = null, ?string $chain = null, ?string $asCategory = null, ?string $format = null): self
    {
        if ($this->supplementaryRole !== self::ROLE_RESCUE_CANDIDATE || $this->rescueVerdict !== self::RESCUE_PENDING) {
            throw new InvalidOvertureExtractV2("row {$this->sourceRef} is not a pending rescue candidate");
        }
        $admitted = $verdict === self::RESCUE_ADMITTED;
        if (! $admitted && $verdict !== self::RESCUE_REFUSED) {
            throw new InvalidOvertureExtractV2("unknown rescue verdict {$verdict}");
        }
        $fields = [$lane, $chain, $asCategory, $format];
        if ($admitted ? in_array(null, $fields, true) : $fields !== [null, null, null, null]) {
            throw new InvalidOvertureExtractV2("row {$this->sourceRef}: an admitted rescue names its lane, chain, category and format; a refused one names none");
        }

        return new self(
            $this->lane, $this->supplementaryRole, $this->rescueLanes,
            $admitted ? self::POLICY_RESCUED : self::POLICY_MATCHER_ONLY,
            $this->sourceRef, $this->sourceRelease, $this->extractRecipeVersion, $this->taxonomyMapVersion,
            $this->sourceCategory, $this->categoryKey, $this->legacyCategory, $this->basicCategory,
            $this->name, $this->brandName, $this->brandWikidata, $this->confidence, $this->operatingStatus,
            $this->operatingStatusKnown, $this->lon, $this->lat, $this->geometryType, $this->address, $this->eligibility,
            $verdict, $lane, $chain, $asCategory, $format,
        );
    }

    public function isBase(): bool
    {
        return $this->lane === self::LANE_BASE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lane' => $this->lane,
            'supplementary_role' => $this->supplementaryRole,
            'rescue_lanes' => $this->rescueLanes,
            'materialization_policy' => $this->materializationPolicy,
            'rescue_verdict' => $this->rescueVerdict,
            'rescued_lane' => $this->rescuedLane,
            'rescued_chain' => $this->rescuedChain,
            'rescued_as_category' => $this->rescuedAsCategory,
            'rescued_format' => $this->rescuedFormat,
            'source' => self::SOURCE,
            'source_ref' => $this->sourceRef,
            'source_release' => $this->sourceRelease,
            'extract_recipe_version' => $this->extractRecipeVersion,
            'taxonomy_map_version' => $this->taxonomyMapVersion,
            'source_category' => $this->sourceCategory,
            'category_key' => $this->categoryKey,
            'legacy_category' => $this->legacyCategory,
            'basic_category' => $this->basicCategory,
            'name' => $this->name,
            'brand_name' => $this->brandName,
            'brand_wikidata' => $this->brandWikidata,
            'confidence' => $this->confidence,
            'operating_status' => $this->operatingStatus,
            'operating_status_known' => $this->operatingStatusKnown,
            'lon' => $this->lon,
            'lat' => $this->lat,
            'geometry_type' => $this->geometryType,
            'address' => $this->address,
            'eligibility' => $this->eligibility,
        ];
    }
}
