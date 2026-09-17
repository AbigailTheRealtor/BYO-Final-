<?php

namespace App\Services\SmartTags;

use App\Support\SmartTags\SmartTagListingRef;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One structured `smart_tags` log line per lifecycle decision.
 *
 * Shaped after CoordinateProviderTelemetry and ExploreProviderTelemetry: a fixed
 * key set, an outcome from a closed list, and identifiers rather than content.
 *
 * WHAT MAY NEVER BE LOGGED, and why it cannot be by construction:
 *
 *   • MLS PublicRemarks — never read by anything here, so there is nothing to log.
 *   • The native listing description — the caller hands this class a BOOLEAN
 *     (`description`, did the parser run) and never the text. There is no
 *     parameter that could carry prose, no snippet, no matched phrase and no
 *     length. The description HASH is deliberately absent too: it is derived from
 *     the text and buys nothing an operator can act on.
 *   • Provider text of any kind, and anything resembling PII.
 *
 * A failure records the exception CLASS, never its message: a Smart Tag fault is
 * local and carries no credential, but a message can quote a listing value and
 * there is no reason to take that risk for a class name that already identifies
 * the fault.
 */
final class SmartTagTelemetry
{
    public const CHANNEL = 'smart_tags';

    // Outcomes. The list is closed; a caller cannot invent one.
    public const TAGGED = 'tagged';
    public const SKIPPED_UNCHANGED = 'skipped_unchanged';
    public const NO_CONTEXT = 'no_context';
    public const NOT_OFFER_LISTING = 'not_offer_listing';
    public const ARCHIVED = 'archived';
    public const FAILED = 'failed';
    public const CONFLICT = 'conflict';
    public const REMARKS_BLOCKED = 'remarks_blocked';
    public const DESCRIPTION_SUPPRESSED = 'description_suppressed';
    public const DISABLED = 'disabled';
    public const PURGED = 'purged';

    // Entry points, so a line says which surface produced it.
    public const ENTRY_SELLER_PUBLISH = 'seller_publish';
    public const ENTRY_LANDLORD_PUBLISH = 'landlord_publish';
    public const ENTRY_QUICK_IMPORT_PUBLISH = 'quick_import_publish';
    public const ENTRY_BRIDGE_LOOKUP = 'bridge_lookup';
    public const ENTRY_BRIDGE_CLI = 'bridge_cli';
    public const ENTRY_BACKFILL = 'backfill';
    public const ENTRY_PURGE = 'purge';

    /**
     * One derivation decision.
     *
     * @param array<string, mixed> $extra already-safe scalars only
     */
    public static function record(
        string $outcome,
        string $entryPoint,
        ?SmartTagListingRef $listing,
        ?DerivationOutcome $result = null,
        ?float $durationMs = null,
        ?Throwable $error = null,
        array $extra = [],
    ): void {
        $resolution = $result?->resolution;

        $line = [
            'outcome'         => $outcome,
            'entry_point'     => $entryPoint,
            'listing_type'    => $listing?->type->value,
            'listing_id'      => $listing?->id,
            'context'         => $result?->context?->value,
            'structured'      => $result?->structuredDerived,
            'description'     => $result?->descriptionParsed,
            'context_changed' => $extra['context_changed'] ?? null,
            'tags'            => $resolution === null ? null : count($resolution->assignments),
            'conflicts'       => $resolution === null ? null : self::conflictCount($resolution),
            'tagger_version'  => isset($extra['tagger_version']) ? substr((string) $extra['tagger_version'], 0, 12) : null,
            'duration_ms'     => $durationMs === null ? null : round($durationMs, 1),
            'error_class'     => $error === null ? null : get_class($error),
        ];

        foreach ($extra as $key => $value) {
            // Only scalars a caller has already decided are safe. Never prose:
            // every value that reaches here is a count, an id, a flag or a name.
            if (! array_key_exists($key, $line) && (is_scalar($value) || $value === null)) {
                $line[$key] = $value;
            }
        }

        $level = $outcome === self::FAILED ? 'warning' : 'info';

        Log::log($level, self::CHANNEL, $line);
    }

    /**
     * A batch summary. Used by the backfill instead of a line per listing, which
     * would be millions of `info` records for one run.
     *
     * @param array<string, int> $counts
     * @param array<string, mixed> $extra
     */
    public static function batch(string $entryPoint, array $counts, array $extra = []): void
    {
        Log::info(self::CHANNEL, array_merge([
            'outcome'     => 'batch',
            'entry_point' => $entryPoint,
        ], $counts, $extra));
    }

    private static function conflictCount(SmartTagResolution $resolution): int
    {
        $conflicts = 0;
        foreach ($resolution->assignments as $assignment) {
            if ($assignment->hasConflict) {
                $conflicts++;
            }
        }

        return $conflicts + count($resolution->droppedForConflict);
    }
}
