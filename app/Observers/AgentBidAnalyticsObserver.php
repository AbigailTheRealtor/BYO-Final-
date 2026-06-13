<?php

namespace App\Observers;

use App\Services\BidAnalyticsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * AgentBidAnalyticsObserver
 *
 * Hooked to all four agent auction bid model types:
 *   SellerAgentAuctionBid, BuyerAgentAuctionBid,
 *   LandlordAgentAuctionBid, TenantAgentAuctionBid
 *
 * Triggered events → snapshot event types:
 *   created  → bid_submitted   (submitting the bid form creates the record)
 *   updated (accepted unchanged) → bid_updated
 *   updated (accepted → 'accepted') → bid_accepted
 *
 * Listing and bid data are loaded from the EAV meta system via $bid->get.
 * The observer loads the parent auction's meta the same way.
 * If data cannot be loaded, an empty array is passed to BidAnalyticsService,
 * which will fall back to 'unknown'/'none' scoring without aborting.
 *
 * The $bidType and $role for each model are determined by the registered
 * class name (see EventServiceProvider).
 */
class AgentBidAnalyticsObserver
{
    /**
     * Map from bid model class → [bidType, role].
     */
    private const BID_TYPE_MAP = [
        \App\Models\SellerAgentAuctionBid::class   => ['seller_agent', 'seller'],
        \App\Models\BuyerAgentAuctionBid::class    => ['buyer_agent',  'buyer'],
        \App\Models\LandlordAgentAuctionBid::class => ['landlord_agent', 'landlord'],
        \App\Models\TenantAgentAuctionBid::class   => ['tenant_agent',  'tenant'],
    ];

    public function created(Model $bid): void
    {
        [$bidType, $role] = $this->meta($bid);
        [$listingData, $bidData, $propertyType] = $this->loadData($bid);

        // bid_created: the bid record was just inserted into the database.
        BidAnalyticsService::captureSnapshot(
            $bidType, $bid->id, $role, $propertyType,
            BidAnalyticsService::EVENT_BID_CREATED,
            $listingData, $bidData
        );

        // bid_submitted: in this system, creating the bid IS the submission act.
        // Stored as a separate snapshot so both lifecycle events are independently
        // queryable even though they share the same timestamp.
        BidAnalyticsService::captureSnapshot(
            $bidType, $bid->id, $role, $propertyType,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            $listingData, $bidData
        );

        $readinessState = $this->readinessStateFromData($bidData, $role);
        BidAnalyticsService::advanceFunnel($bidType, $bid->id, $role, BidAnalyticsService::EVENT_BID_SUBMITTED);
        BidAnalyticsService::advanceFunnel($bidType, $bid->id, $role, $readinessState);
    }

    public function updated(Model $bid): void
    {
        [$bidType, $role] = $this->meta($bid);

        $wasAccepted   = $bid->getOriginal('accepted');
        $isNowAccepted = $bid->accepted;

        $becameAccepted = ($isNowAccepted === 'accepted' && $wasAccepted !== 'accepted');

        [$listingData, $bidData, $propertyType] = $this->loadData($bid);

        $eventType = $becameAccepted
            ? BidAnalyticsService::EVENT_BID_ACCEPTED
            : BidAnalyticsService::EVENT_BID_UPDATED;

        BidAnalyticsService::captureSnapshot(
            $bidType, $bid->id, $role, $propertyType,
            $eventType,
            $listingData, $bidData
        );

        $readinessState = $this->readinessStateFromData($bidData, $role);
        BidAnalyticsService::advanceFunnel($bidType, $bid->id, $role, $readinessState);

        if ($becameAccepted) {
            BidAnalyticsService::advanceFunnel($bidType, $bid->id, $role, BidAnalyticsService::EVENT_BID_ACCEPTED);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function meta(Model $bid): array
    {
        $class = get_class($bid);
        return self::BID_TYPE_MAP[$class] ?? ['unknown', 'unknown'];
    }

    /**
     * Load listing data and bid data arrays from the EAV meta system.
     * Returns [$listingData, $bidData, $propertyType].
     *
     * The `get` appended attribute on all four bid/auction model classes
     * returns a stdClass built via `$collection->push((object) $data)->first()`.
     * We normalize it through normalizeAccessor() which handles stdClass,
     * Arrayable, and native array — ensuring bidData/listingData are always
     * proper PHP arrays before passing them to the scoring service.
     */
    private function loadData(Model $bid): array
    {
        $bidData      = [];
        $propertyType = null;

        try {
            $bidData = $this->normalizeAccessor($bid->get);
        } catch (\Throwable $e) {
            Log::debug('[AgentBidAnalyticsObserver] Could not load bid data', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
            ]);
        }

        $listingData = [];
        try {
            if (method_exists($bid, 'auction') && $bid->auction !== null) {
                $auction     = $bid->auction;
                $listingData = $this->normalizeAccessor($auction->get ?? null);
                // property_type may live in meta (EAV) or as a native column.
                $propertyType = $listingData['property_type'] ?? $auction->property_type ?? null;
            }
        } catch (\Throwable $e) {
            Log::debug('[AgentBidAnalyticsObserver] Could not load listing data', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
            ]);
        }

        return [$listingData, $bidData, $propertyType];
    }

    /**
     * Normalize an accessor return value to a plain PHP array.
     *
     * The four bid model `getGetAttribute()` implementations all do:
     *   $collection->push((object) $data)->first()
     * which returns a stdClass. The four auction model implementations return
     * an anonymous class with private $data and magic __get/__isset.
     *
     * Handles all realistic shapes in priority order:
     *   1. native array              → returned as-is
     *   2. Arrayable interface       → ->toArray()
     *   3. object with toArray()     → ->toArray()  (auction anonymous class)
     *   4. other object (stdClass)   → (array) cast (public properties only)
     *   5. null / scalar             → []
     *
     * Note: (array) cast on an anonymous class with private $data produces
     * null-byte-mangled keys (e.g. "\0*\0data"), not the actual data, which is
     * why toArray() must be checked before the generic cast in step 4.
     */
    private function normalizeAccessor(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $raw->toArray();
        }
        if (is_object($raw)) {
            if (method_exists($raw, 'toArray')) {
                return $raw->toArray();
            }
            // get_object_vars() only returns public properties (no null-byte mangled
            // keys for private/protected). For stdClass this is equivalent to (array) cast.
            // For anonymous classes with private $data and no toArray(), this returns [].
            return get_object_vars($raw);
        }
        return [];
    }

    /**
     * Derive readiness state from bid data via MatchReadinessService for funnel tracking.
     */
    private function readinessStateFromData(array $bidData, string $role): string
    {
        try {
            $result = \App\Services\MatchReadinessService::evaluate($bidData, $role);
            return $result['state'] ?? 'not_ready';
        } catch (\Throwable $e) {
            return 'not_ready';
        }
    }
}
