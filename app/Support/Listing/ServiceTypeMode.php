<?php

namespace App\Support\Listing;

/**
 * The two service types a Hire Agent listing may have — and the rule that anything else
 * is not one of them.
 *
 * WHY A CLASS FOR TWO STRINGS
 * ---------------------------
 * Because the views did not compare against two strings. They compared against ONE:
 *
 *     @if ($service_type === 'full_service')
 *         … the full-service wizard …
 *     @else
 *         … the limited-service-shaped wizard …
 *     @endif
 *
 * That `@else` is not "limited service". It is "everything that is not full service",
 * and NULL is in it. Create Offer Listing has no service-type concept at all and never
 * writes the meta key, so every Offer Listing row reads back `service_type = null` —
 * and when the cross-product bug handed one of those rows to the Hire wizard,
 * `loadDraft()` assigned that null straight over the component's `'full_service'`
 * default and the `@else` branch rendered. The result was a Seller listing displayed in
 * a five-tab limited-service-shaped wizard whose tab panes were, in turn, gated on
 * `=== 'limited_service'` and so rendered nothing at all.
 *
 * The fix is to make the third case say its own name. `isRecognised()` is the single
 * rule; a view asks it instead of relying on the absence of a match.
 *
 * This is DEFENCE IN DEPTH, not the primary fix. The workflow guard stops a wrong-product
 * row long before render. This stops a malformed service_type — from any cause, present
 * or future — from silently selecting a different workflow's UI.
 */
final class ServiceTypeMode
{
    public const FULL    = 'full_service';
    public const LIMITED = 'limited_service';

    /** @var string[] */
    public const ALL = [self::FULL, self::LIMITED];

    /**
     * Is this a service type the wizard actually has a UI for?
     *
     * NULL, '', and any unrecognised value all answer false. Callers must fail closed on
     * false — never fall back to a branch that happens to be adjacent.
     */
    public static function isRecognised($value): bool
    {
        return is_string($value) && in_array($value, self::ALL, true);
    }

    public static function isFull($value): bool
    {
        return $value === self::FULL;
    }

    public static function isLimited($value): bool
    {
        return $value === self::LIMITED;
    }
}
