<?php

namespace App\Support\SmartTags;

/**
 * THE reader of the Phase 2 activation gates. Nothing else interprets them.
 *
 * Two independent gates, and neither is redundant:
 *
 *   enabled         master. Off means no Smart Tag row is written or deleted by
 *                   any automatic path, for any listing type.
 *   bridge_enabled  ADDITIONAL, for `bridge` listings only. Never a replacement
 *                   for the master gate — both must agree.
 *
 * WHY THE READER IS ITS OWN CLASS. The same reason
 * {@see \App\Support\Google\GoogleBrowserMaps} and
 * {@see \App\Support\HireAgent\HireAgentDetailRedesign} are: a gate consulted
 * from several call sites must have exactly one interpretation of what "on"
 * means, or two call sites eventually disagree and the feature is half-enabled.
 *
 * FAIL-CLOSED TWICE OVER. config/smart_tags_wiring.php already parses the
 * environment strictly (`true`/`1`/`on`/`yes` only, everything else off). This
 * class then requires a real boolean `true` from that config, so a config file
 * that did not load, a key that is absent, or a value some future edit leaves as
 * a truthy string all read as OFF. A missing gate is a closed gate.
 */
final class SmartTagWiring
{
    /**
     * The master gate alone. NOT a gate on its own for a Bridge listing — use
     * {@see self::enabledFor()}, which is what every call site asks.
     */
    public static function enabled(): bool
    {
        return self::flag('enabled');
    }

    /** The additional Bridge gate alone. Meaningless without the master gate. */
    public static function bridgeEnabled(): bool
    {
        return self::flag('bridge_enabled');
    }

    /**
     * May this listing type be derived right now?
     *
     * Native listings need the master gate. A Bridge listing needs both, in that
     * order — the master gate is the one that stops everything.
     */
    public static function enabledFor(SmartTagListingType $type): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return $type === SmartTagListingType::Bridge
            ? self::bridgeEnabled()
            : true;
    }

    /**
     * May the unattended Bridge catch-up run?
     *
     * Its own switch AND both derivation gates. Asked by the scheduler before it
     * registers the entry, and by `smart-tags:derive --scheduled` before it writes.
     */
    public static function bridgeCatchUpScheduled(): bool
    {
        return self::enabledFor(SmartTagListingType::Bridge) && self::flag('bridge_catch_up_schedule_enabled');
    }

    private static function flag(string $key): bool
    {
        return (SmartTagConfig::wiring()[$key] ?? null) === true;
    }
}
