# FOLLOW-UP: `/buyer-agent/auction/edit/{id}` returns 500 on missing record (no null guard)

**Status:** Documented only — **not fixed.** Logged as an incidental finding during the Location DNA
Search-Areas browser QA (2026-07-02). Unrelated to the `map-input.blade.php` change.

**Severity:** Medium
- User-facing hard 500 (not a graceful 404) reachable from real "Edit" buttons.
- No data loss, no security impact; valid ids render normally.

---

## Summary

`BuyerCriteriaAuctionController@edit()` calls a model method on the result of
`BuyerCriteriaAuction::find($id)` **without checking for null**. When `find()` returns `null`
(id not present in the `buyer_criteria_auctions` table — a stale/deleted id, or an id that
belongs to a different table), the next line dereferences null and the request dies with:

```
Call to a member function info() on null
```

## Location

`app/Http/Controllers/BuyerCriteriaAuctionController.php` — `edit()`, lines ~401–403:

```php
$page_data['auction'] = BuyerCriteriaAuction::find($id);          // returns null if not found
$page_data['id'] = $id;
$ldnaRaw = $page_data['auction']->info('location_dna_preferences'); // ->info() on null → 500
```

`info()` is the model's EAV meta getter (`method_exists(...,'info') === true`), so the crash is
purely the missing null guard, not a missing method.

## Reproduction path

1. Log in and open any Buyer Criteria list/detail page. The "Edit" action links to this route:
   - `resources/views/buyer_criteria/list.blade.php:112`
   - `resources/views/buyer_criteria/my-criteria-auctions.blade.php:78`
   - `resources/views/buyer_criteria/view.blade.php:255`
   - also built directly in `app/Http/Controllers/Stellar/StellarBuyerResultsController.php:314`
2. Hit `GET /buyer-agent/auction/edit/{id}` (route name `buyer_agent.auction.edit`) with an `id`
   that is **not present in `buyer_criteria_auctions`**.
3. Result: HTTP **500**, exception `Call to a member function info() on null`.

Observed during QA: `BuyerCriteriaAuction::find(95)` returns `null` (id 95 exists in
`buyer_agent_auctions`, a different table; the model here maps to `buyer_criteria_auctions`, which
was empty in the QA database — so every id 500s in that environment). In production the 500 occurs
only for ids that don't exist in `buyer_criteria_auctions`.

## Note on a stale log entry (red herring)

An older `storage/logs/laravel.log` entry dated **2026-02-05** attributes a 500 on this route to a
`ParseError` in `config/buyer_services_order.php:7` (an unescaped apostrophe in a single-quoted
string). That file **now lints clean** (`php -l` passes; strings were converted to double-quoted)
and is **not** the current cause. The logger-cascade in that old entry
("Unable to create configured logger … Log [] is not defined") is why no fresh error is written to
the log today — but the live 500 is the null-dereference above, confirmed via the `APP_DEBUG` error
page.

## Recommended fix (do not apply yet)

Guard the lookup and return a clean 404 (matches the working Livewire edit path, which loads the
record safely):

```php
$auction = BuyerCriteriaAuction::findOrFail($id);   // 404 instead of 500 on bad id
$page_data['auction'] = $auction;
$page_data['id'] = $id;
$ldnaRaw = $auction->info('location_dna_preferences');
```

Or, if a soft landing is preferred, `if (! $auction) { return redirect()->route('...')->with('error', ...); }`
before touching `->info()`. Also worth confirming whether this controller route is still the intended
edit entry point at all, given the Livewire edit route
(`buyer/agent/auction/edit/{auctionId}/{user_type?}`) already handles buyer-criteria edits correctly.
