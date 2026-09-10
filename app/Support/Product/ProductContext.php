<?php

namespace App\Support\Product;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Which product experience this deployment is serving.
 *
 * ONE authoritative answer, read by everything: the route gate
 * (EnsureProductSurface), the navigation and dashboard partials (through the
 * @bidyouroffer Blade directive) and the deploy-time flag contract. There are no
 * raw request()->getHost() comparisons anywhere else, and there must not be —
 * scattering the question is how two surfaces come to disagree about which
 * product they are part of.
 *
 * RESOLUTION IS STATELESS ON PURPOSE. There is no memo, no static cache and no
 * setter. Every call re-reads configuration, which makes the answer deterministic
 * for a given configuration, impossible to leak between tests, and impossible for
 * one request to change for the next.
 *
 * Order:
 *   1. config('products.active') — APP_PRODUCT. Wins absolutely; the host map is
 *      not consulted. An unrecognised value THROWS (see config/products.php).
 *   2. config('products.hosts')  — exact host match, only when (1) is unset.
 *   3. config('products.default') — 'combined', the platform as it is today.
 *
 * PRODUCT VISIBILITY IS NOT AUTHORIZATION. This class answers "does this
 * deployment serve that surface"; auth, verified, owner checks and agentAuth
 * answer "may this user reach it". Both layers still run, and neither substitutes
 * for the other.
 */
final class ProductContext
{
    public const COMBINED     = 'combined';
    public const BIDYOURAGENT = 'bidyouragent';

    /**
     * The product this deployment is serving.
     */
    public static function current(): string
    {
        $active = config('products.active');

        if (is_string($active) && trim($active) !== '') {
            return self::validate(strtolower(trim($active)), 'APP_PRODUCT');
        }

        $fromHost = self::fromHost();

        if ($fromHost !== null) {
            return $fromHost;
        }

        return self::validate(
            strtolower(trim((string) config('products.default', self::COMBINED))),
            'products.default'
        );
    }

    public static function is(string $product): bool
    {
        return self::current() === $product;
    }

    public static function isBidYourAgent(): bool
    {
        return self::is(self::BIDYOURAGENT);
    }

    /**
     * Does this deployment show BidYourOffer surfaces at all?
     *
     * The one question navigation and dashboards ask. Phrased as a capability
     * rather than as a product name so a third product later does not require
     * every Blade condition to be revisited.
     */
    public static function servesBidYourOffer(): bool
    {
        return ! self::isBidYourAgent();
    }

    /**
     * May a request for this registered route be served by this deployment?
     *
     * `$method` is the REQUEST method; `$uri` is the route's registered URI as
     * Laravel stores it (no leading slash, '/' for the root).
     */
    public static function allowsRoute(string $method, string $uri): bool
    {
        if (self::servesBidYourOffer()) {
            return true;
        }

        return ProductSurfaceCatalog::dispositionFor($method, $uri)
            !== ProductSurfaceCatalog::BIDYOUROFFER_ONLY;
    }

    /**
     * Convenience for Blade and controllers: may a route NAME be linked to?
     *
     * Nav conditions resolve through the SAME catalog the gate uses, so a link
     * cannot survive a change that starts refusing the route behind it.
     * An unknown name is treated as linkable — a name that resolves to no route
     * is a broken link, which is not this class's problem to hide.
     */
    public static function allowsRouteName(string $name, string $method = 'GET'): bool
    {
        $route = app('router')->getRoutes()->getByName($name);

        if ($route === null) {
            return true;
        }

        return self::allowsRoute($method, $route->uri());
    }

    /**
     * Host map lookup. Returns null when there is no request, no map, or no
     * exact match — every one of which means "this mechanism has no opinion".
     */
    private static function fromHost(): ?string
    {
        $map = config('products.hosts');

        if (! is_array($map) || $map === []) {
            return null;
        }

        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        if (! $request instanceof Request) {
            return null;
        }

        $host = strtolower(trim((string) $request->getHost()));

        foreach ($map as $mapped => $product) {
            if (strtolower(trim((string) $mapped)) === $host && $host !== '') {
                return self::validate(
                    strtolower(trim((string) $product)),
                    "products.hosts.{$mapped}"
                );
            }
        }

        return null;
    }

    private static function validate(string $product, string $source): string
    {
        $known = config('products.products');
        $known = is_array($known) && $known !== [] ? $known : [self::COMBINED, self::BIDYOURAGENT];

        if (! in_array($product, $known, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown product "%s" from %s. Known products: %s. A product name is not '
                . 'guessed: an unrecognised value would resolve to the combined platform and '
                . 'look exactly like success on a BidYourAgent domain.',
                $product,
                $source,
                implode(', ', $known)
            ));
        }

        return $product;
    }
}
