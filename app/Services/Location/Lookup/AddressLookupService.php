<?php

namespace App\Services\Location\Lookup;

use App\Services\Location\Coordinates\Adapters\LookupCoordinateLadder;
use App\Services\Location\Coordinates\Guards\CoordinateProviderTelemetry;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Typed address text in, coordinate out — the whole of the Location DNA
 * free-text lookup.
 *
 * WHERE IT SITS
 * -------------
 *     Radius Search / Important Places field
 *          ↓  POST, authenticated, rate limited
 *     AddressLookupController
 *          ↓
 *     this service  →  AddressLookupQuery  →  LookupCoordinateLadder
 *          ↓                                    ↓
 *     AddressLookupResult              AddressPoint → Census
 *          ↓
 *     the browser writes lat/lng into the listing's own stored blob
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 * --------------------------------
 * It writes nothing. Not `property_lat`, not `property_location_dna`, not a
 * meta key, not a listing row. A radius centre is not a property's location and
 * an Important Place is somebody else's address; routing either into the
 * platform's authoritative coordinate meta would make a workplace the
 * property's own point one save later, when {@see \App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter}
 * reads it back as rung 1. The listing's coordinate has exactly one writer and
 * it is not this class.
 *
 * It also dispatches nothing and geocodes nothing on its own initiative. There
 * is one entry point and it is a user pressing a button.
 *
 * FAILURE IS AN ANSWER, NOT AN EXCEPTION
 * --------------------------------------
 * Every path returns an {@see AddressLookupResult}. A thin address, a no-match,
 * an ambiguous match, a spent budget, an open circuit, a provider outage, a
 * bug in a rung — all of them produce `ok: false` with a machine-readable
 * reason for the log and one sentence for the person. Nothing throws out of
 * `lookup()`, because the caller is a form and a 500 there would look to the
 * user exactly like "this address does not exist".
 *
 * And no failure carries a coordinate. Not zero, not the centre of Florida, not
 * the map's current view. A caller literally cannot read a location off a
 * failed result.
 *
 * SINGLE FLIGHT, NOT A SECOND CACHE
 * ---------------------------------
 * {@see \App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter}
 * already caches every outcome for thirty days (one day for an ambiguous one),
 * keyed on the same unit-free normalized line this service resolves. Adding a
 * cache here would mean two caches with two TTLs answering one question, which
 * is a disagreement waiting to happen.
 *
 * What the adapter's cache cannot prevent is CONCURRENT identical misses: three
 * simultaneous requests for one address all find the cache empty and all call
 * the provider. So this takes a short lock on the normalized line, and the
 * waiters — once released — go through the adapter and find the cache warm. One
 * request reaches the provider; the rest are answered from what it stored. The
 * lock is an optimisation and never a correctness requirement: a cache store
 * with no lock support, or a lock that times out, degrades to the behaviour we
 * have today rather than to an error.
 */
class AddressLookupService
{
    /** How long the single-flight lock is held before it self-releases. */
    private const LOCK_SECONDS = 12;

    /** How long a waiter blocks for the leader before giving up and proceeding. */
    private const LOCK_WAIT_SECONDS = 8;

    private const LOCK_PREFIX = 'ldna:address-lookup:';

    private readonly PropertyCoordinateResolverInterface $resolver;

    /**
     * @param PropertyCoordinateResolverInterface|null $resolver injectable for
     *        tests; defaults to the free-text ladder so no caller decides what
     *        the ladder is — and, in particular, so no caller can hand this the
     *        standard listing ladder by mistake.
     */
    public function __construct(?PropertyCoordinateResolverInterface $resolver = null)
    {
        $this->resolver = $resolver ?? LookupCoordinateLadder::resolver();
    }

    /**
     * Locate one typed address.
     *
     * @param string $text whatever the user put in the box
     */
    public function lookup(string $text): AddressLookupResult
    {
        try {
            return $this->attempt($text);
        } catch (Throwable $e) {
            // The resolver already absorbs a faulting rung, so reaching here
            // means something further out broke. It still may not surface to
            // the user as anything but "not located".
            Log::warning('address_lookup_failed', ['error' => $e->getMessage()]);

            return AddressLookupResult::notLocated('lookup_error');
        }
    }

    private function attempt(string $text): AddressLookupResult
    {
        $address = AddressLookupQuery::parse($text);

        // Refused BEFORE any request, and before the lock. A street line with
        // no locality is ambiguous nationwide; the provider would answer it
        // with a coordinate in whichever state its corpus preferred, and the
        // budget would have paid for the privilege.
        if (! $address->hasMinimumForLookup()) {
            return AddressLookupResult::notLocated('insufficient_address');
        }

        $line = $address->coordinateLookupLine();
        $hash = CoordinateProviderTelemetry::addressHash($line);

        $result = $this->withSingleFlight(
            $line,
            fn (): PropertyCoordinateResult => $this->resolver->resolve($address)
        );

        if (! $result->isResolved() || $result->latitude === null || $result->longitude === null) {
            Log::info('address_lookup', [
                'outcome'      => 'not_located',
                'reason'       => $result->reason ?? 'unresolved',
                'address_hash' => $hash,
            ]);

            return AddressLookupResult::notLocated($result->reason ?? 'unresolved');
        }

        Log::info('address_lookup', [
            'outcome'      => 'located',
            'provider'     => $result->provider,
            'precision'    => $result->precision->value,
            'address_hash' => $hash,
        ]);

        return AddressLookupResult::located(
            latitude:  $result->latitude,
            longitude: $result->longitude,
            // The provider's own matched line where it gave one, and the line
            // we asked about where it did not. Never the raw typed text: what
            // is stored should be what was matched.
            address:   AddressLookupResult::present($result->normalizedAddress ?: $line),
            precision: $result->precision,
            provider:  $result->provider,
        );
    }

    /**
     * Run the resolution under a short lock on this address.
     *
     * The leader resolves and populates the adapter's cache. Waiters block
     * briefly, then resolve too — hitting that cache rather than the provider.
     *
     * DEGRADES, NEVER FAILS. A cache store without lock support throws
     * BadMethodCallException; a contended lock throws LockTimeoutException;
     * either way the resolution still runs. The worst case is the duplicate
     * provider call we would have made anyway.
     *
     * @param callable():PropertyCoordinateResult $resolve
     */
    private function withSingleFlight(string $line, callable $resolve): PropertyCoordinateResult
    {
        $lock = null;

        try {
            $lock = Cache::lock(self::LOCK_PREFIX . md5($line), self::LOCK_SECONDS);
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException $e) {
            $lock = null;
        } catch (Throwable $e) {
            // No lock support in this store. Proceed unserialised.
            $lock = null;
        }

        try {
            return $resolve();
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable $e) {
                    // A lock that cannot be released will expire on its own.
                }
            }
        }
    }
}
