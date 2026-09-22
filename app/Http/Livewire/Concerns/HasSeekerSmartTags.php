<?php

namespace App\Http\Livewire\Concerns;

use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Property Features You Want" — the Buyer/Tenant Offer Listing Smart Tag
 * picker, as a Create/Edit wizard concern.
 *
 * THE SAME SEEKER-PREFERENCE SYSTEM THE CRITERIA FORMS USE, NOT A SECOND ONE.
 * Selections live in `smart_tag_seeker_preferences`, written ONLY by
 * {@see SmartTagSeekerPreferenceWriter} and read ONLY by
 * {@see SmartTagSeekerPreferenceReader}; the options come from the canonical
 * taxonomy on SURFACE_SEEKER through the reader; the gate is
 * SMART_TAGS_SEEKER_PREFERENCES_ENABLED through
 * {@see SmartTagSeekerPreferenceGate}. This trait holds no table name, no key
 * list and no policy of its own. What it adds is the Livewire shape: a public
 * property the checkboxes bind to, the context read from the FORM's property
 * type so the options change in the same request as the select, and the calls
 * at the points where the wizard persists a row.
 *
 * FOUR COMPONENTS, ONE IMPLEMENTATION — Buyer Create/Edit and Tenant
 * Create/Edit each declare {@see seekerSmartTagSubjectType()} and make four
 * one-line calls, the pattern HasOwnerSmartTags established for Seller/Landlord.
 *
 * WRITTEN WITH EVERY SAVED ROW, DRAFTS INCLUDED. Unlike listing-side evidence,
 * which is derived only at publish, a seeker's picks are an ANSWER on the form
 * like any other. `SAVE_AS_NEW_DRAFT` makes each draft save a new row carrying a
 * full snapshot of the answers, so the row's picks are written against that
 * row's id; deleting a draft purges them through BelongsToListingWorkflow. The
 * table is the one store — no meta copy.
 *
 * NOTHING HERE CAN FAIL A SAVE, AND OFF MEANS UNTOUCHED. With the gate closed the
 * picker does not render and no write is attempted, so rows stored while it was
 * on survive an edit made while it is off. Any fault in the write is logged and
 * swallowed: an Offer Listing must never fail to save because a preference could
 * not be.
 */
trait HasSeekerSmartTags
{
    /**
     * Canonical Smart Tag keys the seeker has ticked.
     *
     * A public Livewire property, so a client can set it to anything — which is
     * why SmartTagSelectionPolicy runs inside the writer, against the STORED
     * property type, and not in `rules()`.
     *
     * @var array<int, mixed>
     */
    public $seeker_smart_tags = [];

    /** Which seeker subject this wizard writes. */
    abstract protected function seekerSmartTagSubjectType(): SmartTagSeekerSubjectType;

    /**
     * Is this wizard currently describing the seeker it was built for?
     *
     * The Tenant wizards can save a buyer, seller or landlord row depending on
     * `user_type`, and the Buyer create view includes other roles' tabs; a seeker
     * picker belongs only on the role's own flow.
     */
    protected function seekerSmartTagRoleMatches(): bool
    {
        return (string) ($this->user_type ?? '') === $this->seekerSmartTagSubjectType()->role()->value;
    }

    /**
     * What the picker renders, or null when it must not render at all.
     *
     * @return array{
     *   context: ?string,
     *   groups: array<string, array{label: string, options: list<array{key: string, label: string, description: string, selected: bool}>}>,
     *   optionCount: int,
     *   selectedCount: int
     * }|null
     */
    public function seekerSmartTagPanel(): ?array
    {
        if (! SmartTagSeekerPreferenceGate::enabled() || ! $this->seekerSmartTagRoleMatches()) {
            return null;
        }

        $context = SmartTagContextResolver::forSeekerSubject(
            $this->seekerSmartTagSubjectType(),
            is_string($this->property_type ?? null) ? $this->property_type : null,
        );

        $selected = self::sanitizeSeekerSmartTags((array) $this->seeker_smart_tags);
        $groups = [];
        $optionCount = 0;
        $selectedCount = 0;

        if ($context !== null) {
            $grouped = app(SmartTagSeekerPreferenceReader::class)->selectableGroupedForContext($context);

            foreach ($grouped as $slug => $group) {
                $options = [];
                foreach ($group['tags'] as $key => $definition) {
                    /** @var SmartTagDefinition $definition */
                    $isSelected = in_array($key, $selected, true);
                    $options[] = [
                        'key'         => $key,
                        'label'       => $definition->label,
                        'description' => $definition->description,
                        'selected'    => $isSelected,
                    ];
                    $optionCount++;
                    $selectedCount += $isSelected ? 1 : 0;
                }
                $groups[$slug] = ['label' => $group['label'], 'options' => $options];
            }
        }

        return [
            'context'       => $context?->value,
            'groups'        => $groups,
            'optionCount'   => $optionCount,
            'selectedCount' => $selectedCount,
        ];
    }

    /**
     * The selection as it enters the draft-change hash, or null to leave the
     * hash exactly as it was before this picker existed.
     *
     * Without it a tags-only change reads as "No changes detected" and the
     * draft is never saved. Omitted when empty so an existing draft with no
     * picks keeps its stored hash.
     */
    protected function seekerSmartTagsForDraftPayload(): ?string
    {
        $keys = self::sanitizeSeekerSmartTags((array) $this->seeker_smart_tags);

        if ($keys === []) {
            return null;
        }

        sort($keys);

        return implode(',', $keys);
    }

    /** Restore the seeker's picks when a draft or listing is loaded for editing. */
    protected function restoreSeekerSmartTags($auction): void
    {
        try {
            $this->seeker_smart_tags = app(SmartTagSeekerPreferenceReader::class)->keysFor($auction);
        } catch (\Throwable $e) {
            $this->seeker_smart_tags = [];
            Log::warning('smart_tag_seeker_preferences restore failed', [
                'subject_type' => $this->seekerSmartTagSubjectType()->value,
                'exception'    => $e::class,
            ]);
        }
    }

    /**
     * Persist the picks for a row the wizard has just saved.
     *
     * Call AFTER the row's property_type meta has been written: the writer
     * resolves the context from the STORED type, never from the form, and the
     * model is re-read here so a relation loaded earlier in the request cannot
     * hand it a stale one. A row that is not this wizard's seeker subject — a
     * Hire row, another role's model — is skipped rather than written.
     */
    protected function persistSeekerSmartTags($auction): void
    {
        if (! SmartTagSeekerPreferenceGate::writesEnabled() || $auction === null) {
            return;
        }

        try {
            $stored = $auction->fresh();

            if ($stored === null
                || SmartTagSeekerSubjectType::forModel($stored) !== $this->seekerSmartTagSubjectType()) {
                return;
            }

            app(SmartTagSeekerPreferenceWriter::class)->replaceSelections(
                $stored,
                self::sanitizeSeekerSmartTags((array) $this->seeker_smart_tags),
                Auth::id() !== null ? (int) Auth::id() : null,
            );
        } catch (\Throwable $e) {
            Log::warning('smart_tag_seeker_preferences write failed', [
                'subject_type' => $this->seekerSmartTagSubjectType()->value,
                'exception'    => $e::class,
            ]);
        }
    }

    /**
     * Strings only, de-duplicated. Shape, not meaning — whether a key is
     * canonical, applicable and seeker-selectable is the writer's decision.
     *
     * @param  array<int, mixed> $raw
     * @return list<string>
     */
    private static function sanitizeSeekerSmartTags(array $raw): array
    {
        $out = [];
        foreach ($raw as $value) {
            if (is_string($value) && preg_match('/^[a-z][a-z0-9_]{1,62}$/', $value) && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
