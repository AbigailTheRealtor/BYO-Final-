<?php

namespace App\Contracts;

/**
 * A POI provider that can say, before being asked, which categories it is able to answer
 * at all.
 *
 * WHY THIS IS A SEPARATE, OPTIONAL INTERFACE
 * ------------------------------------------
 * {@see NearbyPoiFetcherInterface::fetchNearby()} returns a list of candidates, and an
 * empty list has always meant one thing to the caller: "asked, and there is nothing
 * nearby". That is a true statement for a provider which covers the category and happened
 * to find nothing — a neighbourhood with no marina really has no marina — and it is
 * persisted as `not_found`, which is what `not_found` is for.
 *
 * It is NOT a true statement for a provider that does not carry the category at all. The
 * Overture corpus holds seven of the nineteen categories this pipeline asks for; for the
 * other twelve `CorpusPoiCategoryMap` cannot place the descriptor and the adapter returns
 * `[]`. Read through the same code path, that produced a row saying "overture_corpus
 * returned zero results for this category" — indistinguishable, in the database and to
 * anything reading it later, from "there is no park near this home". One is a fact about
 * the neighbourhood; the other is a fact about our data coverage.
 *
 * An empty list cannot carry that distinction, so the question is asked BEFORE the fetch
 * instead. Implementing this interface is how a provider opts in to being asked.
 *
 * NOT ADDED TO `NearbyPoiFetcherInterface` ON PURPOSE. Most providers answer every
 * category they are given, and a required method would force each of them — and every
 * test fake — to return `true`. A fetcher that does not implement this interface is
 * treated as supporting everything, which is exactly the behaviour every caller had
 * before this existed, so nothing changes for `GooglePlacesPoiAdapter` or for a fixture
 * fetcher in a test.
 *
 * @see \App\Services\LocationDna\OvertureCorpusPoiAdapter
 * @see \App\Services\LocationDna\LocationDnaPoiDistanceService::providerSupportsCategory()
 */
interface ProviderCategorySupport
{
    /**
     * Can this provider answer the category this descriptor names?
     *
     * `false` means the provider carries no data for it and must not be asked — the
     * caller skips the category entirely and persists nothing, so that the absence of a
     * row means "this provider does not cover this" and a `not_found` row keeps its one
     * meaning. It is a statement about coverage, never about this coordinate: it must not
     * depend on where the property is, only on what the provider holds.
     *
     * @param  array<string, mixed> $meta One value from
     *         {@see \App\Services\LocationDna\LocationDnaPoiDistanceService::CATEGORIES},
     *         the same descriptor `fetchNearby()` receives.
     */
    public function supportsCategory(array $meta): bool;
}
