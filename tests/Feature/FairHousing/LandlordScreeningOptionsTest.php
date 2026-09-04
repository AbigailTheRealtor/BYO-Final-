<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Models\User;
use App\Support\OfferListing\LandlordScreeningPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fair Housing Phase 2 — landlord Applicant Requirements screening controls.
 *
 * WHAT CHANGED, AND WHY EACH CHANGE NEEDED A DIFFERENT KIND OF TEST.
 *
 * 1. The employment gate is retired outright. `employment_requirement` asked
 *    landlords to require an employment STATUS — "Employed", "Retired allowed",
 *    "Student allowed" — which conditions tenancy on how income is earned rather
 *    than on whether rent can be paid, and excludes retirees, people on
 *    disability or Social Security, students, and anyone on lawful non-wage
 *    income. `employment_verification_requirement` is the same exclusion once
 *    removed: a retiree cannot produce proof of employment. Nothing replaces
 *    them, because the objective question is already asked by the income fields,
 *    which this file pins as surviving.
 *
 * 2. The blanket lifetime "No criminal background" option is retired, and — this
 *    is the part worth a dedicated test — it is SUPPRESSED rather than mapped
 *    forward. Relabelling it "Individualized review of convictions" would credit
 *    a listing with a process it never had.
 *
 * 3. The standardless "Case-by-case review" / "Compensating factors considered"
 *    options are RENAMED, not removed. Removing flexibility pushes landlords to
 *    hard cutoffs, which is worse for applicants; naming the standard is the fix.
 *
 * WHY THE PERSISTENCE TESTS ARE NOT BLADE GREPS. Every one of these keys is a
 * public Livewire property that `saveMeta()` wrote verbatim, with no validation
 * rule anywhere. Deleting an <option> changes the form and nothing else. So the
 * assertions below run the real boundary — LandlordScreeningPolicy — against the
 * exact historical strings, and separately prove the components route their
 * writes through it rather than calling saveMeta directly.
 */
class LandlordScreeningOptionsTest extends TestCase
{
    use DatabaseTransactions;

    private const PARTIAL = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/applicant-requirements.blade.php';
    private const PUBLIC_VIEW = 'resources/views/offer-listing/landlord/view.blade.php';

    private const RETIRED_EMPLOYMENT_KEYS = [
        'employment_requirement',
        'custom_employment_requirement',
        'employment_verification_requirement',
    ];

    private const RETIRED_EMPLOYMENT_VALUES = [
        'Employed',
        'Self-employed allowed',
        'Retired allowed',
        'Student allowed',
    ];

    private const GOVERNED_KEYS = [
        'criminal_background_requirement',
        'eviction_history_requirement',
        'bankruptcy_requirement',
        'credit_score_flexibility',
        'pet_policy_requirement',
        'income_verification_requirement',
    ];

    private function partial(): string
    {
        return file_get_contents(base_path(self::PARTIAL));
    }

    private function publicView(): string
    {
        return file_get_contents(base_path(self::PUBLIC_VIEW));
    }

    private function source(string $component): string
    {
        return file_get_contents((new ReflectionClass($component))->getFileName());
    }

    // =====================================================================
    // Non-vacuousness — the assertions below have something to bite on
    // =====================================================================

    /**
     * @test
     *
     * Every "absent" assertion in this file is only meaningful if the thing it
     * searches is populated and the resolver it calls actually discriminates. A
     * config that failed to load would make each assertNotContains trivially
     * true and the suite would go green while the boundary did nothing.
     */
    public function the_screening_allowlist_is_loaded_and_discriminates(): void
    {
        foreach (self::GOVERNED_KEYS as $key) {
            $this->assertNotEmpty(
                LandlordScreeningPolicy::optionsFor($key),
                "Option list for {$key} is empty — every absence assertion for it would be vacuous."
            );
        }

        // The same call that must reject a retired value must accept a current
        // one. If normalize() returned '' unconditionally the rejection tests
        // would pass for the wrong reason.
        $this->assertSame(
            'Individualized review of convictions',
            LandlordScreeningPolicy::normalize('criminal_background_requirement', 'Individualized review of convictions')
        );
        $this->assertSame(
            '',
            LandlordScreeningPolicy::normalize('criminal_background_requirement', 'No criminal background')
        );

        // And the retired-field list is real, not an empty array.
        $this->assertNotEmpty(LandlordScreeningPolicy::retiredFields());
        foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
            $this->assertTrue(LandlordScreeningPolicy::isRetiredField($key), "{$key} is not registered as retired.");
        }
    }

    // =====================================================================
    // The employment gate is gone from both wizards
    // =====================================================================

    /** @test */
    public function the_employment_requirement_control_is_absent_from_the_shared_create_and_edit_partial(): void
    {
        $partial = $this->partial();

        // One partial serves both wizards, so this is Create and Edit at once —
        // asserted independently below via the include.
        $this->assertStringNotContainsString('wire:model="employment_requirement"', $partial);
        $this->assertStringNotContainsString('wire:model="custom_employment_requirement"', $partial);
        $this->assertStringNotContainsString('wire:model="employment_verification_requirement"', $partial);

        foreach (self::RETIRED_EMPLOYMENT_VALUES as $value) {
            $this->assertStringNotContainsString(
                '<option value="' . $value . '"',
                $partial,
                "Retired employment option '{$value}' is still offered."
            );
        }

        $this->assertStringNotContainsString('Employment verification requirement', $partial);
    }

    /** @test */
    public function both_the_create_and_the_edit_wizard_render_that_same_partial(): void
    {
        $include = "@include('livewire.offer-listing.offer-landlord-tabs.commission-based.applicant-requirements')";

        foreach ([
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        ] as $wizard) {
            $this->assertStringContainsString(
                $include,
                file_get_contents(base_path($wizard)),
                "{$wizard} no longer renders the shared Applicant Requirements partial, so Create/Edit parity is no longer structural."
            );
        }
    }

    /** @test */
    public function neither_component_declares_hydrates_or_persists_the_retired_employment_keys(): void
    {
        foreach ([LandlordOfferListing::class, LandlordOfferListingEdit::class] as $component) {
            $source = $this->source($component);

            foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
                $this->assertStringNotContainsString("public \${$key}", $source, "{$component} still declares \${$key}.");
                $this->assertStringNotContainsString("get->{$key}", $source, "{$component} still hydrates {$key}.");
                $this->assertStringNotContainsString("saveMeta('{$key}'", $source, "{$component} still persists {$key}.");
            }
        }
    }

    /** @test */
    public function a_crafted_payload_cannot_set_a_retired_employment_value_on_create(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'employment_requirement');
    }

    /** @test */
    public function a_crafted_payload_cannot_set_a_retired_employment_value_on_edit(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'employment_requirement');
    }

    /** @test */
    public function a_crafted_payload_cannot_set_the_retired_employment_free_text(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'custom_employment_requirement');
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'custom_employment_requirement');
    }

    /** @test */
    public function a_crafted_payload_cannot_set_the_retired_employment_verification_field(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'employment_verification_requirement');
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'employment_verification_requirement');
    }

    /**
     * @test
     *
     * Belt and braces: even if a property were reintroduced, the projection is
     * the thing that decides what is written, and it emits no employment key.
     */
    public function the_write_projection_emits_no_employment_key_even_when_one_is_supplied(): void
    {
        $projected = LandlordScreeningPolicy::project([
            'employment_requirement'              => 'Employed',
            'custom_employment_requirement'       => 'Must be W-2 employed',
            'employment_verification_requirement' => 'Required',
        ]);

        foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $projected, "The projection would still write {$key}.");
        }
    }

    // =====================================================================
    // The income machinery that replaced it still works
    // =====================================================================

    /** @test */
    public function the_objective_income_fields_are_preserved(): void
    {
        $partial = $this->partial();

        foreach ([
            'income_qualification_method',
            'min_monthly_income_fixed',
            'min_income_requirement',
            'income_verification_requirement',
            'min_credit_score',
            'custom_credit_score_requirement',
        ] as $key) {
            $this->assertStringContainsString(
                'wire:model="' . $key . '"',
                $partial,
                "Legitimate income field {$key} was lost."
            );
        }

        // The income multiplier options are untouched.
        foreach (['2x Rent', '2.5x Rent', '3x Rent', 'Fixed Monthly Income'] as $option) {
            $this->assertStringContainsString('<option value="' . $option . '"', $partial);
        }
    }

    /** @test */
    public function income_documentation_keeps_its_options_and_gains_source_neutral_wording(): void
    {
        $this->assertSame(
            ['No requirement', 'Required', 'Preferred'],
            LandlordScreeningPolicy::optionsFor('income_verification_requirement')
        );

        foreach (['No requirement', 'Required', 'Preferred'] as $value) {
            $this->assertSame(
                $value,
                LandlordScreeningPolicy::normalize('income_verification_requirement', $value)
            );
        }

        $this->assertSame('Income documentation required', LandlordScreeningPolicy::copy('income_documentation_label'));
        $this->assertStringContainsString(
            'documents showing their income meets the requirement',
            LandlordScreeningPolicy::copy('income_documentation_tooltip')
        );
    }

    /** @test */
    public function the_form_states_that_all_lawful_income_counts(): void
    {
        $helper = LandlordScreeningPolicy::copy('income_sources_helper');

        $this->assertStringContainsString('All lawful, verifiable income counts', $helper);
        foreach (['self-employment', 'retirement or benefit income', 'housing assistance'] as $fragment) {
            $this->assertStringContainsString($fragment, $helper);
        }

        // It is fixed copy rendered from the SSOT, not a control a listing can narrow.
        $this->assertStringContainsString("LandlordScreeningPolicy::copy('income_sources_helper')", $this->partial());
    }

    // =====================================================================
    // Criminal history
    // =====================================================================

    /** @test */
    public function the_blanket_lifetime_criminal_ban_is_gone_and_the_narrow_set_is_offered(): void
    {
        $this->assertSame(
            ['No requirement', 'Individualized review of convictions', 'Other'],
            LandlordScreeningPolicy::optionsFor('criminal_background_requirement')
        );

        $this->assertStringNotContainsString('<option value="No criminal background"', $this->partial());

        // No fixed lookback window is shipped: a 3/5/7-year list reads as a legal
        // standard, and the applicable limits are state and local.
        foreach (LandlordScreeningPolicy::optionsFor('criminal_background_requirement') as $option) {
            $this->assertDoesNotMatchRegularExpression('/\d+\s*year/i', $option);
        }
    }

    /** @test */
    public function a_crafted_blanket_criminal_value_cannot_be_written(): void
    {
        $projected = LandlordScreeningPolicy::project([
            'criminal_background_requirement' => 'No criminal background',
        ]);

        $this->assertSame('', $projected['criminal_background_requirement']);
    }

    /** @test */
    public function an_arbitrary_crafted_criminal_value_cannot_be_written(): void
    {
        $projected = LandlordScreeningPolicy::project([
            'criminal_background_requirement' => 'No felonies ever, no exceptions',
        ]);

        $this->assertSame('', $projected['criminal_background_requirement']);
    }

    /** @test */
    public function the_current_criminal_option_and_its_other_text_persist(): void
    {
        $projected = LandlordScreeningPolicy::project([
            'criminal_background_requirement' => 'Individualized review of convictions',
        ]);
        $this->assertSame('Individualized review of convictions', $projected['criminal_background_requirement']);

        $withOther = LandlordScreeningPolicy::project([
            'criminal_background_requirement'        => 'Other',
            'custom_criminal_background_requirement' => 'Convictions reviewed against the offence and its recency.',
        ]);
        $this->assertSame('Other', $withOther['criminal_background_requirement']);
        $this->assertSame(
            'Convictions reviewed against the offence and its recency.',
            $withOther['custom_criminal_background_requirement']
        );
    }

    /** @test */
    public function other_free_text_is_unreachable_when_the_parent_field_is_not_other(): void
    {
        // A crafted request can set the text without ever selecting "Other".
        $projected = LandlordScreeningPolicy::project([
            'criminal_background_requirement'        => 'No requirement',
            'custom_criminal_background_requirement' => 'No felons.',
        ]);

        $this->assertSame('', $projected['custom_criminal_background_requirement']);
    }

    /** @test */
    public function the_surviving_screening_free_text_is_length_bounded(): void
    {
        $max = LandlordScreeningPolicy::customTextMaxLength();
        $this->assertGreaterThan(0, $max);

        $projected = LandlordScreeningPolicy::project([
            'criminal_background_requirement'        => 'Other',
            'custom_criminal_background_requirement' => str_repeat('a', $max + 500),
        ]);

        $this->assertSame($max, mb_strlen($projected['custom_criminal_background_requirement']));
    }

    // =====================================================================
    // Standardless discretion is renamed, not removed
    // =====================================================================

    /** @test */
    public function case_by_case_review_is_gone_from_every_affected_field(): void
    {
        foreach (['eviction_history_requirement', 'bankruptcy_requirement', 'credit_score_flexibility', 'pet_policy_requirement'] as $key) {
            $this->assertNotContains(
                'Case-by-case review',
                LandlordScreeningPolicy::optionsFor($key),
                "{$key} still offers standardless 'Case-by-case review'."
            );
            $this->assertContains(
                'Exceptions considered under documented criteria',
                LandlordScreeningPolicy::optionsFor($key),
                "{$key} lost its flexibility option entirely — that pushes landlords to hard cutoffs."
            );
        }

        $this->assertStringNotContainsString('<option value="Case-by-case review"', $this->partial());
    }

    /** @test */
    public function compensating_factors_is_retired_and_a_concrete_remedy_replaces_it(): void
    {
        $options = LandlordScreeningPolicy::optionsFor('credit_score_flexibility');

        $this->assertNotContains('Compensating factors considered', $options);
        $this->assertContains('Additional deposit or qualified co-signer may be considered', $options);
        $this->assertStringNotContainsString('<option value="Compensating factors considered"', $this->partial());
    }

    /** @test */
    public function the_replacement_wording_actually_persists(): void
    {
        $projected = LandlordScreeningPolicy::project([
            'eviction_history_requirement' => 'Exceptions considered under documented criteria',
            'bankruptcy_requirement'       => 'Exceptions considered under documented criteria',
            'credit_score_flexibility'     => 'Additional deposit or qualified co-signer may be considered',
            'pet_policy_requirement'       => ['Dogs allowed', 'Exceptions considered under documented criteria'],
        ]);

        $this->assertSame('Exceptions considered under documented criteria', $projected['eviction_history_requirement']);
        $this->assertSame('Exceptions considered under documented criteria', $projected['bankruptcy_requirement']);
        $this->assertSame('Additional deposit or qualified co-signer may be considered', $projected['credit_score_flexibility']);
        $this->assertSame(
            ['Dogs allowed', 'Exceptions considered under documented criteria'],
            json_decode($projected['pet_policy_requirement'], true)
        );
    }

    /** @test */
    public function phase_one_assistance_animal_handling_is_untouched(): void
    {
        // Assistance animals are not a pet policy. Phase 2 changed exactly one
        // option string in this field and must not have introduced any
        // assistance-animal vocabulary of its own.
        foreach (LandlordScreeningPolicy::optionsFor('pet_policy_requirement') as $option) {
            $this->assertDoesNotMatchRegularExpression('/assistance|service animal|emotional support/i', $option);
        }
    }

    // =====================================================================
    // Stale historical values: suppressed vs normalized
    // =====================================================================

    /** @test */
    public function a_stale_case_by_case_value_is_normalized_forward(): void
    {
        // Wording-only rename; the meaning survives, so the landlord's answer is
        // carried rather than dropped.
        $this->assertSame(
            'Exceptions considered under documented criteria',
            LandlordScreeningPolicy::normalize('eviction_history_requirement', 'Case-by-case review')
        );
        $this->assertSame(
            'Exceptions considered under documented criteria',
            LandlordScreeningPolicy::normalize('bankruptcy_requirement', 'Case-by-case review')
        );
        $this->assertSame(
            'Individualized review of convictions',
            LandlordScreeningPolicy::normalize('criminal_background_requirement', 'Case-by-case review')
        );
    }

    /** @test */
    public function a_stale_compensating_factors_value_is_not_recast_as_a_deposit_offer(): void
    {
        // The landlord may have meant reserves or rental history. Asserting a
        // deposit / co-signer remedy they never chose would misstate the policy,
        // so it normalizes to the generic documented-criteria wording instead.
        $this->assertSame(
            'Exceptions considered under documented criteria',
            LandlordScreeningPolicy::normalize('credit_score_flexibility', 'Compensating factors considered')
        );
    }

    /** @test */
    public function a_stale_blanket_criminal_value_is_suppressed_and_never_relabelled(): void
    {
        $this->assertSame(
            '',
            LandlordScreeningPolicy::normalize('criminal_background_requirement', 'No criminal background')
        );
        $this->assertNull(
            LandlordScreeningPolicy::displayValue('criminal_background_requirement', 'No criminal background'),
            'A blanket lifetime ban must render nothing, not an individualized-review claim.'
        );
    }

    /** @test */
    public function a_stale_employment_value_can_never_be_displayed(): void
    {
        foreach (self::RETIRED_EMPLOYMENT_VALUES as $value) {
            $this->assertNull(LandlordScreeningPolicy::displayValue('employment_requirement', $value));
        }

        // All three surfaces that publish the landlord's screening policy. The
        // review page is here because Phase 2's first pass missed it: it read the
        // retired keys AND scored applicants against them, while every string
        // assertion in this file stayed green.
        foreach ([
            self::PUBLIC_VIEW,
            'resources/views/offer-listing/landlord/qualification/check.blade.php',
            'resources/views/offer-listing/landlord/qualification/review.blade.php',
        ] as $surface) {
            $source = file_get_contents(base_path($surface));

            foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
                $this->assertStringNotContainsString(
                    "\$str('{$key}')",
                    $source,
                    "{$surface} still reads the retired key {$key}."
                );
            }
        }

        $view = $this->publicView();
        $this->assertStringNotContainsString("'Employment Requirement'", $view);
        $this->assertStringNotContainsString("'Employment Verification'", $view);
    }

    /**
     * @test
     *
     * Every surface that publishes a governed value resolves it through the
     * boundary. A raw read is how a suppressed value gets republished on a page
     * nobody re-checked.
     */
    public function every_screening_surface_resolves_governed_values_through_the_policy(): void
    {
        foreach ([
            self::PUBLIC_VIEW,
            'resources/views/offer-listing/landlord/qualification/check.blade.php',
            'resources/views/offer-listing/landlord/qualification/review.blade.php',
        ] as $surface) {
            $source = file_get_contents(base_path($surface));

            $this->assertStringContainsString(
                'LandlordScreeningPolicy',
                $source,
                "{$surface} publishes screening values without the boundary."
            );

            // A governed key may appear only as an argument to the resolver, never
            // as a bare read assigned straight into a display variable.
            foreach (self::GOVERNED_KEYS as $key) {
                $this->assertDoesNotMatchRegularExpression(
                    '/=\s*\$str\(\s*\'' . preg_quote($key, '/') . '\'\s*\)/',
                    $source,
                    "{$surface} assigns {$key} directly from \$str(), bypassing LandlordScreeningPolicy."
                );
            }
        }
    }

    /** @test */
    public function the_no_requirement_sentinel_is_resolved_in_one_place(): void
    {
        // Components shipped 'No Requirement' as a default for years; it matched
        // no option, and the public view compared against two spellings in two
        // places. Both spellings must now land on the same answer.
        foreach (['eviction_history_requirement', 'bankruptcy_requirement'] as $key) {
            $this->assertSame('No requirement', LandlordScreeningPolicy::normalize($key, 'No Requirement'));
            $this->assertSame('No requirement', LandlordScreeningPolicy::normalize($key, 'No requirement'));
            $this->assertNull(LandlordScreeningPolicy::displayValue($key, 'No Requirement'));
            $this->assertNull(LandlordScreeningPolicy::displayValue($key, 'No requirement'));
        }
    }

    /** @test */
    public function a_listing_holding_only_stale_retired_values_does_not_open_the_screening_section(): void
    {
        $stored = [
            'employment_requirement'          => 'Employed',
            'criminal_background_requirement' => 'No criminal background',
            'eviction_history_requirement'    => 'No Requirement',
            'bankruptcy_requirement'          => '',
            'credit_score_flexibility'        => '',
            'pet_policy_requirement'          => '[]',
            'income_verification_requirement' => 'No requirement',
        ];

        $this->assertFalse(
            LandlordScreeningPolicy::hasAnyDisplayableValue(
                fn (string $key): string => $stored[$key] ?? ''
            ),
            'A listing whose only screening answers are retired or empty must not open the section.'
        );
    }

    /** @test */
    public function one_current_answer_does_open_the_screening_section(): void
    {
        $stored = ['criminal_background_requirement' => 'Individualized review of convictions'];

        $this->assertTrue(
            LandlordScreeningPolicy::hasAnyDisplayableValue(
                fn (string $key): string => $stored[$key] ?? ''
            )
        );
    }

    // =====================================================================
    // Writes go through the boundary, on both wizards
    // =====================================================================

    /** @test */
    public function both_components_route_every_governed_write_through_the_policy(): void
    {
        foreach ([LandlordOfferListing::class, LandlordOfferListingEdit::class] as $component) {
            $source = $this->source($component);

            $this->assertStringContainsString(
                'LandlordScreeningPolicy::project(',
                $source,
                "{$component} does not project its screening writes."
            );

            foreach (self::GOVERNED_KEYS as $key) {
                $this->assertStringNotContainsString(
                    "saveMeta('{$key}', \$this->",
                    $source,
                    "{$component} still writes {$key} directly, bypassing the policy."
                );
            }
        }
    }

    /** @test */
    public function create_and_edit_expose_and_enforce_identical_options(): void
    {
        // Parity is structural on the render side (one partial) and on the write
        // side (one policy). This pins the second half: both components hydrate
        // and project through the same calls.
        $create = $this->source(LandlordOfferListing::class);
        $edit   = $this->source(LandlordOfferListingEdit::class);

        foreach (self::GOVERNED_KEYS as $key) {
            $needle = "'{$key}'";
            $this->assertSame(
                substr_count($create, $needle) > 0,
                substr_count($edit, $needle) > 0,
                "Create and Edit disagree on whether they handle {$key}."
            );
        }

        foreach (['normalizeMulti(', 'normalizeCustomText(', 'LandlordScreeningPolicy::project('] as $call) {
            $this->assertStringContainsString($call, $create, "Create is missing {$call}");
            $this->assertStringContainsString($call, $edit, "Edit is missing {$call}");
        }
    }

    /** @test */
    public function the_option_lists_have_exactly_one_enforcing_reader_and_one_rendering_reader(): void
    {
        // The config is the SSOT precisely because the form and the gate read it
        // together. If a third reader appears, or the Blade stops reading it, the
        // two can drift and the gate quietly stops matching the product.
        $this->assertStringContainsString('landlord_screening_options', file_get_contents(
            base_path('app/Support/OfferListing/LandlordScreeningPolicy.php')
        ));
        $this->assertStringContainsString('LandlordScreeningPolicy::optionsFor(', $this->partial());
    }

    // =====================================================================
    // Ask AI and matching
    // =====================================================================

    /** @test */
    public function no_ask_ai_surface_can_read_a_retired_employment_value(): void
    {
        foreach ([
            'app/Services/AskAi/AskAiContextBuilderService.php',
            'app/Services/AskAi/AskAiFieldQuestionRegistryService.php',
            'app/Services/AskAi/AskAiRunnerV2Service.php',
        ] as $service) {
            $source = file_get_contents(base_path($service));

            foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
                $this->assertStringNotContainsString(
                    "'{$key}'",
                    $source,
                    "{$service} still names {$key}; a stale stored value could reach prompt context."
                );
            }
        }
    }

    /** @test */
    public function ask_ai_reads_criminal_history_through_the_same_suppression(): void
    {
        $source = file_get_contents(base_path('app/Services/AskAi/AskAiContextBuilderService.php'));

        $this->assertStringContainsString(
            "LandlordScreeningPolicy::displayValue(\n                                                      'criminal_background_requirement'",
            $source,
            'Ask AI must resolve criminal history through the policy, or a stale blanket ban reaches the model.'
        );
    }

    /** @test */
    public function phase_two_changes_nothing_about_matching_or_scoring(): void
    {
        $scoring = file_get_contents(base_path('config/match_scoring.php'));

        foreach (array_merge(self::GOVERNED_KEYS, self::RETIRED_EMPLOYMENT_KEYS) as $key) {
            $this->assertStringNotContainsString($key, $scoring);
        }

        foreach (glob(base_path('app/Helpers/*BidMatchScoreHelper.php')) as $helper) {
            $source = file_get_contents($helper);
            foreach (self::RETIRED_EMPLOYMENT_KEYS as $key) {
                $this->assertStringNotContainsString($key, $source, basename($helper) . " reads {$key}.");
            }
        }
    }

    /**
     * @test
     *
     * REGRESSION. The policy is called from the Ask AI landlord extractor, which
     * is covered by a unit test extending PHPUnit's TestCase directly — no
     * application, no config repository. A `config()` call there raises,
     * AskAiContextBuilderService::buildForListing() catches every Throwable, and
     * the listing context comes back EMPTY: not "screening key missing" but every
     * unrelated key gone, with the real error swallowed frames away.
     *
     * This asserts the resolver answers from the file when no container is bound,
     * because the failure mode is silent and nowhere near the change.
     */
    public function the_policy_answers_without_a_booted_container(): void
    {
        $source = file_get_contents(base_path('app/Support/OfferListing/LandlordScreeningPolicy.php'));

        // No unguarded config() call may remain on a code path.
        $this->assertStringNotContainsString(
            "config('landlord_screening_options.",
            $source,
            'The policy reads config() directly again; it will raise wherever no application is booted.'
        );
        $this->assertStringContainsString('private static function conf(): array', $source);

        // Prove it in a separate process with the framework autoloaded but no app.
        $script = <<<'PHPSCRIPT'
            require %s;
            $p = App\Support\OfferListing\LandlordScreeningPolicy::class;
            echo json_encode([
                'options' => $p::optionsFor('criminal_background_requirement'),
                'blanket' => $p::displayValue('criminal_background_requirement', 'No criminal background'),
                'current' => $p::displayValue('criminal_background_requirement', 'Individualized review of convictions'),
            ]);
            PHPSCRIPT;

        $file = tempnam(sys_get_temp_dir(), 'screening') . '.php';
        file_put_contents($file, "<?php\n" . sprintf($script, var_export(base_path('vendor/autoload.php'), true)));

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded, "Policy failed outside a booted application. Output was: {$output}");
        $this->assertContains('Individualized review of convictions', $decoded['options']);
        $this->assertNull($decoded['blanket'], 'The blanket ban leaked when no application was booted.');
        $this->assertSame('Individualized review of convictions', $decoded['current']);
    }

    // =====================================================================

    private function assertCraftedSetIsRejected(string $component, string $property): void
    {
        $user = User::factory()->create();

        try {
            Livewire::actingAs($user)->test($component)->set($property, 'Employed');
        } catch (PublicPropertyNotFoundException $e) {
            $this->assertStringContainsString($property, $e->getMessage());
            return;
        }

        $this->fail("{$component} accepted a crafted value for \${$property}.");
    }
}
