# Offer Detail Page — Launch Audit

## Scope
Audit of the four terminal-state offer detail pages: Accepted, Rejected, Withdrawn, Expired.

## Files Changed

- `app/Http/Controllers/OfferController.php` — snapshot fallback (3-tier) in `show()`; new `downloadPdf()` method with null-safe Auth, 3-tier snapshot fallback, and chain-level resolution; `Pdf` facade import
- `resources/views/offers/show.blade.php` — status-specific section headings; Read-Only badge removed; Email Copy button removed; Download PDF wired to `offers.pdf` route for all four terminal states; "View Timeline" renamed to "View Negotiation Summary"; chain row links
- `resources/views/offers/offer_detail_pdf.blade.php` — new standalone PDF template (no @extends; dompdf-compatible inline CSS)
- `routes/web.php` — `GET /offers/{offer}/pdf` → `offers.pdf` added in the auth+offerPlayoffAccess middleware group

---

## Findings & Fixes Applied

### 1. Status-specific section headings
Previously all terminal states showed "Final Negotiated Terms". Now:

| Status    | Section Heading          |
|-----------|--------------------------|
| Accepted  | Accepted Offer Terms     |
| Rejected  | Rejected Offer Terms     |
| Withdrawn | Withdrawn Offer Terms    |
| Expired   | Expired Offer Terms      |

### 2. Read-Only badge removed
Removed `<span class="badge bg-secondary">Read-Only</span>` from the terms card header on all terminal-state pages. Terminal offers are inherently immutable; the badge was redundant.

### 3. Email Copy button removed
Removed the non-functional placeholder "Email Copy" button from all four terminal-state views.

### 4. Download PDF — wired for all four terminal states
The Download PDF `<a>` button at line 261 of `show.blade.php` is inside the outer `@if($isTerminal && $terminalLeaf)` block (line 140). It is **not** inside the inner `@if($tLeafStatus === 'accepted')` block (lines 172–180), which gates only the SVG icon in the status banner. All four terminal states (accepted, rejected, withdrawn, expired) render the Download PDF link pointing to `route('offers.pdf', $terminalLeaf)`.

PDF generation uses `barryvdh/laravel-dompdf` via `OfferController@downloadPdf`, consistent with the existing `AcceptedBidSummaryController` pattern. Route is protected by the same `auth` + `offerPlayoffAccess` middleware as all other offer actions.

### 5. Accepted snapshot fallback — three-tier priority

For accepted offers, terms are resolved in priority order:

| Priority | Source |
|----------|--------|
| 1 | `accepted_terms_snapshot` meta key on the terminal leaf itself |
| 2 | Live metas on the terminal leaf (all keys except `accepted_terms_snapshot`) |
| 3 | Walk the negotiation chain for the latest accepted offer that has a snapshot or live metas (handles legacy records where the snapshot was written on a parent offer) |

`$snapshotMissing = true` is only set when all three tiers return empty. This applies to both `show()` and `downloadPdf()`.

### 6. Auth null-safety in `downloadPdf()`
Changed from:
```php
$actorId   = Auth::id();
$actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'system';
```
To:
```php
$user      = Auth::user();
$actorId   = $user?->id;
$actorRole = $user?->role ?? $user?->user_type ?? 'system';
```
This avoids a fatal null-dereference if the middleware ever allows an unauthenticated call through.

### 7. "View Timeline" renamed to "View Negotiation Summary"
The button in the Actions card scrolls to `#offer-timeline` (the on-page Negotiation Summary card). The label now accurately describes the destination rather than implying a separate full timeline page.

### 8. Chain navigation links
Each offer in the Negotiation Summary uses `route('offers.show', $step['offer_id'])` so parties can navigate directly to any prior step's detail page.

---

## Status Banner

All four terminal states display a coloured status banner containing the outcome label and the exact date/time of the outcome (sourced from the event log, falling back to `created_at` for legacy records).

| Status    | Banner Colour | Label              | Timestamp Source            |
|-----------|---------------|--------------------|-----------------------------|
| Accepted  | Green         | Offer Accepted     | `offer_accepted` event log  |
| Rejected  | Red           | Offer Rejected     | `offer_rejected` event log  |
| Withdrawn | Dark          | Offer Withdrawn    | `offer_withdrawn` event log |
| Expired   | Secondary     | Offer Expired      | `offer_expired` event log   |

---

## Sign-Off Table

| Check                          | Accepted | Rejected | Withdrawn | Expired |
|-------------------------------|----------|----------|-----------|---------|
| Status banner (colour + date)  | ✅       | ✅       | ✅        | ✅      |
| Status-specific heading        | ✅ "Accepted Offer Terms" | ✅ "Rejected Offer Terms" | ✅ "Withdrawn Offer Terms" | ✅ "Expired Offer Terms" |
| Read-Only badge absent         | ✅       | ✅       | ✅        | ✅      |
| Email Copy button absent       | ✅       | ✅       | ✅        | ✅      |
| Snapshot fallback (3-tier)     | ✅       | N/A      | N/A       | N/A     |
| Download PDF button present    | ✅       | ✅       | ✅        | ✅      |
| Download PDF → `offers.pdf`    | ✅       | ✅       | ✅        | ✅      |
| "View Negotiation Summary" btn | ✅       | ✅       | ✅        | ✅      |
| Chain links in Negotiation Summary | ✅   | ✅       | ✅        | ✅      |
| Auth null-safe in downloadPdf  | ✅       | ✅       | ✅        | ✅      |
