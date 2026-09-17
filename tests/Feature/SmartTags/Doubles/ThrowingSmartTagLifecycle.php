<?php

namespace Tests\Feature\SmartTags\Doubles;

use App\Models\BridgeProperty;
use App\Services\SmartTags\DerivationOutcome;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A lifecycle that fails at every entry point.
 *
 * Bound in place of the real one so the failure-isolation tests prove the CALL
 * SITES are safe, rather than proving only that the real implementation happens
 * to catch. It extends the real class so the container binding stays
 * type-compatible, and overrides every public method to throw.
 *
 * It is also used as a tripwire: where a path must NOT derive, binding this makes
 * an unwanted call fail loudly instead of passing quietly.
 */
class ThrowingSmartTagLifecycle extends SmartTagLifecycle
{
    public function __construct()
    {
        // Deliberately no parent::__construct(): nothing in this object is reached.
    }

    public function deriveForBridgeSilently(BridgeProperty $property, string $entryPoint = SmartTagTelemetry::ENTRY_BRIDGE_LOOKUP): ?DerivationOutcome
    {
        throw new RuntimeException('Smart Tag derivation exploded');
    }

    public function deriveForNativeSilently(Model $listing, string $entryPoint): ?DerivationOutcome
    {
        throw new RuntimeException('Smart Tag derivation exploded');
    }

    public function purgeSilently(string $modelClass, array $ids): void
    {
        throw new RuntimeException('Smart Tag purge exploded');
    }
}
