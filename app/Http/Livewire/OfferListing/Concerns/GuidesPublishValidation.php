<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Guided correction for Offer-Listing publish failures (Seller + Landlord).
 *
 * THE PROBLEM THIS SOLVES
 * -----------------------
 * The publish rules in SellerPublishValidation / LandlordPublishValidation cover
 * fields spread across every wizard tab, but Submit is pressed from whichever tab
 * the user happens to be on. When a rule fails for a field on a *different* tab,
 * the user saw a terse "Missing/invalid: ..." banner and no way to tell which
 * field, on which tab, was at fault.
 *
 * The wizard's own client-side gate could not close the gap either: it collected
 * candidates with `isElementVisible()`, which is false for anything on an
 * inactive tab, so it only ever inspected the tab in front of the user. Phase 0
 * made this visible by adding the first genuinely required field that does not
 * live on the last tab — `address`, on Property Details.
 *
 * WHAT THIS TRAIT PROVIDES
 * ------------------------
 *   1. {@see publishRequiredFieldNames()} — the fields the server *actually*
 *      requires, derived from the role's own `getConditionalRules()` so it can
 *      never drift from the rules it mirrors. The client gate is scoped to this
 *      list; the ~25 DOM fields carrying a bare `required` attribute are NOT
 *      publish blockers and must not become ones.
 *   2. {@see guidePublishValidationFailure()} — hands the failing field names and
 *      their messages to the browser, which resolves each field's owning tab from
 *      the DOM and navigates there. Livewire state is untouched, so every entered
 *      value — including an in-progress draft — survives the round trip.
 *
 * Tab ownership is resolved in the browser (`field.closest('.tab-pane')`) rather
 * than from a server-side index map: the panes are conditionally rendered per
 * role and service type, so a hardcoded index would be wrong for some
 * combinations and silently send the user to the wrong tab.
 */
trait GuidesPublishValidation
{
    /**
     * Field names carrying a `required` rule in the role's current publish rules.
     *
     * Conditional rules are honoured automatically because `getConditionalRules()`
     * is evaluated against live component state — e.g. `auction_time` appears only
     * for a Bidding Period listing, so the client gate demands it only then.
     *
     * Element rules (`roof_type.*`) are skipped: they constrain the members of an
     * array that is itself optional, and they have no single DOM field to focus.
     *
     * @return array<int,string>
     */
    public function publishRequiredFieldNames(): array
    {
        $required = [];

        foreach ($this->getConditionalRules() as $field => $rule) {
            if (str_contains($field, '.')) {
                continue;
            }

            $tokens = is_array($rule) ? $rule : explode('|', (string) $rule);

            foreach ($tokens as $token) {
                // Rule objects (ValidStreetAddress, Rule::in(...)) are not `required`;
                // only the literal string token makes a field mandatory.
                if (is_string($token) && $token === 'required') {
                    $required[] = $field;
                    break;
                }
            }
        }

        return array_values(array_unique($required));
    }

    /**
     * Tell the browser which publish fields failed and why, so it can navigate to
     * the tab owning the first one, reveal the banner and focus the field.
     *
     * Only the first message per field is sent — the banner lists one line per
     * field, and the inline `@error` under the input renders the full set anyway.
     */
    protected function guidePublishValidationFailure(ValidationException $e): void
    {
        $errors = $e->errors();

        if ($errors === []) {
            return;
        }

        $this->dispatchBrowserEvent('publish-validation-failed', [
            'fields'   => array_keys($errors),
            'messages' => array_map(
                static fn ($messages) => is_array($messages) ? ($messages[0] ?? '') : (string) $messages,
                $errors
            ),
        ]);
    }
}
