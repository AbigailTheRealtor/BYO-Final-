# Seller ↔ Tenant Visual/Structural Parity Notes

Side-by-side comparison for each required parity area.
All changes are Seller-only; no Tenant files modified.

---

## 1. Typography Density & Spacing Rhythm (Counter Bid History Card)

| Area | Tenant | Seller |
|------|--------|--------|
| Counter bid card wrapper | `counter-bid-card mb-3 p-3 border rounded mt-2` | **IDENTICAL** |
| Card header flex row | `d-flex justify-content-between align-items-center flex-wrap mb-2` | **IDENTICAL** |
| Title h6 | `<h6 class="mb-0">Your Counter Offer / Counter Offer from ...</h6>` | **IDENTICAL** |
| Date small | `<small class="text-muted">{{ date }}</small>` | **IDENTICAL** |

Screenshot: `seller-counter-bid-history.jpg` / `tenant-counter-bid-history.jpg`

---

## 2. List Compactness (Offered Services in Counter Bid Card)

| Area | Tenant | Seller (after fix) |
|------|--------|--------|
| Section header | `<h6 style="... border-bottom: 2px solid #049399; ..."><i class="fa fa-clipboard-list">Offered Services</h6>` | **IDENTICAL** |
| Wrapper div | `<div class="mb-5">` | **IDENTICAL** |
| Category label | `<div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">` | **IDENTICAL** |
| Service list `ul` | `class="services mb-0"` with `list-style: none; padding-left: 1.2rem` | **IDENTICAL** |
| Service item | `<li style="font-size: 0.9rem; margin-bottom: 4px;">` | **IDENTICAL** |
| Added service | yellow `#fff3cd` bg, `fa-plus-circle` (color #856404), `badge bg-warning text-dark` "Added" | **IDENTICAL** |
| Removed Services box | `#fff5f5` bg, `border: 1px solid #f5c6cb`, `fa-minus-circle` red, `fa-times-circle` per item | **IDENTICAL** |

Screenshot: `seller-counter-bid-card-services.jpg` / `tenant-counter-bid-card-services.jpg`

---

## 3. Match Summary Layout (Bid Card Sidebar)

| Area | Tenant | Seller |
|------|--------|--------|
| Dual-score header | `<i class="fas fa-chart-pie"></i>Match Summary` | **IDENTICAL** |
| Original Match column | border + `Original Match` + % badge + Services/Terms % | **IDENTICAL** |
| Counter Match column | border + `Counter Match` + % badge + Services/Terms % | **IDENTICAL** |
| Info note | "ℹ Added services or terms do not increase either score." | **IDENTICAL** |
| Single-score fallback | `<i class="fas fa-chart-pie"></i>Match Score` + single % badge | **IDENTICAL** |
| Broker Compensation below | `<h5 style="font-family: 'Courier New'...">Broker Compensation Summary:</h5>` | **IDENTICAL** |

Screenshot: `seller-match-summary.jpg` / `tenant-match-summary.jpg`

---

## 4. Counter Action Banner/Buttons (Inside Counter Bid History Card)

| Area | Tenant (counter history card) | Seller (after fix) |
|------|------|------|
| Banner wrapper | `<div class="mt-3 pt-3 border-top">` | **IDENTICAL** |
| Banner | `w-100 p-2 text-center` + `background: #fff3cd; border-radius: 6px; color: #856404` | **IDENTICAL** |
| Banner icon | `fa fa-exchange-alt` | **IDENTICAL** |
| Button row | `d-flex gap-2 flex-wrap justify-content-center w-100 mt-2` | **IDENTICAL** |
| Primary button | `btn btn-warning btn-sm text-dark` | **IDENTICAL** |
| Secondary button | `btn btn-outline-secondary btn-sm` | **IDENTICAL** |

Note: Tenant has Accept/Reject/Counter (3 form buttons) because Tenant uses inline Accept/Reject flow.
Seller has View/Edit Counter Terms (2 link buttons) because Seller uses a page-based counter flow.
Both use the SAME button styling and banner pattern.

Screenshot: `seller-counter-bid-actions.jpg` / `tenant-counter-bid-actions.jpg`

---

## 5. Counter Status Badges (Footer of Counter Bid Card)

| Area | Tenant | Seller |
|------|--------|--------|
| Accepted (self) | `alert alert-success mb-0 py-1 small` + "✅ This counter bid has been accepted." | **IDENTICAL** |
| Accepted (other) | `alert alert-success mb-0 py-1 small` + "✅ {name} accepted the counter bid." | **IDENTICAL** |
| Rejected (self) | `alert alert-danger mb-0 py-1 small` + "❌ This counter bid has been rejected." | **IDENTICAL** |
| Rejected (other) | `alert alert-danger mb-0 py-1 alert-font` + "❌ {name} rejected the counter bid." | **IDENTICAL** |
| Pending (submitter) | `alert alert-secondary mb-0 py-1 small` + "⏳ Waiting for response from {name}..." | **IDENTICAL** |
| Pending (viewer) | `alert alert-light mb-0 py-1 small` + "⏳ Counter bid from {name} is pending." | **IDENTICAL** |

---

## 6. Bid Card Footer Countered State

| Area | Tenant | Seller |
|------|--------|--------|
| Banner | `w-100 p-2 text-center` + `background: #fff3cd; border-radius: 6px; color: #856404` | **IDENTICAL** |
| Banner icon | `fa fa-exchange-alt` | **IDENTICAL** |
| Banner text (owner) | "You have submitted a counter offer for this bid." | **IDENTICAL** |
| Banner text (other) | "{ownerFirst ownerLast} has submitted a counter offer." | **IDENTICAL** |
| Button row | `d-flex gap-2 flex-wrap justify-content-center w-100 mt-2` | **IDENTICAL** |
| View button | `btn btn-warning btn-sm text-dark` + `fa fa-eye` + "View Counter Terms" | **IDENTICAL** |
| Edit button | `btn btn-outline-secondary btn-sm` + `fa fa-edit` + "Edit Counter Terms" (owner only) | **IDENTICAL** |

Screenshot: Visible in `seller-counter-bid-history.jpg` (right panel - "Countered" banner with yellow View button)

---

## 7. Private Compensation & Agency Agreement Terms Page (view_counter_terms.blade.php)

| Area | Tenant | Seller |
|------|--------|--------|
| Page header card | `border: 2px solid #049399; border-radius: 8px;` | **IDENTICAL** |
| Card header | `background: linear-gradient(135deg, #049399, #037a7f); color: white;` | **IDENTICAL** |
| Breadcrumb | Dashboard / Listing / Counter Terms | **IDENTICAL** |
| Listing/Bid info row | Two-column: Listing Information (teal h6) / Bid Information (teal h6) | **IDENTICAL** |
| Agent's Counter Terms card | `border-radius: 10px; border: 1px solid #dee2e6;` inner card | **IDENTICAL** |
| Section header | `fa-file-contract` icon + "Agent's Counter Terms" + "Last updated:" right-aligned | **IDENTICAL** |
| Match Summary heading (dual) | `<i class="fas fa-chart-pie"></i>Match Summary` | **IDENTICAL** |
| Match Summary explanation | "Original Match compares... Latest Counter Match compares..." bold + regular text | **IDENTICAL** |
| Dual score columns | Two bordered panels: "Original Match" + "Latest Counter Match" | **IDENTICAL** |
| Match Score heading (single) | `<i class="fas fa-chart-pie"></i>Match Score` | **IDENTICAL** |
| Services Match row | border-left colored, `Matched Original: X/Y`, Missing/Extra counts | **IDENTICAL** |
| Terms Match row | border-left colored, `Matched Original: X/Y`, Changed/Added counts | **IDENTICAL** |
| Broker Compensation heading | `fa-handshake` + "Broker Compensation & Agency Agreement Terms" teal border-bottom | **IDENTICAL** |
| Field rows with Mismatch badge | `border-left: 4px solid ...` + `badge` "Mismatch" | **IDENTICAL** |
| Indentation structure | All nested sections at correct 24-space indent inside outer card div | **IDENTICAL** |

Screenshot: `seller-counter-terms-top.jpg` / `tenant-counter-terms-top.jpg`
Screenshot: `seller-counter-terms-match-score.jpg` / `tenant-counter-terms-match-score.jpg`

---

## Files Changed (Seller only, no Tenant files touched)

- `resources/views/hire_seller_agent/view.blade.php`:
  - Services section in counter bid history card: updated to match Tenant visual pattern
  - Counter action banner/buttons in counter bid history: updated to `btn btn-warning btn-sm` / `btn btn-outline-secondary btn-sm` with `#fff3cd` banner
  
- `resources/views/hire_seller_agent/view_counter_terms.blade.php`:
  - Indentation fix: nested sections now at 24-space indent (matching Tenant structure)
