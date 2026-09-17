<?php

namespace App\Support\OfferListing;

/**
 * Buyer / Tenant public detail pages — the one gate between a consumer's
 * private qualification data and the open internet.
 *
 * `/offer-listing/buyer/view/{id}` and `/offer-listing/tenant/view/{id}` carry
 * no auth middleware. Everything their Blade files render, they render to
 * anyone with the URL. A Buyer or Tenant listing is a CONSUMER's listing, and
 * what belongs on it is what that person is LOOKING FOR — not what qualifies
 * them, not where they live now, not who lives with them, and not how to reach
 * them.
 *
 * `config/offer_listing_private_criteria.php` is the SSOT and states the
 * reasoning per key. This class is its ONLY reader; a test asserts that.
 *
 * THE GATE IS AT THE HAND-OFF, NOT AT THE CALL SITES. Both controllers pass a
 * single `$meta` array to their view, and every meta read on both pages closes
 * over that array — the `$str` / `$arr` / `$val` helpers, each section's own
 * `has…` emptiness guard, the hero, the badges, the sidebar summary and the
 * tenant page's "Additional Information" loop. Redacting the array once
 * therefore covers all of them, including rows added later by someone who has
 * never read this file. Gating ~30 individual `$row(...)` calls instead would
 * have left the next added row unprotected by default, which is the failure
 * this design exists to remove.
 *
 * A REDACTED KEY IS REMOVED, NOT BLANKED. `$str()` already returns '' for a
 * missing key and `$arr()` returns [], so every reader behaves and every
 * section's emptiness guard collapses its heading; and the tenant overflow loop
 * iterates `$meta` itself, which a blanked-but-present key would still enter.
 *
 * THE OWNER IS THE ONLY EXCEPTION. Ownership is `auth()->check()` first and
 * then an integer comparison against the listing's `user_id` — a guest's null
 * id and a null `user_id` both cast to 0 and would otherwise "match".
 *
 * This class never writes. Stored values, save paths, the owner's own view, the
 * edit wizards and every matcher are untouched; this decides display only.
 */
final class CriteriaPrivacyPolicy
{
    /** Roles this policy governs. A role it does not know redacts nothing. */
    public const ROLES = ['buyer', 'tenant'];

    /** @var array<string,mixed>|null Lazily loaded fallback when no container is bound. */
    private static ?array $fileConfig = null;

    /**
     * Owner-only meta keys for a role, in config order.
     *
     * An unknown role returns [] rather than throwing: this is display code on a
     * public page, and the caller below fails OPEN on roles only because an
     * unknown role also means no redaction list was ever written for it. Callers
     * pass a literal, and `redactForViewer()` is the method that matters.
     *
     * @return string[]
     */
    public static function privateKeys(string $role): array
    {
        $keys = self::conf()[$role] ?? [];

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($k) => is_string($k) ? $k : null, $keys)
        ));
    }

    /**
     * Is this meta key owner-only for this role?
     *
     * Per-role by design. `minimum_annual_net_income` is the tenant's own income
     * and the buyer's required PROPERTY yield: the same key, two unrelated facts.
     * The lists are never derived from one another.
     */
    public static function isPrivate(string $role, string $key): bool
    {
        return in_array($key, self::privateKeys($role), true);
    }

    /**
     * Remove every owner-only key from a listing's meta array for this viewer.
     *
     * The owner receives the array unchanged. Everyone else — every guest and
     * every signed-in non-owner alike — receives it without the private keys.
     *
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    public static function redactForViewer(string $role, array $meta, bool $viewerIsOwner): array
    {
        if ($viewerIsOwner) {
            return $meta;
        }

        foreach (self::privateKeys($role) as $key) {
            unset($meta[$key]);
        }

        return $meta;
    }

    /**
     * Is this viewer the listing's owner?
     *
     * `auth()->check()` is tested FIRST on purpose: a guest's null id and a null
     * `user_id` would both cast to 0 and compare equal, handing a guest the
     * owner's view of any listing whose `user_id` is null.
     */
    public static function viewerIsOwner(?int $viewerId, $ownerId): bool
    {
        if ($viewerId === null || $ownerId === null || $ownerId === '') {
            return false;
        }

        return (int) $viewerId === (int) $ownerId;
    }

    /**
     * Config, container-first with a file fallback.
     *
     * The fallback is not defensive decoration. This class is reachable from
     * plain unit tests that extend PHPUnit's TestCase with no application
     * booted, where `config()` raises; and the symptom of that would not be a
     * missing key but an exception several frames away from anything that names
     * privacy. Mirrors LandlordScreeningPolicy::conf() for the same reason.
     *
     * @return array<string,mixed>
     */
    private static function conf(): array
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config('offer_listing_private_criteria');
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return $fromContainer;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }

        if (self::$fileConfig === null) {
            $path = __DIR__ . '/../../../config/offer_listing_private_criteria.php';
            $loaded = is_file($path) ? require $path : [];
            self::$fileConfig = is_array($loaded) ? $loaded : [];
        }

        return self::$fileConfig;
    }
}
