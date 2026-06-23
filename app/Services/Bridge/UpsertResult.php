<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;

/**
 * Value object returned by BridgePropertyNormalizer::upsert().
 *
 * Carries the persisted model and two signals that callers use to decide
 * whether dispatching ComputeLocationDna is warranted:
 *
 *  - isNew         — the record did not previously exist in bridge_properties.
 *  - addressChanged — the unparsed_address or postal_code changed since the
 *                     last import, so location DNA may be stale.
 *
 * Use shouldDispatchDna() as the single entry-point gate before dispatch.
 */
class UpsertResult
{
    public function __construct(
        public readonly bool $isNew,
        public readonly bool $addressChanged,
        public readonly BridgeProperty $model,
    ) {}

    /**
     * True when the Location DNA job should be dispatched for this record.
     *
     * Dispatching is warranted when the record is brand-new (no DNA has ever
     * been computed) or when the address changed (existing DNA is stale).
     */
    public function shouldDispatchDna(): bool
    {
        return $this->isNew || $this->addressChanged;
    }
}
