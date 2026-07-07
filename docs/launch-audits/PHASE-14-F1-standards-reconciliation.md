# Phase 14 · Step 1 — F.1 Standards Reconciliation (S1–S16)

**Date:** 2026-07-02
**Branch:** `launch-audit-remediation`
**Type:** Documentation / certification pass. **No production code changed.**
**Scope:** Reconcile the S1–S16 global UI standards against what was actually implemented across Phases 1–13 + Phase 8, correcting the stale `⬜` inline table markers, and produce the final standards table with an honest status per standard.

> Method: status derived from committed work (git history + §16 Implementation Log), not from the roadmap's inline `Status` column, which was never back-filled after Phases 8–13 landed. Items requiring a running browser are marked **Needs browser verification** and are NOT certified here (per C14: no faked passes).

---

## Phase → commit ledger (authoritative)

| Phase | Scope | Commit |
|-------|-------|--------|
| 1–7 | Core save/validation/privacy, listing-type, parity, contingencies, assumable/exchange, seller sizing, shared address | `ecd227b3a` (+ `5273a02ca`, `7b2865a57`, `d6b63c27d`, `c4bc7b7bc`, …) |
| 8 | Tooltip/placeholder/UI-text audit (A8.50–A8.64) | `85182b325` |
| 9A–9D | Buyer/Tenant Search Areas + Important Places | `21c1191a8`, `5083246bb`, `a57b3cfa0`, `80d2c064f` |
| 10 | Property-type-aware placeholders | `a45b9ffe3` |
| 11 | Hire/Create Tenant (B3.1–B4.4) | `4a12d503d` |
| 12 | Hire/Create Buyer (B5/B6/S7) — 12/13, B5.4 held | `284577b02` |
| 13 | A2.16 label + verification (A2.17/A2.18/C10/C11) | `bd0b1ba9d`, `f874cac85` |

---

## Final S1–S16 standards table

| Std | Standard | Status | Evidence / notes |
|-----|----------|--------|------------------|
| **S1** | Placeholder standard (real title + real example, never generic) | ✅ **Complete (code)** | Generic `Enter title (e.g., example)` templates eliminated in Phase 8 (water access/view, interior features). Property-type examples in Phase 10. |
| **S2** | Placeholder capitalization (sentence-style, acronyms preserved) | ✅ **Complete (code)** | Phase 8 A8.51/A8.64 sweep across Seller Create+Hire; Phases 11/12 covered Tenant/Buyer. `EV`/`HOA` acronyms preserved. |
| **S3** | "Other" field standard (stays visible; real-name label) | 🟡 **Code done; behavior needs browser** | Placeholder text done (Phase 8 + A7.47/A7.48). Persistence/visibility of "Other" inputs is code-covered for most; **B5.4** (Hire Buyer bed/bath) is the open exception (hold). Draft-resume/edit visibility = **Needs browser verification**. |
| **S4** | Tooltip standard (single compact dark style) | 🟡 **Partial → A8.50** | Wording normalized; **format/font parity across every tooltip = A8.50, Needs browser/visual verification.** |
| **S5** | Helper text standard (no tooltip/header duplication) | ✅ **Complete (code)** | No new duplication introduced; section-header vs tooltip split respected in touched blades. |
| **S6** | Select placeholder standard (all begin with `Select`) | ✅ **Complete (code)** | Selects use `data-placeholder="Select"` in touched forms (verified across Seller/Hire property tabs). |
| **S7** | Conditional field behavior (reveal, persist, prefill) | 🟡 **Code done; browser pending** | Phase 12 rewired Hire Buyer garage dependents (B5.3, always-render + `d-none` + `wire:ignore`); Phase 11 Tenant rental-purpose "Other". **B5.4 open.** select2 rehydration (B5.3/B5.8, Batch 12) = **Needs browser verification.** |
| **S8** | Input size standard | ✅ **Complete (code)** | Phase 7 (A7.42/A7.43 sizing), Phase 11 (B4.3 textarea→single-line), A7.49 number→text. Parity test covers sizing. |
| **S9** | Number/currency/percent input standard | ✅ **Complete (code)** | Phase 6 (A6.39 currency text+`validateInput`, A6.40 down-payment `%` default), A7.49. |
| **S10** | Address standard (one shared component) | 🟡 **Hire done; Create adoption = follow-up** | Shared `<x-byo-address-autocomplete>` + `HandlesGooglePlacesAddress` wired into Hire Seller/Landlord (Batch 15); Buyer/Tenant Search Areas (Phase 9). **Create Seller/Landlord adoption of the shared component = documented owner-approved follow-up, not a blocker.** |
| **S11** | Section consistency (label/helper/tooltip/placeholder parity) | 🟡 **Code done; PDF/email/summary needs verification** | Hire↔Create placeholder parity fixed in Phase 8 (appliances, amenities, NFT, business assets, agency timeframe). Summary/PDF/email parity for these = **Needs verification (C13 display column).** |
| **S12** | Property-type awareness | ✅ **Complete (code)** | Phase 10 shared `PropertyTypePlaceholderHelper`; B2.1/B2.2/B4.5/B6.8. |
| **S13** | Component reuse (no duplicated working code) | ✅ **Complete (code)** | Shared traits/partials: `HasSearchAreas`, `HasImportantPlaces`, `ImportantPlacesService`, `ContingencyOptionHelper`, address component. |
| **S14** | Accessibility / visual consistency | 🟡 **Needs browser/visual** | No errors/icons/required-markers hidden by any text change (Phase 8 diff was placeholder-only). Contrast/focus/keyboard = **Needs browser verification.** |
| **S15** | Backward compatibility (no break to listings/drafts/PDF/AI) | ✅ **Complete (code)** | Phase 8 touched zero `wire:model`/`<option>`/validation. Contingency legacy display-mapping (A5.29/A5.30). C11 save/prefill preserved. Regression suites green for touched areas. |
| **S16** | Cross-flow parity | 🟡 **Code done; multi-surface verify pending** | create/edit/draft parity covered by `CreateEditParityRegressionTest` (80/80). summary/PDF/email/Ask-AI surfaces for the newly-normalized fields = **Needs verification (C13).** |

**Legend:** ✅ Complete (code-verified) · 🟡 Code complete but a runtime/visual/multi-surface dimension is outstanding · ⬜ Not started.

---

## Reconciliation summary

- **Code-complete & certifiable now:** S1, S2, S5, S6, S8, S9, S12, S13, S15 (9 of 16).
- **Code-complete, runtime/visual/multi-surface verification outstanding:** S3, S4, S7, S10, S11, S14, S16 (7 of 16) — these carry into C13/browser QA, NOT failures.
- **The stale roadmap table** (A8.x, B-series, Phase 9–13 showing `⬜`) is superseded by this ledger; those items are committed. F.1 recommends the inline `Status` column be treated as historical and this document as the standards source of truth going into certification.
- **Single open code item touching a standard:** **B5.4** (S3/S7, Hire Buyer bed/bath "Other" select). Documented hold — not fixed in this step.

No standard is regressed by Phases 8–13. No standard can be marked fully ✅ where a browser-only dimension remains — those are explicitly **Needs browser verification** per C14.
