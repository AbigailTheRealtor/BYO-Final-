# Ask AI Knowledge Base — v1.0 Architecture & Design Reference

**Status:** Production-ready (v1.0, frozen).
**Scope:** the creator-facing **Listing AI Knowledge Base** — the gated question set a listing
creator fills in, whose answers pre-load Ask AI and feed the broader intelligence stack.
**Source of truth:** `config/ai_faq_seller.php`, `config/ai_faq_buyer.php`,
`config/ai_faq_landlord.php`, `config/tenant_ai_faq.php`, rendered by
`resources/views/livewire/offer-listing/shared/ai-questions-input.blade.php`.

This document is the **long-term reference for the reasoning behind the v1.0 design**. It exists so
future contributors preserve intent when extending the KB. For the exact applied delta (adds,
rewrites, deletions), see `docs/ask-ai-question-catalog.md` §0.

---

## 1. Design philosophy

The KB is not a data-entry form; it is a **structured interview with an experienced real estate
professional**. Five principles govern every question:

1. **Human insight over structured duplication.** A question earns its place only if it captures
   knowledge the platform cannot already derive from a structured field, Location DNA, Property DNA,
   or Match. If the data already knows the answer, we do **not** ask (this is why
   `school_district_assignment` was removed). Where a question is adjacent to a structured field, it
   is worded to capture *only* the residual human layer (e.g., `commercial_ceiling_height` asks for
   clear height to the lowest obstruction and height limitations — never re-asking the structured
   `ceiling_height` measurement).
2. **Conversational, not a checklist.** Wording reads the way a good agent would ask it. Questions
   are optional; placeholders show real example answers; tooltips explain why the AI needs it.
3. **Compliance by construction.** Every question is anchored on the *property, terms, or the
   party's stated needs* — never on the *type of person*. Fair Housing is enforced at authoring
   (wording), at gating (tenant redaction), and at answer time (the compliance guardrail).
4. **High leverage across the AI stack.** A question is included only when its answer materially
   improves at least one downstream system (§8). Marketing/narrative value counts as much as Q&A
   value.
5. **Maintainability and future-proofing.** Questions live in config (not code); keys are stable
   identifiers that stored answers bind to; new questions are additive. Content decisions are
   documented so v1.1+ can extend without relitigating v1.0.

---

## 2. Universal vs. property-type questions

Each knowledge base is composed on a **two-axis model**:

```
interview(role, property_type) = groups['universal']  +  groups[<property_type_group>]
```

- **Universal questions** apply to every property type for that role — the opener context every
  counterparty asks about (motivation, timing, disclosures, financing posture, applicant
  background) plus role-level AI Insights.
- **Property-type questions** are the expert diligence specific to Residential, Income, Commercial,
  Business, or Vacant Land — and never leak across types.

Within each group, questions carry a `category_type`:

- **`common`** — "Common Questions": answered from the creator's free-text plus structured fields.
- **`insight`** — "AI Insights": educational prompts the AI answers using platform-generated data
  (Property DNA, Location DNA, Match, Description). Insights **explain and educate only — never
  advise**.

The render blade splits the screen into **Common Questions** first, then **AI Insights**, grouping
each under its subsection heading. Same-named subsections in the universal + property-type group
**merge** under one heading (universal questions first).

Each entry's shape:

```php
'key' => [
    'label'         => 'the question the creator sees',
    'placeholder'   => 'neutral example answer (no demographic/steering phrasing)',
    'tooltip'       => 'why the AI needs this',
    'category_type' => 'common' | 'insight',
    'source'        => 'KB | Field | PropDNA | LocDNA | Match | Desc | BuyerDNA | TenantDNA (+ combos)',
]
```

> **`source` is documentation-only metadata** describing design intent. Runtime source eligibility
> is governed independently by `AskAiKnowledgeSourceRegistry` (keyed by *question type*). Do not
> assume the `source` string is what the answer engine consults.

---

## 3. Gating rules

Gating is resolved in the blade from each config's `gating` map and an alias map:

```php
$gatingKey    = config('property_types.ai_faq_gating_aliases')[$property_type] ?? $property_type;
$activeGroups = ($gatingKey === '' || $gatingKey === null)
    ? ['universal']                        // nothing selected → universal only
    : ($gating[$gatingKey] ?? ['universal']); // selected & known → its group; unknown → universal only
```

Rules:

1. **Property-type applicability.**
   - **Seller / Buyer** support all five types: Residential, Income, Commercial (sale), Business
     Opportunity, Vacant Land.
   - **Landlord / Tenant** support only Residential and Commercial (lease). Income, Business, and
     Vacant Land are **Not Applicable** (no groups exist).
2. **Vocabulary bridge.** Seller/Buyer `<select>`s store **short** values (`Income`, `Commercial`,
   `Business`, `Vacant Land`); the gating maps and Landlord/Tenant use **long** values
   (`Income Property`, …). `config/property_types.php` `ai_faq_gating_aliases` bridges the two so the
   intended group renders. (This resolves the historical select-vs-gating defect; regression-guarded
   by `AskAiKnowledgeBaseRenderTest`.)
3. **No property type selected → universal only.** The KB never reveals a property-type interview
   before a type is chosen. An unrecognized non-empty value also falls back to universal-only (never
   leaks Residential).
4. **Reactive & non-destructive.** The tab re-renders when `property_type` changes (Livewire
   `updatedPropertyType`), and answers already entered persist in `$listing_ai_faq` across type
   changes — the components never prune it, so switching type and back is safe.
5. **No cross-type leakage.** Residential questions never appear under Income/Commercial/etc., and
   vice-versa. Verified by the 14-case render matrix + leak guards.

---

## 4. Why questions were added (v1.0)

The v1.0 pass closed two systemic gaps the baseline had — **objections / deal-breakers** and
**matchmaking / ideal-fit** — plus per-type expert depth, without inflating question count. Themes:

- **Emotional & marketing signal (Seller Residential):** `seller_emotional_hook`,
  `seller_guest_compliments`, `seller_best_showing_moment`, `seller_ideal_use_fit`,
  `seller_buyer_feedback` — human insight for descriptions, narratives, and objection handling that
  no structured field or DNA can infer.
- **Deal-breakers / flexibility (Buyer + Tenant):** `buyer_deal_breakers`,
  `buyer_compromise_areas`, `tenant_deal_breakers` — worded to sit *beyond* the structured
  non-negotiables/criteria, capturing qualitative walk-away factors.
- **Lifestyle & fit (Tenant Residential):** `tenant_work_habits`, `tenant_commute_priorities` —
  rebalance the previously screening-heavy tenant interview and improve matching in a hybrid-work
  world.
- **Management & fit (Landlord Residential):** `landlord_management_style`, `landlord_tenant_fit`.
- **Per-type expert depth:** Income (`income_ideal_operator_fit`, `income_value_add_vision`),
  Commercial sale (`commercial_redevelopment_potential`, `commercial_visibility_signage`,
  `commercial_ceiling_height`), Business (`business_growth_opportunities`, `business_customer_draw`),
  Vacant Land (`land_utilities_available`, `land_entitlement_status`), Commercial lease
  (`commercial_cam_structure`, `commercial_target_industries`, `commercial_parking_ratio`),
  Commercial tenant (`tenant_buildout_needs`).

Every addition was verified non-duplicative against structured fields, Location DNA, Property DNA,
Match, and the existing KB before inclusion.

---

## 5. Why questions were removed / rewritten (v1.0)

- **Removed — `school_district_assignment`** (Seller Residential, Landlord Residential): Location
  DNA (Census TIGER) resolves the assigned district. Asking the creator to re-type structured data
  is the anti-pattern this KB avoids.
- **Rewritten (3) — Location-DNA-output duplicators → human-insight prompts:**
  `nearby_amenities_description` (Seller) and `nearby_amenities` (Landlord) both re-listed features
  Location DNA already provides; they now capture the *personal/tenant-experience* layer.
  `commute_options_access` (Seller) now captures local nuance a map can't show (LocDNA supplies the
  objective times).

The guiding rule: if the platform already produces the objective answer, the KB question is either
deleted or narrowed to the residual human layer.

---

## 6. Fair Housing & compliance considerations

Three independent layers protect against Fair Housing / steering violations:

1. **Authoring (wording).** No question references or invites protected-class, demographic, or
   "type of person" framing. Fit/lifestyle questions are anchored on **property features and stated
   use** ("What type of lifestyle or everyday living is this property suited for?",
   "What features make this rental enjoyable for the right tenant?") — never on who lives there.
   Placeholders model compliant example answers.
2. **Tenant viewer redaction.** `AskAiViewerAuthorizationService` redacts applicant-sensitive fields
   for non-owner/unauthorized viewers (fail-closed to `public`). FCRA-adjacent fields (credit,
   eviction, criminal, bankruptcy, income) are never exposed to anyone but the owner. The three new
   Tenant Residential questions are **non-sensitive** (work arrangement / commute / deal-breakers
   reveal needs, not FCRA data); confirm with the privacy owner before GA if `tenant_work_habits` is
   ever treated as employment-adjacent.
3. **Answer-time guardrail.** `AskAiComplianceGuardrailService` deterministically drops any sentence
   matching `protected_class`, `demographic`, `steering`, `advice`, `legal_conclusion`, or
   `financial_advice`, and neutralizes unquoted superlatives. If all content is stripped, a compliant
   withheld-fallback message is returned. A persistent educational disclaimer is attached to every
   answer.

The AI never frames any answer as an approve/deny recommendation, negotiation coaching, or
investment advice.

---

## 7. Content governance rules (for future contributors)

- **Never rename a question key.** Stored answers bind to keys (`listing_ai_faq[<key>]`,
  `ai_faq_answers`). Renaming orphans data. (The legacy tenant `faq_q*` keys are opaque but must be
  preserved; a future normalization requires an answer migration.)
- **Additions are additive and gated.** New questions go into the correct `universal` or
  property-type group; residential-only content must never live in a universal subsection (or it
  leaks to all types).
- **Verify before adding.** Check the candidate against: structured fields, Location DNA, Property
  DNA, Match, and every existing KB question. If it overlaps a structured field, either drop it or
  reword to capture only the residual human layer.
- **Compliance wording is mandatory**, not optional polish — anchor on property/needs.
- **`source` is documentation intent**, not runtime wiring (§2).

---

## 8. How the Knowledge Base powers the platform

The KB is the human-authored substrate the rest of the intelligence stack draws on. Each answer is
optional, but when present it improves:

| System | How the KB feeds it |
|---|---|
| **Ask AI** | Primary answer source. Creator answers (via `ai_faq_answers` / `listing_ai_faq`) let the chatbot answer counterparty questions accurately instead of returning insufficient-context. |
| **Property DNA** | Human context (`seller_emotional_hook`, `seller_guest_compliments`, `commercial_visibility_signage`) enriches AI-generated property intelligence beyond what structured fields express. |
| **Buyer / Tenant DNA** | Buyer/Tenant answers (`buyer_deal_breakers`, `buyer_compromise_areas`, `tenant_work_habits`, `tenant_commute_priorities`) sharpen the AI-generated profile of what the party actually needs. |
| **Location DNA** | KB questions are deliberately *complementary* to Location DNA — human location nuance (`nearby_amenities_description`, `commute_options_access`) layers on top of objective POI/commute/school data rather than duplicating it. |
| **Match Score** | Deal-breakers, compromise areas, ideal-use/operator/tenant fit, and work/commute priorities give the match engine qualitative signal that structured criteria alone miss. |
| **Target Market Intelligence** | Ideal-use/operator/tenant-fit and guest-compliment signals help identify and describe the audience a listing best serves — by use and lifestyle, never demographics. |
| **Marketing content** | Emotional hook, guest compliments, best-showing-moment, and standout features are direct fuel for AI-generated marketing copy. |
| **Listing descriptions** | Human narrative + disclosed condition/systems produce richer, more accurate auto-generated descriptions. |
| **Social media generation** | Short, evocative answers (best-showing-moment, guest compliments) map naturally to social captions and posts. |
| **Email generation** | Objection intelligence (`seller_buyer_feedback`), flexibility, and fit power personalized outreach and follow-up emails. |
| **Future AI capabilities** | Because answers are keyed, optional, and compliance-clean, they form a durable, extensible substrate for future agents (showing recommendations, negotiation-safe summaries, automated Q&A, buyer-property matching narratives) without schema churn. |

---

## 9. v1.1+ backlog (deferred, non-blocking)

1. **Conditional display engine** — show a question only when its structured field is truthy:
   `pool_spa_equipment_condition` (pool/spa), `solar_panels_owned_leased` (solar), `faq_q8` pet
   deposit (applicant-has-pets). Requires a `show_if` schema + field values plumbed into the shared
   partial across the 8 include sites.
2. **Gating refinement** — consider promoting `landlord_management_style` (± `landlord_tenant_fit`)
   to `universal` so commercial-lease listings receive it.
3. **Enrichment questions** — `buyer_future_plans`, `biz_operator_intent`,
   `landlord_long_term_strategy`, `commercial_signage_rights`, `tenant_signage_needs`.
4. **Structured-field promotion** — deterministic values (`commercial_restroom_count`,
   `commercial_ada_accessibility`, `commercial_electrical_capacity`, `professional_management`,
   `furnished_or_unfurnished`) could become structured fields for direct Match/DNA consumption.
5. **Legacy key normalization** — the opaque tenant `faq_q*` keys, with an answer migration.

---

*This document defines the intent behind the v1.0 Ask AI Knowledge Base. Extend it — do not
overwrite its reasoning — as the KB evolves.*
