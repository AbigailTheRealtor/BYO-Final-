<?php

namespace App\Services\Spatial\OvertureExtractV2;

/**
 * Why a bounding-box row is in neither lane. Every row resolves to exactly one outcome — base,
 * supplementary, or ONE of these — checked in this order, so each row is counted once.
 *
 * `outside_bounding_box` here is the normalizer's re-check of the point; rows the SQL recipe left
 * outside the box are counted separately, from the release row count, as `outside_bbox_sql`.
 * The two taxonomy reasons are the supplementary candidates the diagnostic selector did NOT take,
 * i.e. "supplementary rule not satisfied", split by whether the token was null or merely
 * unlisted. `duplicate_source_id` comes second and removes EVERY row of a repeated id, whatever
 * the input order and whatever each copy would otherwise have been.
 */
final class OvertureV2Rejection
{
    public const MISSING_SOURCE_ID = 'missing_source_id';
    public const MALFORMED_FIELD = 'malformed_field';
    public const MALFORMED_GEOMETRY = 'malformed_geometry';
    public const OUTSIDE_BOUNDING_BOX = 'outside_bounding_box';
    public const MALFORMED_CONFIDENCE = 'malformed_confidence';
    public const CONFIDENCE_BELOW_FLOOR = 'confidence_below_floor';
    public const STATUS_PERMANENTLY_CLOSED = 'status_permanently_closed';
    public const STATUS_UNRECOGNISED = 'status_unrecognised';
    public const TAXONOMY_NULL = 'taxonomy_null_not_supplementary';
    public const TAXONOMY_NOT_ALLOWLISTED = 'taxonomy_not_allowlisted_not_supplementary';
    public const DUPLICATE_SOURCE_ID = 'duplicate_source_id';

    /** @return list<string> in check order */
    public static function all(): array
    {
        return [
            self::MISSING_SOURCE_ID, self::DUPLICATE_SOURCE_ID, self::MALFORMED_FIELD, self::MALFORMED_GEOMETRY,
            self::OUTSIDE_BOUNDING_BOX, self::MALFORMED_CONFIDENCE, self::CONFIDENCE_BELOW_FLOOR,
            self::STATUS_PERMANENTLY_CLOSED, self::STATUS_UNRECOGNISED, self::TAXONOMY_NULL, self::TAXONOMY_NOT_ALLOWLISTED,
        ];
    }
}
