# Landlord Counter Terms — Broker Compensation Field Audit
**Task:** #566 — Fix landlord counter terms screen so all preset-filled broker compensation fields are visible and survive a save/reopen round-trip.
**Date:** 2026-05-06
**Scope:** `/landlord/counter-terms/{id}` — `LandlordAgentAuctionCounterTerm.php` + `broker-compensation.blade.php`

---

## Hydration chain

```
AgentBidMapperService::mapFromProfile()
  → bid meta (landlord_agent_auction_bid_metas)
  → LandlordAgentAuctionCounterTerm::hydrateFromMetaMap()
  → Livewire public property
  → blade isCounterMode guard
  → blade wire:model input (visible to user)
  → LandlordAgentAuctionCounterTerm::saveAllMetaData()
  → landlord_counter_term_metas
```

---

## Field-mapping audit table

| Preset Label / Section | Bid Meta Key | Livewire Property | Blade `wire:model` | Guard Condition (`isCounterMode`) | Mapper | hydrateFromMetaMap | saveAllMetaData | Status |
|---|---|---|---|---|---|---|---|---|
| **Landlord Broker Lease Fee** | | | | | | | | |
| Fee type selector | `purchase_fee_type` | `$purchase_fee_type` | ✅ | ✅ includes `purchase_fee_type` | ✅ | ✅ | ✅ | OK |
| Residential flat / flat_type | `purchase_fee_flat`, `purchase_fee_flat_type` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Residential rental period | `purchase_fee_rental_period` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Residential combo % + flat | `purchase_fee_percentage_combo`, `purchase_fee_flat_combo` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Residential other | `purchase_fee_other` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial net aggregate | `purchase_fee_net_aggregate` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial gross rent | `purchase_fee_gross_rent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial monthly % + months | `purchase_fee_monthly_percentage`, `purchase_fee_months` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial flat | `purchase_fee_flat_commercial` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial purchase price % | `purchase_fee_purchase_price` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial other | `purchase_fee_other_commercial` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Sales tax options (gross/flat/monthly) | `sales_tax_option_gross`, `sales_tax_option_flat`, `sales_tax_option_monthly` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Tenant's Broker Commission** | | | | | | | | |
| Commission structure | `tenant_broker_commission_structure` | ✅ | ✅ | ✅ includes all sub-fields | ✅ | ✅ | ✅ | OK |
| Fee structure | `tenant_broker_fee_structure` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Percentage / gross lease / first month rent / flat fee / other | `tenant_broker_percentage`, `tenant_broker_gross_lease`, `tenant_broker_first_month_rent`, `tenant_broker_flat_fee`, `tenant_broker_other` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Lease fee flat | `lease_fee_flat` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Payment Timing for Broker Fees** | | | | | | | | |
| Timing selector (residential) | `broker_fee_timing` | ✅ | ✅ | ✅ (anchor field) | ✅ | ✅ | ✅ | OK |
| Days from first month's rent | `broker_fee_days_from_rent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Days after lease execution | `broker_fee_days_after_lease` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Days after rent payment | `broker_fee_days_after_rent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Other timing description | `broker_fee_timing_other` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Split payment due (commercial) | `split_payment_due` | ✅ | *(no UI input — backend only)* | **FIXED** ✅ added to guard | ✅ | ✅ | ✅ | Fixed |
| Split payment due other | `split_payment_due_other` | ✅ | *(no UI input — backend only)* | **FIXED** ✅ added to guard | ✅ | ✅ | ✅ | Fixed |
| Days after due event | `broker_fee_days_after_due_event` | ✅ | *(no UI input — backend only)* | **FIXED** ✅ added to guard | **FIXED** ✅ added to mapper | ✅ | ✅ | Fixed |
| **Lease Renewal/Extension Fee** | | | | | | | | |
| Fee type selector | `renewal_fee_type` | ✅ | ✅ | ✅ includes all sub-fields | ✅ | ✅ | ✅ | OK |
| Residential sub-fields (% / flat / custom) | `renewal_fee_percentage`, `renewal_fee_lease_value`, `renewal_fee_first_month`, `renewal_fee_flat_free`, `renewal_fee_custom` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| Commercial extras (sales tax, months) | `renewal_fee_sales_tax_lease_value`, `renewal_fee_no_of_months`, `renewal_fee_sales_tax_first_month`, `renewal_fee_sales_tax_flat_fee` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Expansion Commission (Commercial)** | `expansion_commission_percentage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Property Management** | | | | | | | | |
| Interested toggle + fee type + sub-fields | `interested_in_property_management`, `interested_in_property_management_fee`, `*_gross_lease`, `*_rental_periord`, `*_flate_free`, `*_other` | ✅ | ✅ | ✅ all covered | ✅ | ✅ | ✅ | OK |
| **Lease-Option Agreement** | | | | | | | | |
| Toggle + lease/purchase type + values | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Interested in Selling** | | | | | | | | |
| Toggle + selling type + all price fields | `interested_in_selling`, `interested_in_selling_type`, `landlord_broker_purchase_price`, `landlord_broker_percentage_price`, `landlord_broker_dollar_price`, `landlord_broker_flate_fee`, `landlord_broker_other` | ✅ | ✅ | ✅ all covered | ✅ | ✅ | ✅ | OK |
| **Protection Period** | `protection_period` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Early Termination Fee** | `early_termination_fee_option`, `early_termination_fee_amount` | ✅ | ✅ | ✅ (option is anchor; amount shown nested) | ✅ | ✅ | ✅ | OK |
| **Retainer Fee** | | | | | | | | |
| Toggle | `retainer_fee_option` | ✅ | ✅ | ✅ (was anchor) | ✅ | ✅ | ✅ | OK |
| Amount | `retainer_fee_amount` | ✅ | ✅ (shown when option=yes, or in counter mode if amount has data) | **FIXED** ✅ added to outer guard + inner guard hardened | ✅ | ✅ | ✅ | Fixed |
| Application | `retainer_fee_application` | ✅ | ✅ (shown when option=yes, or in counter mode if application has data) | **FIXED** ✅ added to outer guard + inner guard hardened | ✅ | ✅ | ✅ | Fixed |
| **Agency Agreement Timeframe** | `agency_agreement_timeframe`, `agency_agreement_custom` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Brokerage Relationship** | `brokerage_relationship` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Additional Terms** | `additional_details_broker` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | OK |
| **Referral Fee** | `referral_fee_percent` | ✅ | ✅ (agent-created listings only) | ✅ | ✅ | ✅ | ✅ | OK |

---

## Changes made (Task #566)

### `app/Services/AgentBidMapperService.php`
- **Added** `broker_fee_days_after_due_event` to `mapFromProfile()` return array (under "Landlord: split payment due" section). This field was already present in `hydrateFromMetaMap()` and `saveAllMetaData()` but was missing from the mapper — meaning it was never seeded into bid meta when a preset was applied.

### `resources/views/livewire/landlord-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`
- **Payment Timing guard (line 364):** Added `|| !empty($split_payment_due) || !empty($split_payment_due_other) || !empty($broker_fee_days_after_due_event)` — ensures the Payment Timing block renders in counter mode when only commercial split-payment fields carry data.
- **Retainer Fee outer guard (line 1123):** Added `|| !empty($retainer_fee_amount) || !empty($retainer_fee_application)` — ensures the Retainer Fee block renders when amount or application is set even if the option toggle itself is unexpectedly empty.
- **Retainer Fee inner guard (line 1141):** Changed `@if ($retainer_fee_option === 'yes')` to also render when `$isCounterMode` is true and `$retainer_fee_amount` or `$retainer_fee_application` has data — prevents sub-fields from being hidden in counter mode when legacy data has amount/application without the option toggle.

---

## Verification

```
php -l app/Services/AgentBidMapperService.php
→ No syntax errors detected

php -l app/Http/Livewire/Landlord/LandlordAgentAuctionCounterTerm.php
→ No syntax errors detected
```

## Out of scope (not changed)
- Offer Counter Terms, Create Offer Listing forms, Buyer/Seller/Tenant flows
- Agent preset save UI
- Database schema changes
- UI inputs for `split_payment_due` / `split_payment_due_other` / `broker_fee_days_after_due_event` — tracked in follow-up #567
