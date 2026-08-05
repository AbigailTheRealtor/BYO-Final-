# Hire Agent M7.2 — the flag-off guarantee changes from literal bytes to normalised DOM

**Status:** DECIDED, and deliberate. Applies from M7.2 onward.
**Recorded:** 2026-08-05, at the close of M7.2 (section card decomposition).
**Scope:** the Hire Agent listing detail page only. M7.1's guarantee and its assertions are
untouched and still describe what M7.1 shipped.

---

## The change in one sentence

Through M7.1, "the redesign flag off changes nothing" meant the rendered page was **identical to
the byte**. From M7.2 it means the rendered **DOM is identical** — same elements, in the same
order, with the same attributes, ids, text and counts — with inter-element whitespace excluded.

## Why the weaker form is the right one for this milestone

M7.1 changed a class string. It moved no markup, so nothing could shift, and literal byte identity
cost nothing to hold.

M7.2 is a **component extraction**. Eight sections of `resources/views/hire_landlord_agent/view.blade.php`
move inside `x-hire-agent.detail-section`, and markup written at the caller's indentation is now
written at column 0 of the component file. The rendered page shifts by exactly that difference —
measured on the landlord page, the legacy section header emits at 8 spaces where it previously
emitted at 16. No tag, attribute, id, class or text changes. Only whitespace *between* elements,
which has no layout effect in HTML.

Extraction necessarily relocates markup. There is no version of this milestone that both produces
a component and preserves the original column depths.

## The two ways to keep literal bytes, and why both were rejected

1. **Inline the conditional card markup at all eight call sites**, so nothing moves. Keeps the
   bytes, loses the component: unbalanced `<div>` open/close in separate `@if` branches, eight
   times over, in a 3,000-line view, with card chrome hand-rolled instead of reusing
   `x-viho.card`.
2. **An `indent` prop** paying back each caller's original column depth. Keeps the bytes and
   encodes whitespace into the component's public API — a value that differs per call site
   (Property Details sits at 20, the rest at 8) and that a reader has to maintain by hand.

Both trade component quality for whitespace. The decision was to keep the component and hold the
guarantee that carries meaning for a user of the page.

## How the weaker guarantee is actually verified

Not by assertion in prose. Two layers:

**Out-of-band, pre/post.** The pre-change view was extracted from the merge-base commit
(`4d9c74961`) into a directory prepended to Laravel's view finder, so both the pre-change and the
post-change landlord page render **in the same process** against the **same listing fixture**. Both
trees are canonicalised — element name, attributes sorted by name, text with whitespace runs
collapsed, empty text nodes dropped — and compared. They are identical, for the owner and for an
anonymous visitor.

The probe was confirmed to be sensitive rather than trivially passing: injecting a single extra
attribute (`data-probe-canary="1"`) into the pre-change copy produced a failure naming exactly that
attribute. The probe is a temporary harness and is not committed — a permanent test only ever sees
the tree it was checked out against, which is the same limitation M7.1 recorded.

**Committed regression net.** `tests/Feature/HireAgent/HireAgentSectionCardDomEquivalenceTest.php`
pins the legacy shape tightly enough that any M7.2 markup leaking into the flag-off branch fails an
assertion instead of shipping, for **all four roles**:

- exactly one listing card in the main column (nine would mean section cards leaked),
- zero `hla-section-*` anchors and zero nav targets,
- the full section-heading list, in document order, as measured on the pre-change render,
- the legacy `<hr>` separators still present,
- the component's legacy branch adds no element, class or wrapper of its own,
- the component reads no user, route or config, so it can make no visibility decision.

## What did **not** change

- `HireAgentDetailShellLayoutTest` — M7.1's suite — is not modified by this milestone.
- Proposal privacy, `HireAgentProposalAccess`, `restrictLoadedProposals`, the compensation
  visibility gates (`Auth::check()` **and** `$hasLandlordBrokerCompData`), and M6 document
  authorization are untouched. Every section card opens **inside** its guards, never around them.
- Seller, buyer and tenant detail views are not edited at all, so their output is byte-identical by
  construction. Role scope is enforced by which files render the component, not by the flag's role
  allowlist.

## The rule going forward

A milestone that only changes values may hold literal byte identity, and should. A milestone that
**moves markup** holds normalised DOM identity, states that it is doing so, and proves it against
the pre-change tree rather than asserting it.
