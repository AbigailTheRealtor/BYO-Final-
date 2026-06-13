---
name: Accessor normalizer pattern — bid vs auction models
description: The four bid models return stdClass; the four auction models return an anonymous class with private $data. Both require different normalization to produce a usable PHP array.
---

## The shapes

**Bid models** (SellerAgentAuctionBid, BuyerAgentAuctionBid, LandlordAgentAuctionBid, TenantAgentAuctionBid):
`getGetAttribute()` ends with `$collection->push((object) $data)->first()` → returns **stdClass**.
`(array) $stdClass` works correctly because stdClass only has public properties.

**Auction models** (SellerAgentAuction, BuyerAgentAuction, LandlordAgentAuction, TenantAgentAuction):
`getGetAttribute()` ends with `return new class($data) { private $data; ... }` → returns **anonymous class with private $data**.
`(array) $anonClass` produces null-byte-mangled keys (e.g. `"\0*\0data"`) — NOT the actual data.

## The fix

In AgentBidAnalyticsObserver::normalizeAccessor():
1. `is_array` → return as-is
2. `instanceof Arrayable` → `->toArray()`
3. `method_exists($raw, 'toArray')` → `->toArray()` ← handles auction anonymous class
4. `is_object` fallback → `get_object_vars($raw)` ← public props only, no mangled keys
5. else → `[]`

All 4 auction models also had `toArray(): array { return $this->data; }` added to their anonymous class so step 3 fires correctly.

**Why get_object_vars() not (array):** `get_object_vars()` returns only public properties visible from external scope. For stdClass, same as (array). For anonymous classes with private props, returns [] instead of mangled null-byte keys.

## Related: rec attribution stale context
recordRecommendationInteraction() must call storeRecContext() on EVERY
bid_viewed (true AND false), not only when from_recommendation=true.
Without the false write, a normal view after a rec view leaves stale true
context, causing false attribution on bid_accepted/agent_hired.

## Testing pattern
- Use `ReflectionMethod::setAccessible(true)` to test `normalizeAccessor()` directly without needing to mock full observer wiring.
- Integration tests: insert rows into `seller_agent_auction_metas` with `seller_agent_auction_id`/`meta_key`/`meta_value`, then eager-load with `with(['meta', 'auction.meta'])` to exercise the full EAV → accessor → normalizer chain.
