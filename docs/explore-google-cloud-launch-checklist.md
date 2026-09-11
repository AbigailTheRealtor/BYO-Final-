# Explore — Google Cloud safety checklist (manual, before any public activation)

**Nothing in this repository can perform these steps.** Google Cloud configuration lives outside
the codebase, cannot be inferred from it, and was deliberately not touched by the worktree that
wrote this file. This is the list a person works through in the console before
`EXPLORE_GOOGLE_MAPS_BROWSER_KEY` is set anywhere.

It exists because the code-side guarantees — one script load per page, one 3D world per page
lifecycle, no retry loop, no Places/Geocoding/Routes/Roads, a kill switch that prevents the
loader from running at all — bound what *our* page does. They do not bound what a leaked or
over-permissive key does once it is in the world. Defence in depth, and these are the layers the
application cannot provide for itself.

---

## 1. The credential

- [ ] **Create a NEW browser key used by nothing else.** Do not reuse `GOOGLE_PLACES_API_KEY`.
      That is a *server* key for address validation and POI lookup; a server key in a browser is
      readable by every visitor, and a shared key cannot be revoked for one surface without
      breaking the other.
- [ ] Name it so its blast radius is obvious in the console — e.g. `explore-3d-browser-<env>`.
- [ ] **One key per environment.** A staging key that also works in production means a staging
      incident is a production incident.

## 2. Restrict where it works

- [ ] **Application restriction → HTTP referrers (web sites).** An unrestricted browser key is a
      public key: it is visible in page source by design, and anyone can put it on their own site
      and bill you.
- [ ] List the exact origins that serve `/explore`, and nothing else. Prefer explicit hosts over
      broad wildcards; `*.example.com/*` also authorises every subdomain anyone can create.
- [ ] Do **not** add `localhost` to the production key. Give local work its own key, or none.

## 3. Restrict what it can call

- [ ] **API restriction → Maps JavaScript API only.** Explore requests the `maps3d` library and
      nothing else, and a test asserts the renderer never references Places, Autocomplete,
      Geocoding, Routes, Roads, Directions or Distance Matrix.
- [ ] **Do not enable the Places API on this key**, even "just in case". An enabled API is a
      billable surface reachable by anyone holding the key, whether or not our code calls it.
- [ ] Re-check this after any Google console change: enabling an API project-wide can widen a key
      that was previously narrow.

## 4. Quotas — the ceiling that actually stops spend

- [ ] Set **per-API daily quotas** on the Maps JavaScript API for this project, sized to the
      controlled rollout rather than to the hoped-for traffic.
- [ ] Set **per-minute / per-user quotas** where the API exposes them.
- [ ] Start deliberately LOW. Raising a quota after watching real usage is a five-minute change;
      discovering the ceiling was too high happens on an invoice.

## 5. Billing alerts — necessary, and not sufficient

- [ ] Create a **billing budget** on the project with alert thresholds (e.g. 25% / 50% / 90% /
      100%) routed to a person who reads them.
- [ ] **A billing budget does not cap spending.** It sends notifications. Google will continue to
      serve and bill past the budget unless a quota or a programmatic response stops it. Treat
      the budget as the alarm and the **quota** as the brake.
- [ ] If an automated hard stop is wanted, it has to be built (billing export → Pub/Sub → a
      function that disables the API or the key). That is a Cloud project, not an application
      change, and it is out of scope here.

## 6. Controlled rollout

- [ ] Enable for a limited audience first; keep `EXPLORE_ENABLED` off for everyone else.
- [ ] **Switch the renderer on explicitly**: `EXPLORE_GOOGLE_3D_ENABLED=true`. It ships OFF, and a
      browser key on its own turns nothing on — so the key can be set and checked first (page
      source should show `data-google-ready="0"` and no key) before anything loads.
- [ ] Load `/explore` once with the browser's network panel open and confirm exactly **one**
      `maps.googleapis.com/maps/api/js` request — and that moving the camera, holding W or an
      arrow key, dragging on a phone, opening and closing a property panel and resizing the window
      add **none**. The automated tests read the shipped JavaScript; they do not execute it.
- [ ] Watch **Google console usage** and the application's own `explore_provider` log lines side
      by side for a full traffic cycle before widening anything.
- [ ] Check that the `actor` values in those log lines are **distinct per visitor**. If every
      anonymous request resolves to one proxy address, the per-actor ceiling becomes a shared one —
      safe for cost, bad for availability — and needs addressing before widening.
- [ ] Only then consider raising quotas or the Explore provider ceilings — with the observed
      numbers in hand. The Explore ceilings ship at actor 60/hour and 300/day, global 300/hour and
      2,000/day. They are application-side and conservative on purpose; they are not a statement
      of Stellar's allowance, which is not known here, and the Bridge token is shared with MLS sync
      and the criteria searches.

## 7. Know how to stop it, before you start it

Rehearse these, in this order, and confirm each one works:

| To stop | Set | Effect |
|---|---|---|
| Google 3D only | `EXPLORE_GOOGLE_3D_ENABLED=false` | The loader never runs. No `<script>` is inserted, `maps.googleapis.com` is never contacted, and the credential is not even emitted into the HTML. Explore keeps serving listings. |
| Stellar/Bridge traffic only | `EXPLORE_PROVIDER_KILL_SWITCH=true` | No provider request. Explore serves last-known inventory and marks the response degraded. |
| Stellar discovery as a feature | `EXPLORE_DISCOVERY_ENABLED=false` | Cache-only, and the response says so. |
| Everything | `EXPLORE_ENABLED=false` | Every Explore route 404s, data endpoints included. |

**Exact values the provider switches accept** (case-insensitive; surrounding spaces ignored):

| Variable | ON / tripped | OFF / not tripped | Any other value |
|---|---|---|---|
| `EXPLORE_GOOGLE_3D_ENABLED` | `true` `1` `on` `yes` | unset, empty, `false` `0` `off` `no` | **OFF** |
| `EXPLORE_DISCOVERY_ENABLED` | `true` `1` `on` `yes` | unset, empty, `false` `0` `off` `no` | **OFF** |
| `EXPLORE_PROVIDER_KILL_SWITCH` | `true` `1` `on` `yes` | unset, empty, `false` `0` `off` `no` | **TRIPPED** |

Every one of them fails toward stopping the provider. `EXPLORE_ENABLED` is not in this table: it
uses the ordinary cast, under which `off` and `no` read as ON — type exactly `false` to turn it off.

- [ ] Confirm each is an **environment change that takes effect on restart, with no code
      deployment** in the target environment.
- [ ] In Google Cloud, also confirm you can **delete or disable the key** from the console, and
      know who has access to do it out of hours.

## 8. Never

- [ ] Never put an unrestricted key in a browser.
- [ ] Never reuse a server key client-side.
- [ ] Never commit a key. `.env` is untracked; `.env.example` documents variable **names** only.
- [ ] Never paste a key into a ticket, a chat message, or a log line.

---

**Until every box above is ticked, the correct state is the shipped one:**
`EXPLORE_GOOGLE_MAPS_BROWSER_KEY` unset, `EXPLORE_GOOGLE_3D_ENABLED` unset (off),
`EXPLORE_ENABLED=false`, `EXPLORE_DISCOVERY_ENABLED=false`. The live Google 3D visual verification
remains **BLOCKED** on that credential, and that is the expected state before launch rather than a
defect.
