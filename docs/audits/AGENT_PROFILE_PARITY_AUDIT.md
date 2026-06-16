# Agent Profile Parity Audit

**Date:** June 16, 2026
**Scope:** Every field an agent can fill in across all profile-related surfaces vs. what actually renders on the public profile page (`/agent/{shortId}`).
**Method:** Cross-referenced `AgentPresetController::save()` (save logic), `AgentProfileController::PUBLIC_PROFILE_KEYS` (whitelist), `agent-profile/show.blade.php` (render logic), `settings.blade.php` (settings form), and `DashboardController::saveSettings()` (settings save).

---

## Storage Systems Overview

| System | Location | Saved By |
|--------|----------|----------|
| **`users` table** | Native DB columns | Settings form (`DashboardController::saveSettings`) |
| **`user_meta` table** | EAV key/value | Settings form (`saveMeta()`) |
| **`agent_default_profiles.profile_data`** | JSONB column (per role × property type) | Preset editor (`AgentPresetController::save`) |

The public profile reads **only from `profile_data`** (with `PUBLIC_PROFILE_KEYS` whitelist applied), plus `$agent->avatar` from the `users` table. Everything else is invisible to the profile view regardless of whether it is filled in.

---

## Section-by-Section Field Table

### Section 1 — Services

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `services` (catalog checkboxes) | `profile_data` | ✅ | ✅ Grouped bullet list | — |
| `other_services` (custom freetext) | `profile_data` | ✅ | ✅ Merged into Additional Services | — |

---

### Section 2 — Agent Overview

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `bio` | `profile_data` | ✅ | ✅ Hero snippet + About section | — |
| `bio` (settings version) | `user_meta` | N/A — separate system | ❌ Profile reads preset only | **SPLIT**: bio saved in settings never appears on profile |
| `why_hire_you` | `profile_data` | ✅ | ✅ About section | — |
| `what_sets_you_apart` | `profile_data` | ✅ | ✅ About section | — |
| `marketing_plan` | `profile_data` | ✅ | ✅ Marketing Plan section | — |
| `additional_details` | `profile_data` | ❌ not whitelisted | ❌ Never shown | **MISSING**: "Additional Details" filled in preset, silently dropped |

---

### Section 3 — Agent Credentials

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `first_name` | `profile_data` + `users` table (settings) | ✅ | ✅ Hero name display | — |
| `last_name` | `profile_data` + `users` table (settings) | ✅ | ✅ Hero name display | — |
| `phone` | `profile_data` (preset credentials) | ❌ not whitelisted | ❌ Never shown | **MISSING**: phone entered in preset never appears on profile |
| `phone` | `users` table (settings) | N/A | ❌ Never shown | **MISSING**: phone in account settings never shown on profile |
| `phone_number` | `users` table (migration column) | N/A | ❌ Never shown | **MISSING**: duplicate phone column, unused on profile |
| `email` | `profile_data` (preset credentials) | ❌ not whitelisted | ❌ Never shown | **MISSING**: contact email in preset never appears (intentional privacy, but no contact link either) |
| `brokerage` | `profile_data` + `user_meta` accessor | ✅ | ✅ Hero section | — |
| `license_no` | `profile_data` + `user_meta` accessor | ✅ | ✅ Credentials section + hero | — |
| `nar_id` | `profile_data` | ✅ | ✅ Credentials section | — |
| `year_licensed` | `profile_data` | ✅ | ✅ Credentials section | — |
| `brokerage_relationship` | `profile_data` | ✅ | ✅ Credentials section | — |
| `mls_id` | `users` table (via `user_meta` accessor) | N/A | ❌ Never shown | **MISSING**: MLS ID stored on user, never shown on public profile |

---

### Section 4 — Presentation & Links

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `presentation_link` | `profile_data` | ✅ | ✅ Presentation & Links section | — |
| `presentation_upload_path` | `profile_data` | ❌ (intentionally stripped) | ❌ Never shown on profile | **MISSING**: agent uploads a file but profile only shows a link — no fallback to render the uploaded file |
| `business_card_link` | `profile_data` | ✅ | ✅ Presentation & Links section | — |
| `business_card_upload_path` | `profile_data` | ❌ (intentionally stripped) | ❌ Never shown on profile | **MISSING**: uploaded business card/headshot file is invisible on profile; only the URL link version shows |
| `website_link` | `profile_data` | ✅ | ✅ Rendered as pill links | — |
| `social_media` | `profile_data` | ✅ | ✅ Rendered as pill links | — |
| `reviews_links` | `profile_data` | ✅ whitelisted | ❌ **NOT rendered anywhere in `show.blade.php`** | **BUG**: field is whitelisted and passed to view but the template never outputs it; also excluded from `$hasLinks` guard so the section does not open for it |
| `website` | `users` table | N/A | ❌ Never shown | **MISSING**: legacy `users.website` column stored but unused on profile |

---

### Section 5 — Quick Highlights

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `years_experience` | `profile_data` | ✅ | ✅ Highlight card | — |
| `transactions_last_12_months` | `profile_data` | ✅ | ✅ Highlight card | — |
| `avg_response_time` | `profile_data` | ✅ | ✅ Highlight card | — |
| `is_full_time` | `profile_data` | ✅ | ✅ Highlight card (Full-Time / value) | — |

---

### Section 6 — Areas Served

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `primary_areas_served` | `profile_data` | ✅ | ✅ | — |
| `cities_served` | `profile_data` | ✅ | ✅ | — |
| `counties_served` | `profile_data` | ✅ | ✅ | — |
| `neighborhoods_served` | `profile_data` | ✅ | ✅ | — |
| `areas_notes` | `profile_data` | ✅ | ✅ | — |

---

### Section 7 — Social Proof

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `review_1` | `profile_data` | ✅ | ✅ Review blocks | — |
| `review_2` | `profile_data` | ✅ | ✅ Review blocks | — |
| `review_3` | `profile_data` | ✅ | ✅ Review blocks | — |
| `awards_recognition` | `profile_data` | ✅ | ✅ Social Proof section | — |

---

### Section 8 — Video Intro

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `intro_video_url` | `profile_data` | ✅ | ✅ Embedded YouTube/Vimeo or link | — |
| `video_caption` | `profile_data` | ✅ | ✅ Caption below video | — |

---

### Section 9 — Availability & Service Style

| Field | Stored In | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------|-----------|--------------------------|---------------------|-----|
| `availability_status` | `profile_data` | ✅ | ✅ Availability tag | — |
| `evenings_available` | `profile_data` | ✅ | ✅ Flexible Hours tags | — |
| `weekends_available` | `profile_data` | ✅ | ✅ Flexible Hours tags | — |
| `communication_style` | `profile_data` | ✅ | ✅ | — |
| `preferred_contact_method` | `profile_data` | ✅ | ✅ | — |
| `preferred_contact_method` (settings) | `user_meta` | N/A | ❌ Profile reads preset only | **SPLIT**: settings version never shown on profile |
| `best_time_to_contact` | `user_meta` (settings only) | N/A | ❌ Never shown anywhere | **MISSING**: filled in settings, never displayed on profile or anywhere else |

---

### Section 10 — Working Style & Compatibility (`compatibility_preferences`)

This is the largest invisible section. The preset editor has a full "Working Style & Compatibility" accordion with 6 sub-sections and ~20 fields. All are saved into `profile_data['compatibility_preferences']`.

| Sub-Section | Fields Stored | In `PUBLIC_PROFILE_KEYS` | Rendered on Profile | Gap |
|-------------|---------------|--------------------------|---------------------|-----|
| Communication Preferences | `agent_communication_channels`, `agent_communication_frequency`, `agent_response_time_commitment`, `agent_communication_notes`, `agent_availability_notes` | ❌ | ❌ | **MISSING**: entire sub-section invisible |
| Negotiation Approach | `agent_negotiation_style`, `agent_negotiation_notes` | ❌ | ❌ | **MISSING** |
| Guidance Style | `agent_guidance_level`, `agent_guidance_notes` | ❌ | ❌ | **MISSING** |
| Collaboration Preferences | `agent_collaboration_style`, `agent_availability_windows` | ❌ | ❌ | **MISSING** |
| Transaction Strategy | `agent_transaction_pace`, `agent_strategy_experience`, `agent_strategy_notes` | ❌ | ❌ | **MISSING** |
| Representation Philosophy | `agent_decision_support_style`, `agent_risk_posture`, `agent_representation_philosophy`, `agent_philosophy_narrative`, `agent_philosophy_notes` | ❌ | ❌ | **MISSING** |

The entire `compatibility_preferences` blob (~20 fields across 6 sub-sections) is stored per preset, propagated cross-role, and used to pre-fill bid form compatibility tabs — but is **completely absent from the public profile**.

---

### Section 11 — Broker Compensation (Authenticated Viewers)

The public profile shows a compensation section **only for logged-in users**. It displays a curated subset of ~15 high-level fields from the 100+ compensation fields stored. The following saved compensation fields have **no display path** at all (not in `PUBLIC_PROFILE_KEYS`, not in `$compensationData`):

| Field Group | Example Fields | Displayed? |
|-------------|---------------|------------|
| Purchase fee breakdown | `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` | ❌ |
| Lease fee breakdown | `lease_fee_flat`, `lease_fee_percentage_combo`, `lease_fee_flat_combo`, `lease_fee_other` | ❌ |
| Seller leasing | All `seller_leasing_*` fields (~12 keys) | ❌ |
| Tenant broker | All `tenant_broker_*` fields (~7 keys) | ❌ |
| Referral & split | `referral_fee_percent`, `split_payment_due`, `split_payment_due_other` | ❌ |
| Landlord broker purchase | `landlord_broker_*` fields | ❌ |
| Property management interest | `interested_in_property_management*` fields | ❌ |
| Flags | `interested_lease_option`, `interested_in_selling`, `nominal` | ❌ |
| Commission structure detail | `commission_structure_type`, `commission_structure_type_fee_*` | ❌ |

> **Note:** Most of these are intentionally private (sensitive compensation details). The finding is that an agent has no way to know which parts of their compensation profile are visible to even logged-in platform users vs. entirely hidden. The boundary is undocumented and the label "Broker Compensation & Agency Agreement Terms" on the profile implies completeness when only a fraction is shown.

---

### Section 12 — Legacy `users` Table Fields (Stored, Never Shown on Agent Profile)

These columns exist on the `users` table, may be populated via Settings or legacy flows, and do not appear anywhere on the public agent profile:

| Field | Where Stored | Reason Not Shown |
|-------|-------------|------------------|
| `cover_photo` | `users` table | No cover photo section exists on profile; image stored at `images/cover/{filename}` |
| `website` | `users` table | Legacy column; preset system uses `profile_data.website_link` instead |
| `description` | `users` table | Legacy column; preset system uses `profile_data.bio` |
| `address1`, `address2` | `users` table | Not surfaced on agent profile |
| `city_id`, `state_id`, `zip` | `users` table | Not surfaced on agent profile |
| `mls_id` | `users` table (accessor falls back to `user_meta`) | Not surfaced on profile |

---

## Consolidated Gap Summary

| # | Gap | Severity | Location |
|---|-----|----------|----------|
| 1 | **`reviews_links` whitelisted but never rendered** — field passes the security filter and reaches the Blade view, but no template block outputs it; also excluded from `$hasLinks` guard | **High** — data silently lost | `show.blade.php` |
| 2 | **`additional_details` in preset, not whitelisted** — "Additional Details" text area in Agent Overview saved and propagated cross-role, never shown on profile | **High** — agent fills it, nothing happens | `AgentProfileController::PUBLIC_PROFILE_KEYS` |
| 3 | **`compatibility_preferences` — entire section invisible** — ~20 fields across 6 sub-sections stored per preset, zero display on profile | **High** — large effort to fill, zero return | `show.blade.php` / `PUBLIC_PROFILE_KEYS` |
| 4 | **`bio` storage split** — Settings saves `bio` to `user_meta`; profile reads `profile_data.bio` from preset. An agent who fills in bio under Profile Settings sees nothing change on their public profile | **High** — very confusing UX | `DashboardController`, `show.blade.php` |
| 5 | **`preferred_contact_method` storage split** — same pattern: Settings → `user_meta`, profile reads preset | **Medium** | Same as above |
| 6 | **`best_time_to_contact` stored but never shown** — settings field saved to `user_meta`, no display path on profile or anywhere publicly visible | **Medium** | `settings.blade.php` / `show.blade.php` |
| 7 | **Uploaded files (`presentation_upload_path`, `business_card_upload_path`) invisible on profile** — upload is stored and confirmed in edit form, but profile only renders the `_link` URL version; an agent with only an uploaded file (no separate URL) gets no display | **Medium** | `PUBLIC_PROFILE_KEYS`, `show.blade.php` |
| 8 | **`phone` (preset) not whitelisted** — phone entered in the Credentials section of the preset is saved and cross-propagated but never shown on profile | **Medium** | `PUBLIC_PROFILE_KEYS` |
| 9 | **`phone` (users table / settings) not shown** — phone saved via Account Settings never surfaces on profile | **Medium** | `show.blade.php` |
| 10 | **`cover_photo` stored but unused on profile** — `users.cover_photo` column is populated, no cover photo slot exists on the profile page | **Low** | `show.blade.php` |
| 11 | **`mls_id` stored but not shown** — present in users table and user_meta accessor, never shown on agent public profile | **Low** | `show.blade.php` |
| 12 | **Compensation section label implies completeness** — "Broker Compensation & Agency Agreement Terms" shown to logged-in users displays only ~15 of 100+ stored compensation fields; no indication the section is partial | **Low** | `show.blade.php` |

---

## Recommended Fixes (Prioritized)

### P1 — Fix the silent `reviews_links` drop (bug)
Add `reviews_links` rendering to the Presentation & Links section of `show.blade.php` and include it in the `$hasLinks` guard. This is a clear rendering omission — the field is already whitelisted and passed to the view.

### P1 — Add `additional_details` to `PUBLIC_PROFILE_KEYS`
The "Additional Details" field in the Agent Overview section is a natural public field (agent bio notes, extra pitch info). Either add it to the whitelist and render it in the About section, or rename/clarify its purpose so agents know it will not appear publicly.

### P2 — Add a `compatibility_preferences` public section to the profile
The Working Style & Compatibility section contains high-signal information clients use when evaluating an agent (negotiation style, guidance level, communication channels). Even a read-only summary of top-level values would close this gap meaningfully.

### P2 — Resolve the `bio` / `preferred_contact_method` split
Either:
- **Option A:** Remove bio/preferred_contact_method from `settings.blade.php` and link users to the preset editor.
- **Option B:** On save in `DashboardController::saveSettings`, also sync changes into the agent's primary preset's `profile_data`.

Currently an agent filling in bio in Settings gets no result on their profile. This is a significant UX gap for agents who discover Settings before Presets.

### P2 — Handle uploaded file fallback on profile
When `presentation_link` or `business_card_link` is empty but the corresponding `_upload_path` is set, generate a public URL from the stored path and render it. Handle the fallback server-side in `AgentProfileController::show` before the whitelist strip, injecting the resolved URL into `presentation_link` / `business_card_link`.

### P3 — Add `phone` display with a contact affordance
Add `phone` to `PUBLIC_PROFILE_KEYS` and render it as a `tel:` link in the Credentials or Availability section. The value to use is `$data['phone']` from the preset (already saved there).

### P3 — Surface `best_time_to_contact`
Add it to the Availability & Service Style section. It is stored in `user_meta` so it must be fetched from `$agent->info('best_time_to_contact')` in the controller before passing to the view — it is not in `profile_data`.

### P3 — Add a cover photo banner to the profile
`users.cover_photo` is stored (saves to `images/cover/{filename}`) and a property banner image is a standard agent profile element. The profile currently shows only an avatar circle in the hero.

---

## Relevant Files

- `app/Http/Controllers/AgentProfileController.php`
- `resources/views/agent-profile/show.blade.php`
- `app/Http/Controllers/AgentPresetController.php`
- `resources/views/agent-presets/edit.blade.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/settings.blade.php`
- `app/Models/User.php`
- `app/Models/AgentDefaultProfile.php`
