<?php

namespace App\Support\SmartTags;

/**
 * THE reader of the Buyer/Tenant seeker-preference gate. Nothing else
 * interprets it.
 *
 * Separate from {@see SmartTagWiring} on purpose. That class reads the
 * DERIVATION gates — may we read a listing or an MLS feed and write evidence
 * about a property. This reads a CONSUMER gate — may a Buyer or Tenant be shown
 * a feature picker and have their choices stored. One class per decision, for
 * the same reason `SmartTagWiring` itself exists: a gate consulted from several
 * call sites must have exactly one interpretation of "on", or the picker and the
 * write path eventually disagree and the feature is half-enabled.
 *
 * THE ONE ASYMMETRY WORTH READING. `writesEnabled()` gates CREATING, REPLACING
 * and DELETING a selection on a criteria save. `purgeAlwaysAllowed()` is a
 * constant `true` and exists to make the asymmetry explicit rather than implied:
 * cleaning up after a DELETED criteria record is referential hygiene, not a
 * feature, and it must happen whatever the flag says. A row orphaned because the
 * feature was switched off between the write and the delete is still an orphan.
 *
 * FAIL-CLOSED TWICE OVER, like SmartTagWiring. config/smart_tags_wiring.php
 * parses the environment strictly; this class then demands a real boolean `true`
 * from that config, so an absent key, a config file that did not load, or a
 * truthy string all read as OFF.
 */
final class SmartTagSeekerPreferenceGate
{
    /**
     * May the Buyer/Tenant Smart Tag picker render?
     *
     * The same answer the write path uses, deliberately: a control whose submit
     * is ignored is worse than no control, and a write path with no control is
     * an endpoint nobody can see.
     */
    public static function enabled(): bool
    {
        return (SmartTagConfig::wiring()['seeker_preferences_enabled'] ?? null) === true;
    }

    /**
     * May a criteria save create, replace or delete seeker preferences?
     *
     * OFF DOES NOT MEAN "replace with nothing". The caller must SKIP the write
     * entirely — calling the writer with an empty array would delete every
     * selection the customer made while the feature was on, which is data loss
     * caused by a flag rather than by a person.
     */
    public static function writesEnabled(): bool
    {
        return self::enabled();
    }

    /**
     * Deletion cleanup is NOT gated, and never should be.
     *
     * Present as a named constant-returning method rather than as an absent
     * check, so that a future reader asking "why is the purge not behind the
     * flag?" finds the answer here instead of assuming it was an oversight.
     */
    public static function purgeAlwaysAllowed(): bool
    {
        return true;
    }
}
