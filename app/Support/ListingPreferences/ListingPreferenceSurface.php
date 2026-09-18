<?php

namespace App\Support\ListingPreferences;

use App\Models\ListingPreferenceEvent;

/**
 * Where a preference was expressed, and which routes that surface writes
 * through.
 *
 * WHY A SURFACE IS A ROUTE AND NOT A REQUEST FIELD.
 * -------------------------------------------------
 * Phase 2's controller fixed its surface in a constant and said so: "a later
 * surface gets its own route, not a request field." The reason is the same one
 * that keeps `user_id`, `seeker_role` and `subject_key` off the wire — a value
 * the browser supplies is a value the browser can lie about, and the history
 * these events feed is a Fair Housing audit trail. A shopper cannot relabel
 * their own event as having come from somewhere else.
 *
 * So each surface gets its own route group, and the group carries the surface
 * as a ROUTE DEFAULT: server-side, part of the routing table, and never read
 * from the request body.
 *
 * ONE PLACE NAMES THE ROUTES. The shared control renders on several surfaces
 * and must point at the right endpoints without growing a URL-building rule of
 * its own; this class answers that, and an unrecognised surface falls back to
 * the detail routes rather than producing a broken link.
 */
final class ListingPreferenceSurface
{
    /** The Phase 2 surface: a listing's own detail page, and the Phase 3A result cards. */
    public const DETAIL = ListingPreferenceEvent::SURFACE_DETAIL;

    /** Phase 3B: the customer's own Saved / Maybe / Passed management area. */
    public const ACCOUNT = ListingPreferenceEvent::SURFACE_ACCOUNT;

    /** Phase 3B: the Virtual Drive shopper view. */
    public const VIRTUAL_DRIVE = ListingPreferenceEvent::SURFACE_VIRTUAL_DRIVE;

    /**
     * Surface => route-name prefix.
     *
     * Only surfaces with a real write route appear here. `results` and
     * `explore` exist as event constants for surfaces that either share the
     * detail routes today or are not wired at all, and neither may be selected
     * by this class until it has routes of its own.
     */
    private const ROUTES = [
        self::DETAIL        => 'listing-preferences.',
        self::ACCOUNT       => 'listing-preferences.account.',
        self::VIRTUAL_DRIVE => 'listing-preferences.virtual-drive.',
    ];

    /** @return list<string> */
    public static function writable(): array
    {
        return array_keys(self::ROUTES);
    }

    public static function isWritable(?string $surface): bool
    {
        return $surface !== null && array_key_exists($surface, self::ROUTES);
    }

    /**
     * The three write endpoints plus the read endpoint for one surface.
     *
     * @return array{state: string, reasons: string, clear: string, show: string}
     */
    public static function routes(?string $surface): array
    {
        $prefix = self::ROUTES[$surface] ?? self::ROUTES[self::DETAIL];

        return [
            'state'   => route($prefix . 'store'),
            'reasons' => route($prefix . 'reasons'),
            'clear'   => route($prefix . 'destroy'),
            'show'    => route($prefix . 'show'),
        ];
    }

    /**
     * The surface a request is being served on, taken from the ROUTE, never the
     * body.
     *
     * An unrecognised or absent default reads as the detail surface: a missing
     * route default is a wiring mistake, and mislabelling the event is a
     * smaller harm than refusing the customer's click over it.
     */
    public static function fromRoute(?string $fromRouteDefaults): string
    {
        return self::isWritable($fromRouteDefaults) ? (string) $fromRouteDefaults : self::DETAIL;
    }
}
