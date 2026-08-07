<?php

namespace App\Services\Location\Coordinates;

/**
 * Resolve a property address to a coordinate.
 *
 * The one contract listing flows depend on. It names no provider, so replacing
 * the US Census Geocoder with a commercial fallback — or with our own address
 * corpus — is an adapter change and a config binding, not a change to any
 * calling component.
 *
 * Takes a {@see PropertyAddress}, not a Livewire component or an Eloquent model,
 * so Seller, Landlord, Buyer, Tenant, MLS import and anything later all share
 * one implementation.
 */
interface PropertyCoordinateResolverInterface
{
    /**
     * Always returns a result; never throws for an unresolvable address.
     *
     * An address that cannot be resolved comes back as
     * {@see PropertyCoordinateResult::unresolved()} with a reason — the
     * fail-closed shape the merged Google kill-switch work established, kept
     * here so a provider outage degrades to "no coordinate" rather than an
     * exception in a publish path.
     */
    public function resolve(PropertyAddress $address): PropertyCoordinateResult;
}
