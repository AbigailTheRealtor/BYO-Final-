# Hire Agent Listing Framework — Milestone 2 Validation Record

**Status:** Milestone 2 first checkpoint **APPROVED** by the owner on 2026-07-30.
**Companion to:** `hire-agent-listing-framework-implementation-plan.md` §2 (authorized scope).
**Subject commit:** `f7e829bf888b13af663b887b67c25bd119a2cbab`

This record exists because the Milestone 2 implementation was reconstructed after a container
reset, recovered from an orphaned Git index rather than from a commit, and then validated
against a baseline that had to be re-measured rather than trusted. The evidence chain is
therefore worth writing down: a future reader needs to know not just that validation passed,
but on what basis it could be believed.

---

## 1. Preservation commit

The reconstructed implementation survived a container reset only as **staged index entries in a
deleted worktree's orphaned index file** (`.git/worktrees/ux-hire-agent-create-offer-parity/index`).
It was not reachable from any commit, branch, stash, or dangling object. Confirmed at the time:
`git stash list` held nothing related, and no dangling commit carried a committer date later
than the parent commit.

Preservation was performed **without a working tree**, because the worktree directory no longer
existed:

| Step | Value |
|------|-------|
| Tree written from the orphaned index (`git write-tree`) | `7def822e8e89d928396dbf8fda7dceb22c51edbf` |
| Parent | `8e9f572c9559227fd1ad3e9aee6cf53ecd1d9926` |
| Commit (`git commit-tree`) | `f7e829bf888b13af663b887b67c25bd119a2cbab` |
| Branch ref updated (`git update-ref`, compare-and-swap on the old value) | `ux/hire-agent-create-offer-parity` |

The ref update supplied the expected old value, so it would have failed rather than clobbered
had the ref moved concurrently. The commit was verified reachable (absent from
`git fsck --dangling`) before any further work, which is what took the blobs out of reach of
garbage collection. Nothing was checked out into the primary workspace, and the spatial branch
was never touched.

Message: `feat(hire-agent): preserve Milestone 2 privacy checkpoint recovery`

---

## 2. Validation methodology

Validation ran in a **recreated isolated worktree** with a **real vendor directory**, not a
symlink. This distinction is load-bearing: a symlinked `vendor` would make every autoloaded
dependency resolve through `/home/runner/workspace`, which would defeat the isolation the
worktree exists to provide.

Isolation was proven rather than assumed — `App\`, `Tests\`, `Database\Factories\`,
`Database\Seeders\`, the Composer `ClassLoader`, `app/helpers.php`, and Laravel's
`base`/`config`/`database`/`storage`/`app`/`resource`/`lang` paths all resolve inside the
worktree, and **0 of 801 loaded files** resolved through the primary workspace.

Database identity was confirmed twice: by direct inspection of the resolved connection
(sqlite / `:memory:` / empty `url` / no host / `DATABASE_URL` falsy on every surface) and by the
30-test `tests/Feature/Safeguards` suite, which asserts the resolved connection is neither
PostgreSQL nor MySQL, references nothing named `heliumdb`, and that no connection was opened
against a networked database.

### 2.1 The 512 MB harness ceiling

The first full-suite attempt **crashed and was discarded** — `Allowed memory size of 536870912
bytes exhausted`, exit 255. `phpunit.xml` sets `<ini name="memory_limit" value="512M"/>`, and
PHPUnit applies its `<php>` block *after* the CLI, so a `-d memory_limit=2G` on the command line
is silently overridden. This ceiling is already recorded as a known harness defect in
`docs/launch-audits/PHASE-14-F2-regression-classification.md` §E2.

The suite was therefore run under a **scratchpad 2 GB configuration** — a PHPUnit config held
outside the project tree, with absolute paths anchored to each worktree, `memory_limit` raised
to 2G, and every `force="true"` environment attribute reproduced verbatim (those attributes are
load-bearing against Replit's injected `DATABASE_URL`, `DB_CONNECTION`, `DB_DATABASE` and
`GOOGLE_PLACES_*` process variables). The repository's `phpunit.xml` was **not** edited: it is a
config file, and the Milestone 2 production-file guard would have correctly flagged the change.

### 2.2 The baseline was re-measured, not trusted

The Milestone 1 baseline record stored **counts only** — no list of failing tests — so a
count-level comparison could not have distinguished "no regression" from a compensating swap
(one failure disappearing while another appeared). A read-only worktree was created at the
parent commit `8e9f572c9` and the identical suite was run there.

The two runs were executed **sequentially, never concurrently**, so CPU contention could not
perturb timing-sensitive tests in one run but not the other.

The re-measured baseline reproduced the recorded Milestone 1 figures **exactly on all six
metrics**, which is what licenses any comparison drawn from it:

| Run | Tests | Assertions | Errors | Failures | Skipped | Incomplete | Exit |
|-----|------:|-----------:|-------:|---------:|--------:|-----------:|-----:|
| Milestone 1 baseline (recorded) | 9,431 | 110,910 | 53 | 288 | 26 | 4 | — |
| Baseline re-measured @ `8e9f572c9` | 9,431 | 110,910 | 53 | 288 | 26 | 4 | 2 |
| Milestone 2 @ `f7e829bf8` | 9,551 | 110,028 | 53 | 288 | 26 | 4 | 2 |

Exit code 2 is PHPUnit's normal code for a completed run containing failures — not a crash. It
is expected on both sides given 341 pre-existing failure/error entries.

Targeted runs, all exit 0: `HireAgentProposalAccessTest` (69 passed),
`HireAgentDetailViewPrivacyTest` (51 passed), the production-file guard (1 passed), and the
database safeguards (30 passed).

---

## 3. JUnit identity comparison

Both runs emitted JUnit XML, and failures were compared **by test identity**, not by count.

```
baseline failing entries    : 341
milestone 2 failing entries : 341

IDENTICAL   : 341
NEW         : 0
CHANGED     : 0
DISAPPEARED : 0
```

34 entries differed in raw failure text. Each divergence was located to a character offset and
attributed before being set aside — every one fell inside a dumped response body, and every one
was a value that differs between any two runs of identical code:

- unseeded Faker person names rendered into the nav bar,
- regenerated CSRF tokens,
- random model `short_id`s and `BAA-`/`BCA-`-style reference codes,
- wall-clock timestamps in `INSERT` statements,
- compiled-Blade cache filenames (a hash of the view path, which necessarily differs per worktree).

After normalising only those classes of value, **zero divergences remained outside a markup
dump**. Purely scalar assertion messages were left fully intact during normalisation, so a
genuine change to a scalar assertion would still have surfaced as `CHANGED`.

### 3.1 Pre-existing Hire Agent failures

`HireAgentDirectReadOnlyReviewTest` carries **10 failures**. The failing set is identical at
baseline and at Milestone 2, so these are pre-existing and untouched by this checkpoint. They
are inside the 341 and are **not** a Milestone 2 regression. They remain open work, unrelated to
proposal privacy.

---

## 4. Zero new regressions

No new failures, no changed failures, no disappeared failures. **No production code was changed
during validation** — no test proved a regression, so nothing warranted a change.

The 120 new tests all pass and account for the entire test-count delta
(9,551 − 9,431 = 120), split 69 / 51 across the two focused files. Neither file exists at
baseline, so the delta is fully attributed.

---

## 5. Assertion reconciliation

The assertion total **fell by 882** while the test count rose by 120. That combination looks
wrong at a glance and is worth explaining precisely, because the obvious reading — that
assertions were lost — is not what happened.

```
+  312   assertions contributed by the 120 new tests
- 1,194   assertions from ONE pre-existing shared test
-------
-  882   exactly the observed delta
```

The single test is
`Tests\Feature\Storage\PublicMediaViewSmokeTest::test_no_legacy_media_url_idiom_remains`. It
iterates a fixed list of 8 views and makes **3 assertions per non-comment line**. Two of those
8 views are inside Milestone 2's authorized scope, and the checkpoint removed competing-proposal
markup from them:

| View (present in that test's `VIEWS` list) | Non-comment lines | Δ |
|---|---|---:|
| `resources/views/hire_buyer_agent/view.blade.php` | 4,491 → 4,226 | −265 |
| `resources/views/hire_tenant_agent/view.blade.php` | 4,849 → 4,716 | −133 |
| | **−398** | **× 3 = −1,194** |

`hire_seller_agent` and `hire_landlord_agent` also shrank, but are **not** in that test's `VIEWS`
list — which is why the arithmetic lands on −1,194 and not −1,728. The test **passes in both
runs**. The metric moved because the code did what the checkpoint intended: fewer assertions
here is the direct consequence of deleting disclosure markup, not a loss of coverage.

Anyone re-running this suite after further view edits should expect this number to move again,
and should reconcile it the same way rather than treating it as a regression signal.

---

## 6. Privacy confirmations

All from the full-suite JUnit log, all passing, all four roles covered.

- **Owner review intact** — 41 tests (seller 10 / buyer 10 / landlord 11 / tenant 10):
  `owner_is_served_every_proposal`, `listing_owner_may_review_all_proposals`,
  `owner_is_served_the_rival_proposal_card`, `owner_does_see_rival_data_that_competitors_cannot`,
  `restrict_loaded_proposals_preserves_the_full_set_for_the_owner`, plus the owner-only empty state.
- **Own-proposal visibility intact** — 57 tests: `agent_is_served_only_their_own_proposal`,
  `submitting_agent_may_view_their_own_proposal`, `own_proposal_signals_survive_restriction`.
- **Competing-agent disclosure denied across all four roles** — 38 tests, **9 per role**:
  `competing_agent_cannot_view_another_agents_proposal`,
  `competing_agent_is_not_served_the_rival_proposal_card`,
  `competing_agent_never_sees_rival_amount`, `competing_agent_sees_no_removed_disclosure_copy`,
  `agent_who_has_not_bid_is_served_nothing`, `guest_is_served_nothing`,
  `restrict_loaded_proposals_empties_the_relation_for_a_stranger`.

`administrator_is_served_nothing_by_this_checkpoint` passes for all four roles, confirming that
deny-by-default holds and that **no administrator access path was added** — §2.2 satisfied in its
owner half only, by design.

---

## 7. Scope verification

The commit changes **exactly 12 paths** and nothing else:

```
A  app/Services/HireAgent/HireAgentProposalAccess.php
M  app/Http/Controllers/SellerAgentAuctionController.php
M  app/Http/Controllers/BuyerAgentAuctionController.php
M  app/Http/Controllers/LandlordAgentAuctionController.php
M  app/Http/Controllers/TenantAgentAuctionController.php
M  resources/views/hire_seller_agent/view.blade.php
M  resources/views/hire_buyer_agent/view.blade.php
M  resources/views/hire_landlord_agent/view.blade.php
M  resources/views/hire_tenant_agent/view.blade.php
A  tests/Feature/HireAgent/HireAgentProposalAccessTest.php
A  tests/Feature/HireAgent/HireAgentDetailViewPrivacyTest.php
M  tests/Feature/Offers/OfferWorkflowReadinessTest.php
```

Independently corroborated by tree size: the parent tree holds 23,153 files and the preserved
tree holds 23,156 — exactly the three additions, confirming nothing else entered or left.

---

## 8. Protected-path verification

Every protected path was verified **byte-identical** between `8e9f572c9` and `f7e829bf8` by
comparing blob SHAs, which is stronger than reading a diff — it cannot be fooled by
whitespace-equivalent or re-ordered content.

| Protected path | Result |
|---|---|
| `resources/views/offer-listing/partials/_competing-bids.blade.php` (Create Offer) | UNCHANGED |
| `app/Helpers/{Seller,Buyer,Landlord,Tenant}BidMatchScoreHelper.php` | UNCHANGED |
| `app/Services/CompatibilityScoreService.php` | UNCHANGED |
| `app/Services/ScoreBreakdownService.php` | UNCHANGED |
| `app/Http/Controllers/CompetingBidsController.php` | UNCHANGED |
| `app/Services/CompetingBidsService.php` | UNCHANGED |
| `app/Models/BiddingPeriodAgentMapping.php` | UNCHANGED |
| `resources/views/tenant_agent/competing_bids.blade.php` | UNCHANGED |
| `routes/web.php`, `routes/api.php` | UNCHANGED |

Whole-tree diffs across `database/`, `config/`, Create Offer
(`app/Http/Livewire/OfferListing/**`, `resources/views/offer-listing/**`), Buyer Criteria and
Service Auction returned **empty**. No migration, no schema change, no route change.

**Path correction for future readers:** `CompatibilityScoreService` lives at
`app/Services/CompatibilityScoreService.php`, **not** at
`app/Services/Dna/Compatibility/CompatibilityScoreService.php`. Verification was performed at
the real location. A protected-path check written against the wrong path would silently pass
while checking nothing.

---

## 9. Stopping point

Per plan §2.4, this checkpoint stops before deleting any legacy route, controller, service,
model, or the dedicated competing-bids view. Those components remain in place, unreferenced by
the Hire detail views but otherwise intact.

Not done, deliberately: no amend, no merge, no push, no legacy deletion sequence.
**Milestone 3 is not opened.**
