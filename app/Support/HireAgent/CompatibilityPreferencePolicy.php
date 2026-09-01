<?php

namespace App\Support\HireAgent;

/**
 * CompatibilityPreferencePolicy — what a listing's compatibility blob may contain.
 *
 * THE ONE PLACE THAT ANSWERS "which keys may a listing of this role and property type hold?",
 * and the only reader of config/hire_agent_compatibility_keys.php.
 *
 *
 * ── WHY THIS IS A PERSISTENCE CONCERN AND NOT A VALIDATION ONE ──────────────────────────
 *
 * `$compatibility_preferences` is a public Livewire property. A client can set any nested
 * path inside it through the ordinary syncInput message — Livewire has no notion of which
 * sub-keys the form actually rendered. Laravel's validate() then checks the keys named in
 * rules() and leaves every other key sitting on the property, because stripping unlisted
 * input is not what it does. And the persist wrote the sub-array through unchanged:
 *
 *     $stored[$roleKey] = $this->compatibility_preferences[$roleKey];   // verbatim
 *
 * So before this class existed, a crafted request could write any key it liked into a
 * listing's stored blob, and hiding a control in Blade stopped exactly nobody. `prohibited`
 * rules narrow that only on the paths that reach full validation; a draft save does not.
 *
 * The projection therefore runs at the WRITE, not at validation, and every write path goes
 * through it: Create, Save Draft, Save Edit, and the legacy per-role create components.
 *
 *
 * ── INTERSECTION, NOT SUBTRACTION ───────────────────────────────────────────────────────
 *
 * A key survives by being NAMED in config. Nothing is ever removed by matching a deny-list,
 * because a deny-list only catches what someone thought to deny — and the three things we
 * most need caught are the ones nobody will think of: a key injected by an attacker, a key
 * typo'd by a future edit, and a RETIRED key posted by a browser tab that was opened before
 * the deploy. All three are dropped by the same array_intersect_key, and no one had to
 * anticipate any of them.
 *
 * That property is why the Fair Housing retirement of `tenant_type_preference` is enforced
 * here rather than only by deleting the form control: a stale tab posting the old field is
 * a real state during any deploy, and the value it posts is the one we are removing.
 *
 *
 * ── PROPERTY TYPE, AND WHY THE STORED VALUE WINS ────────────────────────────────────────
 *
 * `preferred_business_use` is commercial-only. `$this->property_type` is itself a public
 * Livewire property and therefore just as client-settable as the blob, so a request that
 * flips the listing commercial and adds the commercial key in one message would pass a
 * naive check that trusted the submitted value.
 *
 * So callers pass the property type that will actually be persisted, and on Edit they pass
 * the STORED one — see propertyTypeForProjection(). Anything that is not exactly the
 * configured commercial value is treated as residential: null, '', a legacy spelling, an
 * unrecognised string. property_type lives in EAV meta and can be absent on an older row,
 * so the permissive reading would be a hole rather than a kindness.
 *
 * @see config/hire_agent_compatibility_keys.php
 */
class CompatibilityPreferencePolicy
{
    /**
     * The config entry that holds landlord's commercial-only key list.
     *
     * Reserved: it is a key list living beside the four real roles, and must never resolve as one.
     */
    private const COMMERCIAL_ONLY_ROLE_KEY = 'landlord_commercial_only';

    /**
     * Project one role sub-array down to the keys that role and property type may persist.
     *
     * Unknown keys are discarded. Missing allowed keys are NOT invented — the projection
     * narrows, it never widens, so a caller that passes a partial sub-array gets a partial
     * sub-array back rather than one padded with empty strings that would overwrite stored
     * values with blanks.
     *
     * @param  array<string,mixed>  $sub           The role's sub-array as the component holds it.
     * @param  string               $role          seller|buyer|landlord|tenant.
     * @param  string|null          $propertyType  The property type that will be persisted.
     * @return array<string,mixed>
     */
    public static function project(array $sub, string $role, ?string $propertyType = null): array
    {
        $allowed = self::allowedKeys($role, $propertyType);

        if ($allowed === []) {
            // Unknown role. Persisting an unfiltered blob under an unrecognised role key is
            // the failure this class exists to prevent, so the safe answer is nothing.
            return [];
        }

        return array_intersect_key($sub, array_flip($allowed));
    }

    /**
     * Project a whole multi-role blob, one role at a time.
     *
     * The Edit component writes every role namespace it holds, not just the active one, so
     * it needs this rather than project(). Namespaces whose role is unrecognised are dropped
     * rather than passed through — an unknown `{something}_specific` key in a stored blob is
     * either a bug or an injection, and neither should survive a save.
     *
     * The property type applies only to the landlord namespace; no other role is scoped by it.
     *
     * @param  array<string,array<string,mixed>>  $blob
     * @return array<string,array<string,mixed>>
     */
    public static function projectAll(array $blob, ?string $propertyType = null): array
    {
        $out = [];

        foreach ($blob as $namespace => $sub) {
            if (!is_string($namespace) || !is_array($sub)) {
                continue;
            }

            if (!str_ends_with($namespace, '_specific')) {
                continue;
            }

            $role = substr($namespace, 0, -strlen('_specific'));

            if (self::allowedKeys($role, $propertyType) === []) {
                continue;
            }

            $out[$namespace] = self::project($sub, $role, $propertyType);
        }

        return $out;
    }

    /**
     * The keys this role may persist, given the property type.
     *
     * Returns [] for an unrecognised role, which project() reads as "persist nothing".
     *
     * @return list<string>
     */
    public static function allowedKeys(string $role, ?string $propertyType = null): array
    {
        $role  = strtolower(trim($role));
        $roles = (array) config('hire_agent_compatibility_keys.roles', []);

        // `landlord_commercial_only` is a key list, not a role, and it lives in the same config
        // array as the four real roles. Left addressable it would hand a caller the commercial
        // keys with NO property-type test — the one thing this class exists to enforce — for any
        // request that named it as its role. It is reserved here rather than moved out of the
        // array because keeping the landlord's two key sets adjacent is what makes the config
        // readable, and this is the cost of that.
        if ($role === self::COMMERCIAL_ONLY_ROLE_KEY) {
            return [];
        }

        $base = $roles[$role] ?? null;

        if (!is_array($base) || $base === []) {
            return [];
        }

        $keys = array_values(array_filter($base, 'is_string'));

        if ($role === 'landlord' && self::isCommercial($propertyType)) {
            $commercial = $roles[self::COMMERCIAL_ONLY_ROLE_KEY] ?? [];
            if (is_array($commercial)) {
                $keys = array_merge($keys, array_values(array_filter($commercial, 'is_string')));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Is this the commercial property type, exactly?
     *
     * Exact match on the configured value, deliberately. A ->contains('Commercial') style
     * test would admit a value we have never seen and cannot reason about, and the cost of
     * being wrong here is a commercial-only key on a residential listing.
     */
    public static function isCommercial(?string $propertyType): bool
    {
        $commercial = (string) config(
            'hire_agent_compatibility_keys.commercial_property_type',
            'Commercial Property'
        );

        return is_string($propertyType) && trim($propertyType) === $commercial;
    }

    /**
     * Which property type a write should be projected against.
     *
     * On Create the submitted value IS the value about to be persisted, so it is the right
     * one. On Edit the submitted value has not been persisted yet and is client-settable,
     * so the STORED value governs: a single request that flips the listing commercial and
     * writes the commercial key must not be able to authorise itself.
     *
     * The consequence is intended and worth stating: a landlord genuinely converting a
     * listing to commercial saves once to change the type, and answers Preferred Business
     * Use on the next save. One extra save is the correct price for closing a self-
     * authorising write.
     *
     * @param  string|null  $storedPropertyType    From the listing, or null on Create.
     * @param  string|null  $submittedPropertyType From component state.
     */
    public static function propertyTypeForProjection(
        ?string $storedPropertyType,
        ?string $submittedPropertyType
    ): ?string {
        $stored = is_string($storedPropertyType) ? trim($storedPropertyType) : '';

        return $stored !== '' ? $stored : $submittedPropertyType;
    }
}
