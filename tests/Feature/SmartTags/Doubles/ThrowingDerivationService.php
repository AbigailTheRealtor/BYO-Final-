<?php

namespace Tests\Feature\SmartTags\Doubles;

use App\Models\BridgeProperty;
use App\Services\SmartTags\DerivationOutcome;
use App\Services\SmartTags\SmartTagDerivationService;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A derivation service that CONSTRUCTS cleanly and fails when used.
 *
 * Binding a throwing factory instead would fail inside the container while
 * building SmartTagLifecycle, which proves nothing about the facade's catch —
 * the exception would never have reached its try block.
 */
class ThrowingDerivationService extends SmartTagDerivationService
{
    public function __construct()
    {
        // The parent's dependencies are never read: both methods throw first.
    }

    public function deriveBridge(BridgeProperty $property): DerivationOutcome
    {
        throw new RuntimeException('deriver exploded');
    }

    public function deriveNative(Model $listingModel): DerivationOutcome
    {
        throw new RuntimeException('deriver exploded');
    }
}
