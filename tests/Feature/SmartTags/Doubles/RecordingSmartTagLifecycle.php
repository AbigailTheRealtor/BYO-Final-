<?php

namespace Tests\Feature\SmartTags\Doubles;

use App\Models\BridgeProperty;
use App\Services\SmartTags\DerivationOutcome;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use Illuminate\Database\Eloquent\Model;

/**
 * A lifecycle that records what it was asked to do and does nothing.
 *
 * The tripwire for "this path must not derive". A THROWING double cannot serve
 * that purpose any more: the call sites now go through
 * SmartTagLifecycle::tryDerive*(), which swallows everything by design, so a
 * throw would be caught and the test would pass whether or not the call
 * happened. Counting is the only way left to tell.
 */
class RecordingSmartTagLifecycle extends SmartTagLifecycle
{
    /** @var array<int, array{kind: string, id: int|string|null, entry: string}> */
    public array $calls = [];

    public function __construct()
    {
        // No dependencies: nothing real is ever reached.
    }

    public function deriveForBridgeSilently(BridgeProperty $property, string $entryPoint = SmartTagTelemetry::ENTRY_BRIDGE_LOOKUP): ?DerivationOutcome
    {
        $this->calls[] = ['kind' => 'bridge', 'id' => $property->getKey(), 'entry' => $entryPoint];

        return null;
    }

    public function deriveForNativeSilently(Model $listing, string $entryPoint): ?DerivationOutcome
    {
        $this->calls[] = ['kind' => 'native', 'id' => $listing->getKey(), 'entry' => $entryPoint];

        return null;
    }

    public function purgeSilently(string $modelClass, array $ids): void
    {
        $this->calls[] = ['kind' => 'purge', 'id' => implode(',', $ids), 'entry' => SmartTagTelemetry::ENTRY_PURGE];
    }

    public function countOf(string $kind): int
    {
        return count(array_filter($this->calls, static fn (array $c) => $c['kind'] === $kind));
    }
}
