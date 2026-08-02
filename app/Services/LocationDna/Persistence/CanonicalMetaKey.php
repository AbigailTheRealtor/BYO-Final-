<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\LocationDnaDocument;

/**
 * G1f-1 — the canonical meta key, named once.
 *
 * The literal already exists on {@see LocationDnaDocument::CANONICAL_META_KEY}. This class exists
 * so {@see MetaKeyedRecord} can name the key without importing the contract core, which keeps the
 * only persistence-touching class free of a domain dependency it does not otherwise need.
 *
 * The value is taken FROM the contract rather than re-typed, so the two can never drift.
 */
final class CanonicalMetaKey
{
    public const KEY = LocationDnaDocument::CANONICAL_META_KEY;

    private function __construct()
    {
    }
}
