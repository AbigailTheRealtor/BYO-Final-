# Agent AI V2 activation prerequisites

**Status: NOT ready to activate. No scope may be enabled yet.**

Authoritative record of what must be true before any `AGENT_AI_V2_*` scope is turned on.
Written during the Property Q&A **P0.1** hardening pass. The executable form of this
document is `tests/Feature/AgentAi/AgentAiV2ActivationReadinessTest.php` — if the two
disagree, the test is what gates the build, but they are meant to say the same thing.

---

## The blocking prerequisite

**Existing knowledge snapshots must be rebuilt under the P0 visibility rules before any
Agent AI V2 scope is enabled.**

That rebuild has **not** been performed. It is deliberately out of scope for P0 and P0.1.

### Why

`ExtendedKnowledgeLoader::loadSnapshotFacts()` is the **only** consumer in the application
that reads the stored visibility flags on snapshot rows:

```php
$snapshot->facts()
    ->where('public_allowed', true)
    ->where('visibility', 'public_allowed')
```

Every other reader gates on `restricted` instead — `AskAiKnowledgeSearchService` (which as of
P0.1 also enforces viewer scope, see below) and `AskAiAnalyticsController`.

Snapshot rows written **before P0** carry the old **default-open** classification:
`public_allowed = true` and `visibility = 'public_allowed'` for every key that was not on the
restricted list. That set includes:

- all buyer and tenant criteria — budgets, minimum cap rate, commute and purchase intent;
- the seller's own `minimum_cap_rate` and `minimum_annual_net_income` negotiating thresholds;
- every canonical key added to the context builder after the classifier was written, which
  became public by omission rather than by decision.

P0 fixed the **writer** (`SnapshotFactVisibility` is now an intersection that fails closed to
`owner_only`). It migrated and backfilled **nothing**. So the stale rows are still there,
still flagged public.

Today that is inert, because the only thing that would read them sits behind
`CheckAgentAiV2Enabled`, which `abort(404)`s while the global flag and all five per-scope
flags are false. **Enabling any one of them** opens `/agent-ai/ask` — a route that carries
**no auth middleware** — onto those stale rows, and the pre-P0 wide fact set becomes
anonymously readable again with no code change and nothing in a diff to review.

`AgentAiV2ActivationReadinessTest` demonstrates both halves of this concretely: a stale row
is still served as public by the loader, and a row written under the P0 rules is not.

---

## Activation sequence

In this order. Do not reorder; step 3 is what makes step 5 safe.

1. **Decide and record which scope is being activated**, and why. The five scopes are
   independent; activating one does not imply the others.
2. **Inventory the affected snapshots.** Count rows in `ask_ai_facts` and `ask_ai_answers`
   whose `visibility` / `public_allowed` were written before the P0 commit, grouped by
   `listing_type`. Buyer and tenant rows should end at zero public facts; seller and landlord
   rows should end at the D1 allow-list only.
3. **Rebuild every snapshot** through `AskAiKnowledgeSnapshotBuilderService` so each fact and
   answer is reclassified by the current `SnapshotFactVisibility`. This is the blocking step.
   Treat it as a data-changing operation: take a backup-first approach, run it read-only /
   dry-run first, and verify the step-2 counts afterwards.
4. **Re-verify** that no `public_allowed = true` row survives for a buyer or tenant listing,
   and that no seller/landlord row outside the D1 allow-list survives either.
5. **Only then** set the single `AGENT_AI_V2_*` scope flag, and update the corresponding
   assertion in `AgentAiV2ActivationReadinessTest` in the same change — deliberately, so the
   activation is visible in the diff.

---

## Separate, still-open items (not prerequisites, but read before activating)

These were found by the independent P0 review. None is a regression introduced by P0 or P0.1,
and none blocks the rebuild — but each affects what Agent AI V2 would publish.

- **The 14-key allow-list / redaction disagreement.** `SnapshotFactVisibility` declares 14
  keys public (5 `hoa*`, `has_cdd`, `annual_cdd_fee` on seller; the `hoa*` keys plus
  `rent_amount` and `pet_deposit_fee_rent` on landlord) that
  `AskAiViewerAuthorizationService::RESTRICTED_COMPLIANCE_TOKENS` strips from every non-owner
  context. The stricter mechanism wins at read time, so this is fail-safe today — but the
  allow-list overstates what is publishable, and Agent AI V2 reads the allow-list, not the
  redactor. Reconcile as part of the D1 disclosure decisions.
- **`visibility` has a database default of `'public_allowed'`** on both `ask_ai_facts`
  (`2026_06_11_000002_create_ask_ai_facts_table`) and `ask_ai_answers`
  (`2026_06_11_000006_extend_ask_ai_snapshot_schema`). All four builders now always pass the
  column explicitly, so the application never relies on the default — but a future writer
  that omitted it would produce a public row that looks deliberate. Facts have a second
  column (`public_allowed`) that the P0.1 read-time guard also requires, so a fact is
  double-gated; **answers have only `visibility`**, so for answers the schema default is the
  single point of failure. Changing it needs a migration and `deploy/start-production.sh` is
  the sole migration owner — not done in P0.1; recommended follow-up.
- **The D3 knowledge-base triage** has not started. Owner-authored KB answers are currently
  `owner_only` for all four roles, which is the fail-closed position; publishing any of them
  is a per-key decision that has not been made.
- **`/api/ask-ai/ask` has no ownership check.** It authenticates via Sanctum and relies on
  scope resolution plus redaction. As of P0.1 the database-first path is scope-enforced (see
  below), which closes the owner-only leak, but the endpoint still permits cross-listing
  questions by design for external channels.

---

## What P0.1 already did (so it is not re-litigated at activation time)

- Removed `annual_noi => minimum_annual_net_income` and `cap_rate => minimum_cap_rate` from
  `SellerListingLoader` — the same seller-minimum misrepresentation P0 removed from the Ask
  AI context map, still live in the Agent AI pipeline. Guarded by
  `tests/Unit/AgentAi/AgentAiSellerMinimumExposureGuardTest.php`, which includes a source
  scan so the alias cannot return under a different spelling.
- Added **read-time** viewer-scope enforcement to `AskAiKnowledgeSearchService`: `restricted`
  stays blocked for every scope including the owner; `owner_only` reaches the owner only;
  `public_allowed` reaches everyone. An absent or unrecognised `viewer_scope` fails closed to
  `public`. Guarded by `tests/Feature/AskAi/PropertyQaP01ReadTimeVisibilityTest.php`.
- Added builder-persistence coverage proving all four builders store KB answers as
  `owner_only` — `tests/Feature/AskAi/PropertyQaP01KnowledgeBaseAnswerVisibilityTest.php`.
- Corrected the comments claiming `faq_answers.current_cap_rate` and
  `faq_answers.annual_net_operating_income` are seller-answerable. They are declared in
  `AskAiFieldQuestionRegistryService` but absent from `config/ai_faq_seller.php`, so no seller
  can author them and both resolve to "information not provided". **That is the intended
  behaviour**: with no verified actual cap rate or NOI, the system must not state one. Whether
  to add the two KB questions is a D3 decision.
