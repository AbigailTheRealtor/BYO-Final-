---
name: Offer test submitter-hiding pattern
description: Offer show.blade.php hides Accept/Reject/Counter entirely for the submitter; tests for those actions need makeOfferByOtherUser helpers.
---

## Rule
Tests for Accept, Reject, or Counter visibility must use a non-submitter viewer. When a test creates an offer with `user_id = $this->user->id`, the test user becomes the submitter. `show.blade.php` computes `$actorIsSubmitter = Auth::id() === $offer->user_id` and skips Accept/Reject via `hide_for_submitter=true` and skips Counter entirely with `@if(!$actorIsSubmitter)`. Those sections never render, so any positive assertion about them fails.

## Why
The view enforces a business rule: the person who submitted an offer cannot accept, reject, or counter their own offer. That is correct product behaviour, not a test fixture bug.

## How to apply
Add `makeOfferByOtherUser()` and `makeSubmittedOfferByOtherUser()` helpers to offer test classes:

```php
private function makeOfferByOtherUser(array $attrs = []): Offer
{
    $other        = User::factory()->create();
    $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);
    return Offer::factory()->create(array_merge([
        'user_id'          => $other->id,
        'offer_auction_id' => $offerAuction->id,
    ], $attrs));
}
```

- Test user = OfferAuction owner (the listing owner / recipient)
- Offer submitter = a different factory user
- `canView` passes because `getLegitimatePartyIds()` includes `offerAuction->user_id`
- `$actorIsSubmitter=false`, so Accept/Reject/Counter sections all render

## Secondary: formaction= substring trap
The draft edit form (`_offer_terms_form.blade.php`) renders a "Save & Submit Offer" button with `formaction="…/submit"`. This contains `action="…/submit"` as a substring, so `assertStringNotContainsString('action="…/submit"', $content)` FAILS even when no dedicated submit action form exists.

Fix: use `makeOfferByOtherUser` so `$canEdit = ($isOwner && status === 'draft')` = false — the draft edit form is never included and the formaction button is absent.

## Secondary: assertSee + Blade escaping
Blade `{{ $var }}` HTML-encodes single quotes to `&#039;`. Use `assertSee($reason)` (default `$escaped=true`), which pre-escapes the needle the same way before searching. `assertSee($reason, false)` searches for raw `'` characters which won't match the encoded HTML.
