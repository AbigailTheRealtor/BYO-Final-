# Phase 1A Offer System Implementation Plan

**Status:** Authoritative build specification — read before writing any Phase 1A code
**Inputs:** `OFFER_SYSTEM_GOVERNANCE.md`, `OFFER_SYSTEM_BUILD_ORDER.md`, `OFFER_SYSTEM_DO_NOT_TOUCH.md`, `OFFER_ARCHITECTURE_FOUNDATION_AUDIT.md`
**Scope:** Submission-side data layer only. No UI, no routes, no controllers.

---

## Section 1 — Phase 1A Goal and Scope

### Objective

Create the foundational **submission-side data layer** for the Offer System. A buyer or tenant can eventually submit a formal purchase or lease offer against a listing in `offer_auctions`. Phase 1A establishes the tables, models, services, and gate that all later phases depend on. Nothing visible to end users is built in this phase.

### Deliverables

**Three migrations (new tables only):**
- `create_offers_table`
- `create_offer_metas_table`
- `create_offer_event_logs_table`

**Three Eloquent models:**
- `Offer`
- `OfferMeta`
- `OfferEventLog`

**Two service classes:**
- `OfferStateService`
- `OfferSnapshotService`

**One gate:**
- `OfferAccessGate` (registered as the `submit-offer` Laravel Gate)

**One config file:**
- `config/offer_system.php`

### What Is Not in Scope for Phase 1A

- Any Livewire component, Blade view, controller, or route
- Counter-offer logic (Phase 2)
- Offer comparison view (Phase 3)
- Accepted Offer Summary generation (Phase 4)
- PDF export (Phase 5)
- E-sign integration (Phase 6)
- AI analysis (Phase 7)
- Any modification to any existing table, column, or EAV meta key

---

## Section 2 — Do-Not-Touch Boundaries

The following areas are protected. No Phase 1A file may touch, import into, or modify any item on this list. Any apparent need to modify a protected area requires an explicit stop and approval request.

### Existing Listing Creation Forms
- [ ] Seller Offer Listing (form, Livewire, JS, validation)
- [ ] Buyer Offer Listing (form, Livewire, JS, validation)
- [ ] Landlord Offer Listing (form, Livewire, JS, validation)
- [ ] Tenant Offer Listing (form, Livewire, JS, validation)
- [ ] `initializeLimitedService()` — frozen legacy function; never touch

### No Parallel Listing Form System
- [ ] Do not create any new listing creation path or duplicate any existing listing field

### Existing EAV Meta Keys (read-only; no remove, rename, or type change)
- [ ] `seller_offer_listing_metas`
- [ ] `buyer_offer_listing_metas`
- [ ] `landlord_offer_listing_metas`
- [ ] `tenant_offer_listing_metas`
- [ ] `seller_agent_auction_metas` / `buyer_agent_auction_metas` / `landlord_agent_auction_metas` / `tenant_agent_auction_metas`
- [ ] `seller_agent_auction_bid_metas` / `buyer_agent_auction_bid_metas` / `landlord_agent_auction_bid_metas` / `tenant_agent_auction_bid_metas`
- [ ] Any other `*_metas` table in the schema

### Accepted Bid Summary System
- [ ] `AcceptedBidSummary` model and relationships
- [ ] `x-bid-detail-layout` Blade component
- [ ] PDF cache invalidation logic tied to accepted bid summaries
- [ ] Accepted bid summary view pages and download endpoints

### BidYourAgent Hiring Flows
- [ ] Public Hire Me URLs (`/hire/{agentShortId}/{role}/{propertyType?}`)
- [ ] Embeddable widget (`/widget/hire/{agentShortId}/{role}/{propertyType}`)
- [ ] `AgentBidMapperService` auto-bid creation logic
- [ ] `AgentDefaultProfile` preset system and UI (`/agent/presets`)
- [ ] Agent Hire Listings Hub (`/agent/hire-listings`)

### Referral Tracking
- [ ] `referral_visits` table and population logic
- [ ] My Referrals page (`/agent/my-referrals`) and data queries
- [ ] Referral percentage field handling in agent bid forms or summaries

### Ask AI / Property DNA / Location DNA
- [ ] Property DNA generation, storage, or display logic
- [ ] Buyer/Tenant DNA compatibility scoring
- [ ] Location DNA phase implementations
- [ ] Any existing OpenAI prompt contracts or response-parsing logic
- [ ] AI explanation layer for existing bid/listing fields

### Additive-Only Migration Rule
- [ ] No `dropColumn`, `dropColumns`, `renameColumn`, `dropTable`, or narrowing `change` on any existing table
- [ ] No existing column removed or renamed anywhere in the schema

---

## Section 3 — Exact Migration Blueprints

Migrations must be created in order: `create_offers_table` → `create_offer_metas_table` → `create_offer_event_logs_table`. All three belong in a single pull request.

---

### 3.1 Migration: `create_offers_table`

```php
Schema::create('offers', function (Blueprint $table) {
    $table->id();                                                          // bigint unsigned, PK, not null
    $table->foreignId('offer_auction_id')
          ->constrained('offer_auctions')
          ->cascadeOnDelete();                                              // bigint unsigned, FK → offer_auctions.id, not null; index auto-created
    $table->foreignId('user_id')
          ->constrained('users')
          ->cascadeOnDelete();                                              // bigint unsigned, FK → users.id, not null; index auto-created
    $table->string('role', 20);                                            // varchar(20), not null; values: 'buyer' | 'tenant'
    $table->string('status', 30)->default('draft');                        // varchar(30), not null, default 'draft'
    $table->json('listing_snapshot')->nullable();                          // JSON, nullable; written once at draft→submitted
    $table->unsignedBigInteger('parent_offer_id')->nullable();             // bigint unsigned, nullable; always NULL in Phase 1A
    $table->timestamp('submitted_at')->nullable();                         // timestamp, nullable; set at draft→submitted transition
    $table->timestamp('expires_at')->nullable();                           // timestamp, nullable; submitter-specified expiry
    $table->timestamps();                                                  // created_at, updated_at; not null
});

// Self-referencing FK added in a separate Schema::table call (after table exists)
Schema::table('offers', function (Blueprint $table) {
    $table->foreign('parent_offer_id')
          ->references('id')
          ->on('offers')
          ->nullOnDelete();                                                 // null parent_offer_id if parent is deleted
    $table->index('parent_offer_id');
    $table->index('status');
});
```

**Column summary:**

| Column | Type | Nullable | Default | Index | FK Target |
|--------|------|----------|---------|-------|-----------|
| `id` | bigint unsigned | no | — | PK | — |
| `offer_auction_id` | bigint unsigned | no | — | FK index | `offer_auctions.id` |
| `user_id` | bigint unsigned | no | — | FK index | `users.id` |
| `role` | varchar(20) | no | — | — | — |
| `status` | varchar(30) | no | `draft` | plain index | — |
| `listing_snapshot` | json | yes | null | — | — |
| `parent_offer_id` | bigint unsigned | yes | null | plain index | `offers.id` (self) |
| `submitted_at` | timestamp | yes | null | — | — |
| `expires_at` | timestamp | yes | null | — | — |
| `created_at` | timestamp | no | — | — | — |
| `updated_at` | timestamp | no | — | — | — |

**Columns intentionally excluded:**
- `accepted_at`, `rejected_at`, `withdrawn_at` — always derived from `offer_event_logs`; must not be stored as separate columns
- Any price, term, or contingency fields — stored in `offer_metas`

**`down()` method:**
```php
Schema::dropIfExists('offers');
```
> Note: `down()` for the self-referencing FK must drop the FK constraint before dropping the table, or rely on `dropIfExists` cascade. The recommended approach is to drop the FK in the same `down()` using `Schema::table('offers', fn($t) => $t->dropForeign(['parent_offer_id']))` before calling `Schema::dropIfExists('offers')`.

---

### 3.2 Migration: `create_offer_metas_table`

```php
Schema::create('offer_metas', function (Blueprint $table) {
    $table->id();                                                          // bigint unsigned, PK, not null
    $table->foreignId('offer_id')
          ->constrained('offers')
          ->cascadeOnDelete();                                              // bigint unsigned, FK → offers.id, not null
    $table->string('meta_key', 100);                                       // varchar(100), not null
    $table->text('meta_value')->nullable();                                // text, nullable
    $table->timestamps();                                                  // created_at, updated_at

    $table->unique(['offer_id', 'meta_key']);                              // prevents duplicate keys per offer
});
```

**Column summary:**

| Column | Type | Nullable | Default | Index | FK Target |
|--------|------|----------|---------|-------|-----------|
| `id` | bigint unsigned | no | — | PK | — |
| `offer_id` | bigint unsigned | no | — | FK index | `offers.id` |
| `meta_key` | varchar(100) | no | — | composite unique | — |
| `meta_value` | text | yes | null | — | — |
| `created_at` | timestamp | no | — | — | — |
| `updated_at` | timestamp | no | — | — | — |

**`down()` method:**
```php
Schema::dropIfExists('offer_metas');
```

---

### 3.3 Migration: `create_offer_event_logs_table`

```php
Schema::create('offer_event_logs', function (Blueprint $table) {
    $table->id();                                                          // bigint unsigned, PK, not null
    $table->foreignId('offer_id')
          ->constrained('offers')
          ->cascadeOnDelete();                                              // bigint unsigned, FK → offers.id, not null
    $table->unsignedBigInteger('actor_id')->nullable();                    // bigint unsigned, nullable (null = system event)
    $table->string('actor_role', 30)->nullable();                          // varchar(30), nullable: 'submitter'|'listing_owner'|'system'
    $table->string('event_type', 50);                                      // varchar(50), not null
    $table->string('from_status', 30)->nullable();                         // varchar(30), nullable; null for creation events
    $table->string('to_status', 30)->nullable();                           // varchar(30), nullable; null for non-state events
    $table->json('metadata')->nullable();                                  // JSON, nullable; rejection reason, notes, Phase 2 counter ref
    $table->string('ip_address', 45)->nullable();                          // varchar(45), nullable; IPv4 or IPv6
    $table->timestamp('created_at')->nullable();                           // created_at only; NO updated_at column

    $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
    $table->index(['offer_id', 'event_type']);
    $table->index('actor_id');
});
```

**Column summary:**

| Column | Type | Nullable | Default | Index | FK Target |
|--------|------|----------|---------|-------|-----------|
| `id` | bigint unsigned | no | — | PK | — |
| `offer_id` | bigint unsigned | no | — | FK + composite index | `offers.id` |
| `actor_id` | bigint unsigned | yes | null | plain index | `users.id` |
| `actor_role` | varchar(30) | yes | null | — | — |
| `event_type` | varchar(50) | no | — | composite index | — |
| `from_status` | varchar(30) | yes | null | — | — |
| `to_status` | varchar(30) | yes | null | — | — |
| `metadata` | json | yes | null | — | — |
| `ip_address` | varchar(45) | yes | null | — | — |
| `created_at` | timestamp | yes | — | — | — |

> **Critical:** There is no `updated_at` column on this table. Do not call `$table->timestamps()` — use `$table->timestamp('created_at')->nullable()` only. Event log rows are never modified after insertion.

**`down()` method:**
```php
Schema::dropIfExists('offer_event_logs');
```

---

### 3.4 Required Drop Order in `down()` Methods

Because `offer_event_logs` and `offer_metas` carry FKs to `offers`, the reverse migration sequence must drop child tables before the parent:

1. `offer_event_logs` → drop first
2. `offer_metas` → drop second
3. `offers` → drop last (after dropping its own self-referencing FK first)

---

### 3.5 Pre-Run Verification Step

Before executing any of the three migrations in any environment, run:

```bash
php artisan migrate --pretend
```

Review the SQL output and confirm:
- No `ALTER TABLE` statement targets an existing table
- No `DROP` statement appears
- No column is renamed
- The three new tables are the only tables affected

Do not proceed if any destructive statement appears in the pretend output.

---

## Section 4 — State Machine Definition

The `offers.status` column holds the current state of every offer record. Six values must be storable from Phase 1A onward, even though only five transitions are implemented in Phase 1A.

| State | Description | Terminal? | Phase |
|-------|-------------|-----------|-------|
| `draft` | Offer being composed; not yet sent. Visible only to the submitter. | No | 1A |
| `submitted` | Formally submitted; visible to the listing owner. | No | 1A |
| `accepted` | Listing owner accepted. No further transitions allowed. | **Yes** | 1A |
| `rejected` | Listing owner explicitly rejected. No further transitions allowed. | **Yes** | 1A |
| `withdrawn` | Submitter withdrew before a decision was made. No further transitions allowed. | **Yes** | 1A |
| `countered` | Phase 2 stub. A counter-offer was issued. Do **not** implement any transition logic for this state in Phase 1A. | No (Phase 2) | 2 |

**Reserved (not yet defined):** `expired` — reserved for future use; do not implement in any phase before it is explicitly scoped.

**Rule:** All six enum values must be representable in the `status` varchar(30) column from Phase 1A. The `OfferStateService` must only implement transitions for `draft`, `submitted`, `accepted`, `rejected`, and `withdrawn` in Phase 1A. The `countered` value may be stored but no method in Phase 1A may transition an offer INTO `countered`.

---

## Section 5 — Allowed and Forbidden Transitions

### 5.1 Allowed Transitions

| From | To | Who May Trigger | Phase |
|------|----|-----------------|-------|
| `draft` | `submitted` | Submitter (buyer/tenant) | 1A |
| `submitted` | `accepted` | Listing owner (seller/landlord) | 1A |
| `submitted` | `rejected` | Listing owner (seller/landlord) | 1A |
| `submitted` | `withdrawn` | Submitter (buyer/tenant) | 1A |
| `submitted` | `countered` | Listing owner (seller/landlord) | **Phase 2 only** |
| `countered` | `accepted` | Counter recipient (submitter) | **Phase 2 only** |
| `countered` | `rejected` | Counter recipient (submitter) | **Phase 2 only** |
| `countered` | `withdrawn` | Either party | **Phase 2 only** |

### 5.2 Forbidden Transitions

| Attempt | Reason |
|---------|--------|
| `accepted` → any | Terminal state; accepted offers are immutable |
| `rejected` → any | Terminal state |
| `withdrawn` → any | Terminal state |
| `draft` → `accepted` | Must pass through `submitted` first |
| `draft` → `rejected` | Must pass through `submitted` first |
| `draft` → `withdrawn` | Must pass through `submitted`; a draft may simply be deleted |
| `draft` → `countered` | Cannot counter an unsubmitted offer |
| Any skip across non-adjacent states | State machine is strictly sequential |

### 5.3 Exception Requirement

`OfferStateService` must throw a typed exception class — `OfferTransitionException` — on every forbidden transition attempt. It must not return a boolean, null, or silently fail. `OfferTransitionException` must be a named class (e.g., `App\Exceptions\OfferTransitionException`) that callers can catch distinctly from other exceptions.

---

## Section 6 — Model Blueprints

### 6.1 `Offer` Model

```php
class Offer extends Model
{
    protected $table = 'offers';

    protected $fillable = [
        'offer_auction_id',
        'user_id',
        'role',
        'status',
        'listing_snapshot',
        'parent_offer_id',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'listing_snapshot' => 'array',       // JSON cast; returns assoc array
        'submitted_at'     => 'datetime',
        'expires_at'       => 'datetime',
    ];
}
```

**Immutability rules:**
- `listing_snapshot` must not be overwritten after the offer transitions out of `draft`. `OfferSnapshotService` enforces this; `Offer` itself should not implement auto-mutation.
- `offer_auction_id` and `user_id` must not be changed after creation. These are set once at record creation and must be treated as immutable in service logic.

**No `$guarded = []` shortcut.** Use explicit `$fillable` so no unexpected mass-assignment is possible.

---

### 6.2 `OfferMeta` Model

```php
class OfferMeta extends Model
{
    protected $table = 'offer_metas';

    protected $fillable = [
        'offer_id',
        'meta_key',
        'meta_value',
    ];
}
```

No special casts. `meta_value` is stored as text; callers are responsible for serializing/deserializing structured values (e.g., JSON strings) before writing and after reading.

---

### 6.3 `OfferEventLog` Model

```php
class OfferEventLog extends Model
{
    protected $table = 'offer_event_logs';

    public const UPDATED_AT = null;         // disables updated_at entirely

    protected $fillable = [
        'offer_id',
        'actor_id',
        'actor_role',
        'event_type',
        'from_status',
        'to_status',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata'   => 'array',            // JSON cast
        'created_at' => 'datetime',
    ];
}
```

**Immutability rules:**
- `public const UPDATED_AT = null` tells Laravel not to attempt writing an `updated_at` timestamp.
- No `update()` or `delete()` call may target this model from application code. `OfferStateService` must only ever call `OfferEventLog::create()` or `DB::table('offer_event_logs')->insert()`.
- No soft deletes on this model.

---

## Section 7 — Relationship Blueprints

### 7.1 `Offer` Relationships

| Method name | Type | Target | Notes |
|-------------|------|--------|-------|
| `metas()` | `hasMany` | `OfferMeta::class` | FK: `offer_metas.offer_id` |
| `eventLogs()` | `hasMany` | `OfferEventLog::class` | FK: `offer_event_logs.offer_id` |
| `offerAuction()` | `belongsTo` | `OfferAuction::class` | FK: `offers.offer_auction_id` |
| `user()` | `belongsTo` | `User::class` | FK: `offers.user_id`; the submitting buyer/tenant |
| `parentOffer()` | `belongsTo` | `Offer::class` | FK: `offers.parent_offer_id`; self-referencing; always null in Phase 1A |
| `childOffers()` | `hasMany` | `Offer::class` | Inverse of `parentOffer()`; Phase 2 counter chain |

### 7.2 `OfferMeta` Relationships

| Method name | Type | Target | Notes |
|-------------|------|--------|-------|
| `offer()` | `belongsTo` | `Offer::class` | FK: `offer_metas.offer_id` |

### 7.3 `OfferEventLog` Relationships

| Method name | Type | Target | Notes |
|-------------|------|--------|-------|
| `offer()` | `belongsTo` | `Offer::class` | FK: `offer_event_logs.offer_id` |
| `actor()` | `belongsTo` | `User::class` | FK: `offer_event_logs.actor_id`; nullable; null for system events |

---

## Section 8 — Service Blueprints (Public Method Signatures Only)

### 8.1 `OfferStateService`

**Typed exception class:** `App\Exceptions\OfferTransitionException`

Every transition method must:
1. Validate that the requested transition is in the allowed-transitions table for Phase 1A.
2. Throw `OfferTransitionException` if the transition is forbidden.
3. Wrap the status update on `offers` AND the event log insert into a single `DB::transaction()`. If either write fails, both roll back.

**Public method signatures:**

```php
// Transition draft → submitted. Calls OfferSnapshotService to write listing_snapshot.
// Sets submitted_at on the offer. Returns the updated Offer.
public function submit(Offer $offer, User $actor): Offer

// Transition submitted → accepted. Returns the updated Offer.
public function accept(Offer $offer, User $actor): Offer

// Transition submitted → rejected. $reason is stored in the event log metadata.
// Returns the updated Offer.
public function reject(Offer $offer, User $actor, ?string $reason = null): Offer

// Transition submitted → withdrawn. Returns the updated Offer.
public function withdraw(Offer $offer, User $actor): Offer

// Internal helper used by all transition methods. Not callable externally.
// Writes the event log row atomically with the status update.
// Throws OfferTransitionException for forbidden transitions.
private function transition(
    Offer $offer,
    string $toStatus,
    string $eventType,
    User $actor,
    array $metadata = []
): Offer
```

**Rule:** No public method named `counter()`, `setCountered()`, or any equivalent may exist on this class in Phase 1A. The `countered` status value may not be transitioned into by any callable method.

---

### 8.2 `OfferSnapshotService`

Phase 1A stubs this service. Phase 1B defines the exact EAV keys to capture.

```php
// Reads offer_auction_metas for the given offer_auction_id and returns
// a snapshot array. Called by OfferStateService::submit() at draft→submitted.
// Must NOT write to offer_auction_metas or offer_auctions.
// Must NOT overwrite an existing non-null listing_snapshot.
// Phase 1B defines the exact meta keys captured as 'preferred_terms'.
public function capture(int $offerAuctionId): array

// Returns the minimum required snapshot envelope.
// Phase 1A stub: preferred_terms will be an empty array until Phase 1B.
// Minimum returned shape:
// [
//     'snapshot_at'     => <ISO 8601 string>,
//     'listing_id'      => <offer_auctions.id>,
//     'listing_title'   => <offer_auctions.title>,
//     'listing_role'    => <from EAV meta>,
//     'property_address'=> <from EAV meta>,
//     'property_type'   => <from EAV meta>,
//     'listing_status'  => <from EAV meta at snapshot time>,
//     'preferred_terms' => [],   // populated in Phase 1B
// ]
private function buildEnvelope(array $metaRows, $offerAuction): array
```

**Constraint:** `OfferSnapshotService` is read-only on `offer_auction_metas`. It uses `DB::table('offer_auction_metas')` to read. It never calls `insert()`, `update()`, or `delete()` on that table.

---

### 8.3 `OfferAccessGate`

Registered as the `submit-offer` named Laravel Gate in a service provider.

```php
// Gate name constant — use this string when calling Gate::check() or $this->authorize()
const GATE_NAME = 'submit-offer';

// Check whether $user may submit an offer on $offerAuction.
// Returns true (passes) or false (denies).
// Must NOT modify any record in any table.
// Must use DB::table(), not Eloquent, for all database queries.
// Must check Schema::hasTable() before querying any table that may be absent.
public function check(User $user, OfferAuction $offerAuction): bool
```

**Behavior rules (see also Section 11):**
- When `config('offer_system.gating_enabled')` is `false`, return `true` for any authenticated user (development/testing bypass).
- When `config('offer_system.gating_enabled')` is `true`, check for an active agent-hire relationship for the target `offer_auction_id` using raw `DB::table()` calls only.
- Always check `Schema::hasTable()` before querying any table that is not guaranteed to exist in all environments.
- Read-only. No INSERT, UPDATE, or DELETE in any gate check.

---

## Section 9 — Event Log Write Rules

All of the following rules are non-negotiable. A code review checklist item must verify each one before Phase 1A is approved.

### Append-Only
- No `UPDATE` or `DELETE` statement may ever target `offer_event_logs` from application code.
- The `OfferStateService` must only call `OfferEventLog::create()` or `DB::table('offer_event_logs')->insert()`.
- Soft deletes must not be used on this model.

### No `updated_at` Column
- The `offer_event_logs` table has no `updated_at` column (not created in migration).
- The `OfferEventLog` model declares `public const UPDATED_AT = null`.

### Transactional Coupling
- Every state transition writes exactly one event log row atomically with the status update on the `offers` table.
- Both writes are wrapped in `DB::transaction()`.
- If the event log `insert` fails, the `offers.status` update rolls back.
- If the `offers.status` update fails, the event log `insert` does not commit.

### Event Types

| `event_type` value | When written |
|-------------------|--------------|
| `offer_created` | When a new `draft` offer record is first created |
| `offer_submitted` | When `draft → submitted` transition succeeds |
| `offer_accepted` | When `submitted → accepted` transition succeeds |
| `offer_rejected` | When `submitted → rejected` transition succeeds |
| `offer_withdrawn` | When `submitted → withdrawn` transition succeeds |
| `offer_countered` | Phase 2 only; do not write in Phase 1A |
| `status_changed` | Generic fallback for any future non-semantic state change |

### Required Fields Per Event Row

Every event log row must include:
- `offer_id` — always set; never null
- `event_type` — always set from the table above
- `created_at` — set automatically by Laravel to the current UTC timestamp
- `from_status` — the offer's status before the transition (null for `offer_created`)
- `to_status` — the offer's status after the transition (null for non-state events)
- `actor_id` — the authenticated `User::id` who triggered the event; null only for system-initiated events
- `actor_role` — `submitter`, `listing_owner`, or `system`

Optional fields (set when available):
- `metadata` — JSON array; include rejection reason here, not in a separate column; Phase 2 will add counter offer ID here
- `ip_address` — the submitter's IP address from the request; null for background/system events

### Timestamp Derivation Rule

`accepted_at`, `rejected_at`, and `withdrawn_at` are **not** stored as columns on the `offers` table. They are always derived from `offer_event_logs` by querying for the matching `event_type` and reading `created_at`. The sole exception is `submitted_at`, which is a query-convenience column on `offers` that must always remain consistent with the corresponding event log row.

---

## Section 10 — Listing Snapshot Rules

### When Written
`listing_snapshot` is written **once** — at the `draft → submitted` transition — by `OfferSnapshotService::capture()`, called inside `OfferStateService::submit()`.

### Never Updated After Submission
Once `listing_snapshot` is set to a non-null value, it must never be overwritten. `OfferSnapshotService` must check whether `listing_snapshot` is already non-null before writing and refuse to overwrite it. `OfferStateService::submit()` must verify the offer is currently in `draft` status before calling the snapshot service.

### Minimum Required JSON Fields

```json
{
  "snapshot_at": "<ISO 8601 UTC timestamp at time of submission>",
  "listing_id": "<offer_auctions.id as integer>",
  "listing_title": "<offer_auctions.title>",
  "listing_role": "<'seller' or 'landlord' — sourced from EAV meta>",
  "property_address": "<from EAV meta at snapshot time>",
  "property_type": "<from EAV meta at snapshot time>",
  "listing_status": "<from EAV meta at snapshot time>",
  "preferred_terms": {}
}
```

`preferred_terms` is an empty object in Phase 1A. Phase 1B defines the exact `offer_auction_metas` keys to capture here.

### Phase 1A vs. Phase 1B Responsibility Split

| Responsibility | Phase |
|---------------|-------|
| Reserve `listing_snapshot` JSON column on `offers` | Phase 1A |
| Stub `OfferSnapshotService` with minimum envelope | Phase 1A |
| Define exact EAV keys captured as `preferred_terms` | Phase 1B |
| Write `preferred_terms` values into snapshot | Phase 1B |

### Read-Only Archive

`listing_snapshot` is a read-only archive of what the submitter saw at submission time. It is not a substitute for the live listing record. The `offer_auction_id` FK always points to the live `offer_auctions` row. Systems that need current listing data must read from `offer_auctions` / `offer_auction_metas`, not from `listing_snapshot`.

### No Writes to Listing Side

`OfferSnapshotService` reads `offer_auction_metas` and `offer_auctions` via `DB::table()`. It never calls `insert()`, `update()`, or `delete()` on any listing-side table.

---

## Section 11 — BidYourAgent → BidYourOffer Gating Rules

### Gate Name
`submit-offer` — registered as a named Laravel Gate in a service provider (e.g., `AuthServiceProvider` or a dedicated `OfferServiceProvider`).

### Gating Condition (when `gating_enabled` is `true`)
A user passes the gate if and only if they have an **active agent-hire relationship** attached to the target `offer_auction_id`. The exact table and column structure for this check is read from the existing hiring-flow tables using `DB::table()`.

### Implementation Requirements

| Rule | Specification |
|------|--------------|
| ORM choice | `DB::table()` only. **Never** Eloquent in a Gate callback. Eloquent `$with` eager-load can abort PostgreSQL transactions on query error, poisoning subsequent writes. |
| Table existence guard | Before querying any table that may be absent in some environments (e.g., test DBs), call `Schema::hasTable('table_name')`. Return `false` (deny) if the table does not exist. |
| Hiring flow data | Read-only. Never INSERT, UPDATE, or DELETE hiring flow records from within the gate. |
| Gate return | `true` to pass, `false` to deny. Never throw exceptions from the gate itself; callers handle denial. |

### `offer_system.gating_enabled` Config

| Environment | Value | Effect |
|-------------|-------|--------|
| `local` | `false` | Gate passes all authenticated users; BidYourAgent check skipped |
| `testing` | `false` | Gate passes all authenticated users; tests can run without full hiring flow |
| `staging`, `production`, all others | `true` | Full BidYourAgent hire relationship check enforced |

### Redirect Behavior on Gate Failure

When the gate fails (returns `false`):
- The user must be redirected to the **BidYourAgent hiring flow** for the relevant listing type.
- The redirect must **not** reveal that an offer system exists.
- No offer-system URL, error message, or UI element is exposed until Phase 1B is complete.

Note: Phase 1A has no routes or controllers, so no redirect logic is implemented in Phase 1A. This rule is documented here so Phase 1B implements the redirect correctly from the start.

### Read-Only Constraint

The `OfferAccessGate` is read-only. No write to any table may occur inside or as a side effect of the gate check.

---

## Section 12 — Config Recommendation

### File Location
`config/offer_system.php`

### Contents

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Offer System Gating
    |--------------------------------------------------------------------------
    |
    | When true, the submit-offer gate enforces a full BidYourAgent hire-
    | relationship check before a buyer or tenant may submit an offer.
    |
    | Must be true in production before Phase 1B ships.
    | Should be false in local and testing environments to allow Phase 1A
    | tables and models to be tested without a complete hiring flow setup.
    |
    */
    'gating_enabled' => ! in_array(
        app()->environment(),
        ['local', 'testing']
    ),
];
```

### Key: `offer_system.gating_enabled`

| Setting | Default value | Rule |
|---------|--------------|------|
| `local` / `testing` | `false` | Gate passes all authenticated users |
| All other environments | `true` | Full BidYourAgent relationship check enforced |

### Phase 1B Sign-Off Requirement

Before Phase 1B is approved for production deployment, a checklist item must confirm:
- `config('offer_system.gating_enabled')` returns `true` in the production config
- The production config file or environment variable override explicitly sets this to `true`

---

## Section 13 — Unit Test Plan

All tests below must exist and pass before Phase 1A is approved. No test may mock the gate in a way that allows offers to bypass the gating check in production-mode tests.

### 13.1 Valid Transition Tests

One test per allowed Phase 1A transition:

| Test case | Transition | Expected outcome |
|-----------|------------|-----------------|
| `test_draft_can_be_submitted` | `draft → submitted` | Offer status becomes `submitted`; `submitted_at` is set; event log row written |
| `test_submitted_can_be_accepted` | `submitted → accepted` | Offer status becomes `accepted`; event log row written |
| `test_submitted_can_be_rejected` | `submitted → rejected` | Offer status becomes `rejected`; event log row written |
| `test_submitted_can_be_withdrawn` | `submitted → withdrawn` | Offer status becomes `withdrawn`; event log row written |

### 13.2 Forbidden Transition Tests

One test per forbidden attempt, asserting `OfferTransitionException` is thrown:

| Test case | Attempt | Asserts |
|-----------|---------|---------|
| `test_accepted_cannot_transition_to_any` | `accepted → submitted` (and any) | `OfferTransitionException` thrown |
| `test_rejected_cannot_transition_to_any` | `rejected → submitted` (and any) | `OfferTransitionException` thrown |
| `test_withdrawn_cannot_transition_to_any` | `withdrawn → submitted` (and any) | `OfferTransitionException` thrown |
| `test_draft_cannot_skip_to_accepted` | `draft → accepted` | `OfferTransitionException` thrown |
| `test_draft_cannot_skip_to_rejected` | `draft → rejected` | `OfferTransitionException` thrown |
| `test_draft_cannot_skip_to_withdrawn` | `draft → withdrawn` | `OfferTransitionException` thrown |
| `test_draft_cannot_transition_to_countered` | `draft → countered` | `OfferTransitionException` thrown |
| `test_submitted_cannot_transition_to_countered_in_phase_1a` | `submitted → countered` | `OfferTransitionException` thrown (Phase 2 transition must not be callable) |

### 13.3 Event Log Creation Tests

| Test case | Verifies |
|-----------|---------|
| `test_submit_writes_event_log_row` | A single `offer_submitted` row exists in `offer_event_logs` after `submit()` is called |
| `test_accept_writes_event_log_row` | A single `offer_accepted` row exists after `accept()` is called |
| `test_reject_writes_event_log_row` | A single `offer_rejected` row exists after `reject()` is called |
| `test_withdraw_writes_event_log_row` | A single `offer_withdrawn` row exists after `withdraw()` is called |
| `test_failed_event_log_write_rolls_back_status_change` | If the event log insert is forced to fail (e.g., by simulating a DB exception inside the transaction), the `offers.status` is not updated; the offer remains in its original state |

### 13.4 Listing Snapshot Immutability Tests

| Test case | Verifies |
|-----------|---------|
| `test_snapshot_is_written_at_submission` | `listing_snapshot` is non-null after `submit()` |
| `test_snapshot_is_not_overwritten_on_second_call` | Calling the snapshot service a second time on the same offer does not overwrite the existing `listing_snapshot` |
| `test_snapshot_contains_minimum_required_fields` | Returned snapshot array contains `snapshot_at`, `listing_id`, `listing_title`, `listing_role`, `property_address`, `property_type`, `listing_status`, and `preferred_terms` keys |
| `test_snapshot_fields_match_live_listing_at_submission_time` | The snapshot's `listing_id` matches `offer.offer_auction_id`; `snapshot_at` is close to the current timestamp |

### 13.5 Access Gate Behavior Tests

| Test case | Setup | Asserts |
|-----------|-------|---------|
| `test_gate_passes_when_gating_disabled` | `offer_system.gating_enabled = false` | Authenticated user passes the gate regardless of hire relationship |
| `test_gate_passes_user_with_qualifying_hire_relationship` | `offer_system.gating_enabled = true`; user has active hire relationship for target listing | Gate returns `true` |
| `test_gate_fails_unauthenticated_user` | No authenticated user | Gate returns `false` |
| `test_gate_fails_user_without_hire_relationship` | `offer_system.gating_enabled = true`; user has no hire relationship for target listing | Gate returns `false` |

---

## Section 14 — Verification Checklist Before Phase 1A Code Is Approved

Every item below must be manually verified and checked off before a Phase 1A pull request is approved. Automated checks are noted where applicable.

### Source Document Review
- [ ] `docs/OFFER_SYSTEM_GOVERNANCE.md` has been read in full by the implementing agent
- [ ] `docs/OFFER_SYSTEM_BUILD_ORDER.md` has been read in full
- [ ] `docs/OFFER_SYSTEM_DO_NOT_TOUCH.md` has been read in full
- [ ] `docs/OFFER_ARCHITECTURE_FOUNDATION_AUDIT.md` has been read in full

### Migration Safety
- [ ] `php artisan migrate --pretend` was run on all three migrations before executing
- [ ] Pretend output contains no `ALTER TABLE` on any existing table
- [ ] Pretend output contains no `DROP` statement
- [ ] Pretend output contains no `RENAME COLUMN` statement
- [ ] Only `offers`, `offer_metas`, and `offer_event_logs` are new tables
- [ ] `offer_event_logs` migration does not call `$table->timestamps()`; only `created_at` exists
- [ ] Self-referencing FK on `offers.parent_offer_id` is added after the table is created (second Schema call)
- [ ] All three `down()` methods drop tables in reverse order: `offer_event_logs` → `offer_metas` → `offers`
- [ ] No existing table was altered

### Model Correctness
- [ ] `OfferEventLog` declares `public const UPDATED_AT = null`
- [ ] `Offer.$casts` includes `listing_snapshot => 'array'`
- [ ] `OfferMeta` has explicit `$fillable`; no `$guarded = []`
- [ ] `Offer` has explicit `$fillable`; no `$guarded = []`
- [ ] `parentOffer()` relationship is defined on `Offer` even though it will always be null in Phase 1A

### Service Correctness
- [ ] `OfferStateService` uses `DB::transaction()` around every status update + event log insert pair
- [ ] `OfferStateService` throws `OfferTransitionException` (typed, named class) on every forbidden attempt
- [ ] No public method on `OfferStateService` accepts or produces `countered` status as a target in Phase 1A
- [ ] `OfferSnapshotService` never calls `insert()`, `update()`, or `delete()` on `offer_auction_metas` or `offer_auctions`
- [ ] `OfferSnapshotService` refuses to overwrite a non-null `listing_snapshot`

### Gate Correctness
- [ ] `OfferAccessGate` uses `DB::table()` only; no Eloquent calls inside the gate
- [ ] `OfferAccessGate` calls `Schema::hasTable()` before querying any optionally-present table
- [ ] `OfferAccessGate` performs no write operations
- [ ] `config/offer_system.php` exists with `gating_enabled` key
- [ ] `gating_enabled` defaults to `false` for `local` and `testing` environments
- [ ] `gating_enabled` defaults to `true` for all other environments

### UI Boundary
- [ ] No Livewire component was created
- [ ] No Blade view was created
- [ ] No route was added to `web.php` or `api.php`
- [ ] No controller was created

### Testing
- [ ] All Phase 1A unit tests listed in Section 13 exist and pass
- [ ] No test mocks the gate in a way that bypasses the production check in production-mode tests
- [ ] No test uses `countered` status as a convenience value before Phase 2 transition logic exists
- [ ] No test factory or seeder modifies `offer_auctions`, `offer_auction_metas`, or any existing table

### Exception Class
- [ ] `App\Exceptions\OfferTransitionException` class exists as a named, catchable exception

---

## Section 15 — Explicit Out-of-Scope List for Phase 1A

### UI — Phase 1B or Later
Any of the following appearing in a Phase 1A pull request is a rejection-worthy violation:

- Livewire offer submission wizard or form components
- Blade views for offer creation, offer status, or offer detail
- Routes for offer submission, offer viewing, or offer management
- Any buyer-facing or tenant-facing offer UI
- CSS, JavaScript, or AlpineJS for offer-related interfaces

### Business Logic — Later Phases
Forbidden in Phase 1A regardless of implementation complexity:

| Item | Phase |
|------|-------|
| Counter-offer creation, review, or acceptance | Phase 2 |
| Counter-offer state transition logic (even as a private method) | Phase 2 |
| Any callable path that transitions an offer INTO `countered` | Phase 2 |
| Offer comparison view or grid | Phase 3 |
| Accepted Offer Summary generation and persistence | Phase 4 |
| PDF export of offer summaries | Phase 5 |
| E-sign integration | Phase 6 |
| AI analysis, explanation, or comparison of offers | Phase 7 |
| Expiry processing for `expires_at` | Not yet scoped |

### Platform Systems — Never Modified by the Offer System

The following must not be changed by any phase of the Offer System without separate explicit approval:

| Protected area | Protection reason |
|---------------|------------------|
| `offer_auctions` table / `offer_auction_metas` table | Listing side; read-only from offer system perspective |
| Any existing listing creation form (seller, buyer, landlord, tenant) | Protected in `OFFER_SYSTEM_DO_NOT_TOUCH.md` §1 |
| `initializeLimitedService()` in any listing Blade file | Frozen legacy function; see `replit.md` |
| `accepted_bid_summaries` table, `AcceptedBidSummary` model | Protected in §4 |
| `x-bid-detail-layout` Blade component | Protected in §4 |
| PDF cache invalidation logic for bid summaries | Protected in §4 |
| BidYourAgent Hire Me URLs, widget, `AgentBidMapperService`, `AgentDefaultProfile` | Protected in §5 |
| Agent Hire Listings Hub (`/agent/hire-listings`) | Protected in §5 |
| `referral_visits` table, My Referrals page, referral percentage fields | Protected in §6 |
| Property DNA, Buyer/Tenant DNA, Location DNA | Protected in §7 |
| Any existing OpenAI prompt contract or response-parsing logic | Protected in §7 |
| Any existing EAV meta key in any `*_metas` table | Protected in §3 |
| Any existing database column anywhere in the schema | Additive-only rule — §8 and §9 |

---

*End of Phase 1A Implementation Plan. No application code, migration, model, service, route, Livewire component, or Blade view was created as part of producing this document.*
