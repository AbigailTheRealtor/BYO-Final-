<?php

/*
|--------------------------------------------------------------------------
| Listing Preference Reasons — the ONE structured reason vocabulary
|--------------------------------------------------------------------------
|
| The chips a Buyer or Tenant may select after Save, Maybe or Pass.
|
| READ ONLY THROUGH App\Support\ListingPreferences\ListingPreferenceConfig.
| A test asserts it, for the same reason the Smart Tag taxonomy has one reader:
| a Blade view or a controller must not grow its own idea of what a reason means.
|
| GOVERNANCE — read docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md
| before editing.
|
| THIS FILE IS NOT A SECOND TAXONOMY. A reason that describes a property
| characteristic MUST link to a canonical Smart Tag rather than restate it.
| ListingPreferenceReasonCatalog::validationErrors() fails the build when a
| `smart_tag` reason names a key that is not canonical, active, seeker-selectable
| and past compliance review — so the Fair Housing exclusions already encoded in
| config/smart_tags.php (accessible_features, playground) apply here with no
| second list to maintain.
|
| DIMENSIONS — what kind of thing the reason is about, and whether it can ever
| become a learned signal:
|
|   smart_tag    A canonical property characteristic. `smart_tag` names the key.
|                LEARNABLE.
|   criteria     Structured search criteria — price, size, fees. Smart Tags
|                deliberately exclude these (see the taxonomy header), so they
|                are NOT forced into tags. `criteria_dimension` names the
|                BuyerMatchScorer category. LEARNABLE.
|   location     Proximity and the user's own stated places. Location DNA's
|                territory, never a tag. LEARNABLE ONLY against the user's OWN
|                Important Places / commute anchors — never neighbourhood
|                identity, and never other users' behaviour.
|   unspecified  A real thing a customer wants to say that has no structured
|                counterpart today ("Layout", "Other"). Captured for product
|                insight. NEVER LEARNED — there is nothing to learn it against,
|                and guessing would be inventing a vocabulary.
|
| STATES — which of Save / Maybe / Pass a reason may be offered for. The three
| prompts ask different questions ("What do you like…", "What are you unsure
| about?", "Why isn't this one for you?"), so a reason that only makes sense as
| a complaint is not offered as a compliment.
|
| KEYS ARE PERMANENT. Retire with 'status' => 'retired'; never reuse or rename,
| because stored listing_preference_reasons rows reference them.
|
| FREE TEXT: there is none. `other` records that a customer had a reason we do
| not model; it carries no text field in Phase 1, and any later text box is
| stored-but-never-parsed unless separately governed (see the governance doc).
|
| FIELDS
|   label              customer-facing chip text
|   dimension          smart_tag | criteria | location | unspecified
|   smart_tag          canonical tag key; required iff dimension is smart_tag,
|                      and must be null otherwise
|   criteria_dimension BuyerMatchScorer category; required iff dimension is
|                      criteria, null otherwise
|   states             which of save | maybe | pass may offer this reason
|   status             active | retired
|   display_order      chip order within the prompt
|
*/

$order = 0;

$reason = static function (
    string $label,
    string $dimension,
    array $states,
    array $opts = []
) use (&$order): array {
    $order += 10;

    return array_merge([
        'label'              => $label,
        'dimension'          => $dimension,
        'smart_tag'          => null,
        'criteria_dimension' => null,
        'states'             => $states,
        'status'             => 'active',
        'display_order'      => $order,
    ], $opts);
};

$SAVE  = 'save';
$MAYBE = 'maybe';
$PASS  = 'pass';

$ALL    = [$SAVE, $MAYBE, $PASS];
$LIKED  = [$SAVE, $MAYBE];
$AGAINST = [$MAYBE, $PASS];

return [

    'version' => '2026-09-16.1',

    'reasons' => [

        // ── Structured criteria ─────────────────────────────────────────────
        // Price and size are search criteria, not Smart Tags. The taxonomy
        // header excludes them by name; they are scored by BuyerMatchScorer.
        'price'          => $reason('Price', 'criteria', $ALL, ['criteria_dimension' => 'price']),
        'too_expensive'  => $reason('Too expensive', 'criteria', $AGAINST, ['criteria_dimension' => 'price']),
        'too_small'      => $reason('Too small', 'criteria', $AGAINST, ['criteria_dimension' => 'size']),
        'too_large'      => $reason('Too large', 'criteria', $AGAINST, ['criteria_dimension' => 'size']),
        'hoa_fees'       => $reason('HOA / fees', 'criteria', $ALL, ['criteria_dimension' => 'financial']),

        // ── Location ────────────────────────────────────────────────────────
        // Never a Smart Tag. Learnable only against the customer's OWN stated
        // Important Places and commute anchors — see the governance doc's
        // prohibition on collaborative and neighbourhood-level location learning.
        'location_proximity' => $reason('Location / proximity', 'location', $ALL),
        'commute'            => $reason('Commute', 'location', $ALL),
        // "Busy road" is deliberately NOT mapped to highway_frontage: that tag
        // means the parcel itself fronts a highway, which is a different claim
        // from a customer's judgement about traffic outside this house.
        'busy_road'          => $reason('Busy road', 'location', $AGAINST),

        // ── Property characteristics (canonical Smart Tags) ──────────────────
        'updated_kitchen'      => $reason('Updated kitchen', 'smart_tag', $LIKED, ['smart_tag' => 'updated_kitchen']),
        'quartz_countertops'   => $reason('Quartz countertops', 'smart_tag', $LIKED, ['smart_tag' => 'quartz_countertops']),
        'updated_bathrooms'    => $reason('Updated bathrooms', 'smart_tag', $LIKED, ['smart_tag' => 'updated_bathrooms']),
        'open_floor_plan'      => $reason('Open floor plan', 'smart_tag', $LIKED, ['smart_tag' => 'open_floor_plan']),
        'natural_light'        => $reason('Natural light', 'smart_tag', $ALL, ['smart_tag' => 'natural_light']),
        'private_pool'         => $reason('Pool', 'smart_tag', $ALL, ['smart_tag' => 'private_pool']),
        'waterfront'           => $reason('Waterfront', 'smart_tag', $ALL, ['smart_tag' => 'waterfront']),
        'garage'               => $reason('Garage', 'smart_tag', $ALL, ['smart_tag' => 'garage']),
        'fenced_yard'          => $reason('Fenced yard', 'smart_tag', $ALL, ['smart_tag' => 'fenced_yard']),
        'move_in_ready'        => $reason('Move-in ready', 'smart_tag', $LIKED, ['smart_tag' => 'move_in_ready']),
        'needs_complete_update' => $reason('Needs too much work', 'smart_tag', $AGAINST, ['smart_tag' => 'needs_complete_update']),

        // ── Captured, never learned ─────────────────────────────────────────
        // Real customer language with no structured counterpart today. Adding a
        // Smart Tag for one of these is a taxonomy change under the Smart Tags
        // governance process, never an edit here.
        //
        // Generic "Style" is deliberately ABSENT in V1: there is no well-defined
        // style taxonomy, and a vague style tag would be exactly the duplicate
        // vocabulary this file exists to prevent. Specific architectural tags
        // may be linked individually if and when they are added.
        'layout'         => $reason('Layout', 'unspecified', $ALL),
        'kitchen'        => $reason('Kitchen', 'unspecified', $ALL),
        'bathrooms'      => $reason('Bathrooms', 'unspecified', $ALL),
        'condition'      => $reason('Condition', 'unspecified', $ALL),
        'outdoor_space'  => $reason('Outdoor space', 'unspecified', $ALL),
        'other'          => $reason('Other', 'unspecified', $ALL),
    ],
];
