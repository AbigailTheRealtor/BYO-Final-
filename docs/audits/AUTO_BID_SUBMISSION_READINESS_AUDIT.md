# Auto-Bid Submission Readiness Audit

**Produced:** 2026-06-16  
**Auditor:** Automated review (task agent)  
**Scope:** All four auction types — Property/Buyer, Seller Agent, Landlord Agent, Tenant Agent  
**Audit boundary:** `routes/web.php`, `app/Console/Kernel.php`, `app/Http/Controllers/HireAgentDirectController.php`, `app/Http/Controllers/PropertyAuctionBidController.php`, `app/Http/Controllers/LandlordAuctionController.php`, `app/Http/Controllers/CounterBidController.php`, `app/Http/Controllers/PropertyAuctionController.php`, `app/Jobs/ComputeCompatibilityScore.php`, `app/Models/ListingCompatibilityScore.php`, `resources/views/landlord_auction/add-bid.blade.php`  
**Purpose:** Establish an honest, concrete baseline of what auto-bid submission infrastructure exists, what is missing, and what must be resolved before any auto-submit feature is turned on in production.

---

## Contents

1. [Executive Summary](#1-executive-summary)
2. [Section A — Traditional Bot-Bidder System](#2-section-a--traditional-bot-bidder-system)
3. [Section B — Hire-Me Auto-Bid Marker](#3-section-b--hire-me-auto-bid-marker)
4. [Section C — Stored Auto-Bid Fields with No Execution Engine](#4-section-c--stored-auto-bid-fields-with-no-execution-engine)
5. [Section D — Commented-Out Artisan Commands](#5-section-d--commented-out-artisan-commands)
6. [Section E — Compatibility Scoring System and Its Non-Connection to Auto-Submit](#6-section-e--compatibility-scoring-system-and-its-non-connection-to-auto-submit)
7. [Section F — Gap Analysis: Five Required Conditions](#7-section-f--gap-analysis-five-required-conditions)
8. [Section G — Safety and Operational Gap Analysis](#8-section-g--safety-and-operational-gap-analysis)
9. [Section H — Readiness Verdict Table](#9-section-h--readiness-verdict-table)

---

## 1. Executive Summary

**Overall verdict: ❌ NOT PRODUCTION SAFE — no auction type.**

The platform has two distinct concepts that both use the term "auto-bid": (1) a legacy bot-bidder route that places synthetic bids from fake bot accounts on `PropertyAuction` listings, and (2) user-facing "autobid" preference fields stored on human bids across all four auction types. Neither concept constitutes a production-ready auto-submit engine.

The bot-bidder is triggered by an unauthenticated GET request to `/test` with no schedule, no queue, no rate limiting, no match score gate, no subscription gate, and no geography constraint. It is a prototype that has never been removed from the route file. Three Artisan command classes referenced in commented-out schedule entries (`AutoBid`, `SellerAutocounter`, `BuyerAutocounter`) do not exist on disk. The compatibility scoring system (`ComputeCompatibilityScore` job, `listing_compatibility_scores` table) stores scores but nothing reads them in any auto-submit path. No automatic bid placement logic exists for the Seller Agent, Landlord Agent, or Tenant Agent auction types at all.

---

## 2. Section A — Traditional Bot-Bidder System

### A.1 Location

`routes/web.php`, lines 1047–1103, route name `test`.

### A.2 Trigger

An unauthenticated HTTP GET request to `/test`. There is no middleware, no authentication check, no CSRF protection, and no IP allowlist on this route. Any external party who knows the URL can trigger bot bids.

There is **no scheduled trigger**. The `Kernel.php` schedule contains a commented-out entry:

```php
// $schedule->command('autoBid')->everyMinute();
// $schedule->command('autoBid')->everyThirtyMinutes();
```

The `AutoBid` Artisan command class (`app/Console/Commands/AutoBid.php`) **does not exist**. The schedule entry is inert even if uncommented.

### A.3 Conditions Checked

| Check | Field / Logic |
|-------|---------------|
| Listing opt-in | `PropertyAuction.auto_bid = 1` |
| Not sold | `PropertyAuction.sold = 0` |
| No sold date | `PropertyAuction.sold_date IS NULL` |
| Bot user exists | `User::where('user_type', 'bot')->count() > 0` |

A random bot user is selected with `rand(1, $totalBoots)` and `skip($randomNumber - 1)->take(1)->first()`.

### A.4 Price-Increment Logic

The route checks three price thresholds from the `property_auctions` table native columns:

- `autobid_price` — described as the minimum price floor
- `autobid_price2` — described as the reserve price
- `autobid_price3` — described as the buy-now price

The first threshold that exceeds the current maximum human bid price (`max(bids.price)` where `auto_bid_record != 1`) becomes the bot bid price. If none of the three thresholds exceeds the current maximum bid, **no bid is placed** and the route exits silently with no response body.

### A.5 Notable Code Issues

- `autobid_maximum_price` is saved as the string literal `"null"` (not PHP `null`):  
  ```php
  $new_propertyAuction_bid->autobid_maximum_price = "null";
  ```
- `auto_bid_record` is saved as integer `0`, but bids are filtered by `!= 1`, making the semantics non-obvious — any non-`1` value (including `0`, `false`, `null`) passes the filter.
- The route does not return a response body; it implicitly returns an empty 200.
- Multiple `saveMeta` calls write hardcoded values (e.g. `'financing' => 'Cash'`, `'video_url' => 'https://www.koqy.mobi'`) that are unrelated to any real bidder preference.
- The expiration date of the listing is advanced by one day on every successful bot bid (`PropertyAuctionMeta` update of `expiration_date`), which is not guarded against overflow.

### A.6 Auction Types Covered

Only `PropertyAuction` listings (the property/buyer auction type). No bot-bidder logic exists for Seller Agent, Landlord Agent, or Tenant Agent auctions.

---

## 3. Section B — Hire-Me Auto-Bid Marker

### B.1 What It Is

When a client completes the "Hire Me / Hire This Agent" flow via `HireAgentDirectController::acknowledgeSubmit()` (POST `/hire/agent/direct/{agentId}/{role}/{propertyType}/acknowledge`), the controller saves the following EAV meta key on the created bid:

```php
$bid->saveMeta('hire_me_auto_bid', '1');
```

This meta key is visible at `app/Http/Controllers/HireAgentDirectController.php`, line 1131.

### B.2 What It Is Not

This is a **label** applied to a bid that was created through a human-initiated, session-protected, multi-step confirmation flow. It marks the bid as having originated from the Hire Me pathway rather than from the standard bid form. It is **not** an autonomous submission engine — no daemon, scheduler, job, or route reads `hire_me_auto_bid = 1` to trigger any further automated action.

### B.3 Validations Present in HireAgentDirectController

| Validation | Status |
|-----------|--------|
| Agent identity (`user_type = 'agent'`) | ✅ Enforced |
| Role validity (`VALID_ROLES` allowlist) | ✅ Enforced |
| Property type validity (`VALID_PROPERTY_TYPES` allowlist) | ✅ Enforced |
| Preset existence for role + property type | ✅ Enforced |
| Preset has at least one service configured | ✅ Enforced |
| One-time submit token (anti-replay) | ✅ Enforced |
| One-time acknowledgment nonce | ✅ Enforced |
| Agent cannot hire themselves | ✅ Enforced |
| Client contact fields (name, phone, email) | ✅ Validated |

### B.4 Validations Absent from HireAgentDirectController

| Missing Validation | Impact |
|-------------------|--------|
| Subscription / payment status check | Agent with expired subscription can receive hires |
| Radius / geography constraint | Agent can be hired for a property in any location |
| Match score threshold | No minimum compatibility score gates the hire submission |
| Listing status check (is_draft, is_approved, expiry) | Hire can be created against an effectively inactive listing context |

---

## 4. Section C — Stored Auto-Bid Fields with No Execution Engine

### C.1 Landlord Agent Auction Bids

The bid form at `resources/views/landlord_auction/add-bid.blade.php` presents three autobid-preference fields:

| Field (name attribute) | Label context | EAV meta key saved by |
|-----------------------|---------------|----------------------|
| `autobid_price` | Escalating bid price | `LandlordAuctionController` (inferred from form name parity) |
| `autobid_days_start_date` | Days from which auto-bid starts | `LandlordAuctionController` |
| `autobid_lease_length` | Preferred lease length for auto escalation | `LandlordAuctionController` |

The UI implies escalation behavior ("increase by $50 over the highest offer"). **No service, job, or command reads these fields to place automatic bids.** They are persisted as EAV meta and displayed to the landlord but never acted upon by any server-side processor.

### C.2 Property Auction Bids (Buyer / Seller Property)

`PropertyAuctionBidController::savePABid()` and `CounterBidController::store()` both save the following autobid-related EAV meta keys:

| EAV meta key | Description |
|-------------|-------------|
| `autobid_price` | Buyer's escalation price target |
| `autobid_escrow_deposit` | Preferred escrow deposit for auto-escalation |
| `autobid_contingency` | Contingency preference |
| `autobid_inspection` | Inspection preference |
| `autobid_appraisal` | Appraisal preference |
| `autobid_finance` | Finance contingency preference |
| `autobid_saleContingency` | Sale contingency preference |
| `autobid_offered_contingency` | Offered contingency flag |
| `autobid_offered_contingency_days` | Number of contingency days |
| `autobid_closing_date` | Preferred closing date for auto-escalation |

Additionally, the `PropertyAuction` listing itself stores native column values:

| Native column | Stored in | Purpose |
|--------------|-----------|---------|
| `autobid_price` | `property_auctions` | Minimum threshold for bot-bidder (listing-level) |
| `autobid_price2` | `property_auctions` | Reserve threshold for bot-bidder |
| `autobid_price3` | `property_auctions` | Buy-now threshold for bot-bidder |

**No service, job, or command reads the bid-level EAV autobid fields** (`autobid_escrow_deposit`, `autobid_contingency`, etc.) to place automatic bids or modify existing bids. The listing-level `autobid_price`, `autobid_price2`, `autobid_price3` are read exclusively by the `/test` bot-bidder route.

### C.3 Seller Agent and Tenant Agent Auction Bids

No autobid storage fields or UI were found in the Seller Agent or Tenant Agent bid forms. These auction types have no autobid infrastructure at all — no stored fields and no execution engine.

---

## 5. Section D — Commented-Out Artisan Commands

### D.1 Commands Referenced in Kernel.php

`app/Console/Kernel.php` contains the following commented-out entries:

```php
// protected $commands = [\App\Console\Commands\AutoBid::class,];
// protected $commands = [\App\Console\Commands\SellerAutocounter::class, \App\Console\Commands\BuyerAutocounter::class];
```

```php
// $schedule->command('autoBid')->everyMinute();
// $schedule->command('expirationDate')->everyMinute();
// $schedule->command('autoBid')->everyThirtyMinutes();
// $schedule->command('seller:autocounter')->everyMinute();
// $schedule->command('buyer:autocounter')->everyMinute();
```

### D.2 File Existence Check

| Class | Expected path | File exists? |
|-------|--------------|:------------:|
| `App\Console\Commands\AutoBid` | `app/Console/Commands/AutoBid.php` | ❌ No |
| `App\Console\Commands\SellerAutocounter` | `app/Console/Commands/SellerAutocounter.php` | ❌ No |
| `App\Console\Commands\BuyerAutocounter` | `app/Console/Commands/BuyerAutocounter.php` | ❌ No |

All three command class files are absent from `app/Console/Commands/`. The schedule entries are inert — uncommenting them would cause a fatal class-not-found error at boot.

### D.3 Commands That Do Exist

The following auto-bid-adjacent command exists and is registered:

| Command | File | Schedule |
|---------|------|---------|
| `offers:expire-pending` | `app/Console/Commands/ExpireOffersCommand.php` | `everyMinute()` (active) |

This command handles offer expiry, not auto-bid submission.

---

## 6. Section E — Compatibility Scoring System and Its Non-Connection to Auto-Submit

### E.1 What Exists

`app/Jobs/ComputeCompatibilityScore.php` is a queued job (`ShouldQueue`) that:

- Loads an active `PropertyDnaProfile` (supply side) and an active `BuyerTenantDnaProfile` (demand side)
- Calls `CompatibilityEngine::compute()` to produce a multi-dimensional score
- Persists an append-only row to `listing_compatibility_scores` with the following scored columns:

| Column | Type | Description |
|--------|------|-------------|
| `overall_score` | `decimal:2` | Compatibility coverage metric (non-unresolved dimensions / 8 × 100) |
| `physical_match_score` | `decimal:2` | Physical dimension sub-score |
| `financial_match_score` | `decimal:2` | Financial dimension sub-score |
| `terms_match_score` | `decimal:2` | Terms dimension sub-score |
| `location_match_score` | `decimal:2` | **Always `null`** — Location DNA Phase 2 not yet implemented |
| `deal_breaker_triggered` | `boolean` | True if any conflicting dimensions were detected |
| `deal_breaker_flags` | `array` | Array of conflicting dimension identifier strings |

The job uses a PostgreSQL advisory lock to prevent concurrent races and an append-only versioning pattern (prior row is archived before a new row is inserted).

### E.2 What Is Explicitly Missing

**No auto-submit path reads `listing_compatibility_scores` in any form.**

- The `/test` bot-bidder route does not query `listing_compatibility_scores`.
- `HireAgentDirectController` does not query `listing_compatibility_scores`.
- No Artisan command, queued job, observer, or policy reads `overall_score`, `deal_breaker_triggered`, or `deal_breaker_flags` to gate or trigger bid submission.
- `location_match_score` is always `null` — a location-based gate could not function even if wired.

The compatibility scoring system is a **read-only audit layer** that computes and stores scores for display purposes. It is entirely disconnected from the bid submission pipeline.

---

## 7. Section F — Gap Analysis: Five Required Conditions

The following table states the status of each production-readiness condition against both execution paths (bot-bidder and Hire Me):

| Condition | Status | Detail |
|-----------|:------:|--------|
| **Minimum match score threshold** | ❌ MISSING | No score floor gates bot bids or Hire Me submissions. `overall_score` is stored in `listing_compatibility_scores` but is never read in any auto-submit path. `deal_breaker_triggered` is also ignored. |
| **Radius / geography check** | ❌ MISSING | The bot-bidder has no location check of any kind. Hire Me validates `VALID_PROPERTY_TYPES` (an allowlist of property type strings) but has no radius constraint, distance calculation, or geography matching. |
| **Property type filtering** | ⚠️ PARTIAL | Hire Me enforces `VALID_PROPERTY_TYPES = ['residential', 'income', 'commercial', 'business', 'vacant_land']`. The bot-bidder has no property type check — it bids on any `PropertyAuction` record with `auto_bid = 1` regardless of property type. |
| **Active / listing status check** | ⚠️ PARTIAL | The bot-bidder checks `sold = 0` and `sold_date IS NULL`. It does not check `is_draft`, `is_approved`, listing expiry, or whether the listing is active in any workflow sense. Hire Me does not check listing status at all — the hire flow creates a new listing from scratch rather than targeting an existing one, so it cannot validate against an existing listing's status. |
| **Subscription / payment status** | ❌ MISSING | No subscription or payment gate exists on any auto-submit path. Neither the bot-bidder nor Hire Me checks whether the bidding user, the bot user, or the agent has an active subscription or payment method on file. |

---

## 8. Section G — Safety and Operational Gap Analysis

The following additional gaps are production-blocking and must be resolved before enabling any auto-submit feature:

### G.1 Unauthenticated Trigger Route

The `/test` route has no middleware (`auth`, `verified`, `throttle`, or otherwise). Any external party with knowledge of the route name can trigger bot bid insertion via a plain HTTP GET request, including from automated scanners.

**Required:** Remove the route from production or protect it with an IP allowlist and a signed URL / secret header.

### G.2 No Rate Limiting

Neither the bot-bidder nor Hire Me has a rate-limiting guard on bid creation. Rapid repeated requests to `/test` (or replay attacks on the Hire Me acknowledge form, which is protected by a one-time nonce but not rate-limited at the route level) could flood the `property_auction_bids` table.

**Required:** Add `throttle` middleware or application-level rate limiting before any auto-submit path goes live.

### G.3 No Deduplication Guard

The bot-bidder has no deduplication check. If triggered twice in rapid succession for the same listing, two identical bot bids at the same price will be inserted. The Hire Me flow uses a one-time nonce to prevent replay, but this does not protect against parallel concurrent requests arriving before the nonce is consumed.

**Required:** A database-level unique constraint or advisory lock on auto-bid insertion per listing per time window.

### G.4 No Queue Worker Configuration for the Bot-Bidder

The `/test` route executes synchronously in the request cycle. There is no queue dispatch, no `ShouldQueue` implementation, and no retry logic. Under load, a single request could block the PHP-FPM worker for the duration of all bid insertions.

**Required:** If auto-bidding is to scale beyond a single listing, the execution must be moved to a queued job with proper `$tries` and `$timeout` limits.

### G.5 No Audit Log of Auto-Placed Bids

Neither the bot-bidder nor any auto-submit path writes to a dedicated audit log. Auto-placed bids are indistinguishable in the `property_auction_bids` table from human bids (except for the `auto_bid_record = 0` column, which is also set on bot bids and not on human bids — making it semantically identical and useless as a distinguishing marker).

**Required:** A dedicated `auto_bid_log` table or a reliable `source` column on `property_auction_bids` with values such as `'bot'`, `'hire_me'`, `'human'` before any auto-submit feature is enabled.

### G.6 `autobid_maximum_price` Stored as String `"null"`

In the bot-bidder at `routes/web.php:1084`:

```php
$new_propertyAuction_bid->autobid_maximum_price = "null";
```

The literal string `"null"` is written to the `autobid_maximum_price` column on every bot-placed bid. Any downstream code that reads this column with a numeric comparison or `is_null()` check will receive incorrect results — the string `"null"` is truthy in PHP and will not equal `null`.

**Required:** Fix to PHP `null` before any processing logic reads this field.

### G.7 `auto_bid_record` Flag Semantics

Bot bids are saved with `auto_bid_record = 0`. Human bids from the standard bid form also default to `0` (the column is not set in `savePABid`). The bot-bidder filters bids with `where('auto_bid_record', '!=', 1)`, meaning it treats both human bids and bot bids as part of the "human" bid pool when computing the maximum bid price. This means a bot bid can be used as the baseline for computing the next bot bid — creating a self-escalating loop if `/test` is called repeatedly.

**Required:** Establish a clear and consistently applied sentinel value (e.g. `auto_bid_record = 1` for bot bids, `0` for human bids) and filter accordingly.

---

## 9. Section H — Readiness Verdict Table

| Auction Type | Auto-Submit Engine Exists | Trigger Defined | Match Score Gate | Radius Gate | Active Status Gate | Subscription Gate | Production Safe |
|--------------|:------------------------:|:---------------:|:----------------:|:-----------:|:-----------------:|:-----------------:|:---------------:|
| **Property / Buyer** | ⚠️ Partial (bot route only) | ❌ No schedule; unauthenticated GET `/test` | ❌ No | ❌ No | ⚠️ Partial (`sold=0`, `sold_date IS NULL` only) | ❌ No | ❌ No |
| **Seller Agent** | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No |
| **Landlord Agent** | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No |
| **Tenant Agent** | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No |

> **Note on Hire Me:** The Hire Me flow (`HireAgentDirectController`) creates agent bids across all four auction types and marks them with `hire_me_auto_bid = 1`. It is not an autonomous engine — it requires a human client to initiate and confirm the flow. It is included in Section B for completeness but is not represented as an "auto-submit engine" in the table above.

---

## Appendix: Known Missing Command Files

| Command handle | Referenced in | File path | Exists |
|---------------|--------------|-----------|:------:|
| `autoBid` | `Kernel.php` (commented-out schedule) | `app/Console/Commands/AutoBid.php` | ❌ |
| `seller:autocounter` | `Kernel.php` (commented-out schedule) | `app/Console/Commands/SellerAutocounter.php` | ❌ |
| `buyer:autocounter` | `Kernel.php` (commented-out schedule) | `app/Console/Commands/BuyerAutocounter.php` | ❌ |
| `expirationDate` | `Kernel.php` (commented-out schedule) | *(unknown)* | ❌ |

These entries would cause a fatal boot error if their comment markers were removed.
