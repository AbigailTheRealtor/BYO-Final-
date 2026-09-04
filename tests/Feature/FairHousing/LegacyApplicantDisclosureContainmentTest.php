<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Support\OfferListing\LandlordScreeningPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fair Housing Phase 2 — the legacy applicant self-disclosure fields stay on
 * their own side of the fence.
 *
 * TWO DIFFERENT THINGS SHARE A VOCABULARY, AND THAT IS THE HAZARD.
 *
 *   `criminal_background`             — what an APPLICANT discloses about
 *                                       themselves, on the legacy rental
 *                                       qualification check and offer-terms
 *                                       forms. Options read "Criminal
 *                                       background disclosed", "Prefer to
 *                                       discuss".
 *   `criminal_background_requirement` — what a LANDLORD requires of applicants,
 *                                       on the Applicant Requirements tab.
 *                                       Phase 2 rewrote this one.
 *
 * They are different fields on different tables written by different code, and
 * the option string "No criminal background" appears in both. Phase 2 changed
 * only the landlord policy field; the legacy applicant forms are deliberately
 * untouched here and belong to the deferred legacy work.
 *
 * WHAT THIS FILE IS FOR. Because the names are one word apart, a future edit
 * could plausibly alias one onto the other — a shared partial, a copied
 * allowlist, a `saveMeta($key)` loop fed from a request. That would let an
 * applicant's disclosure land in the landlord's published screening policy, or
 * let the retired blanket ban re-enter through a door Phase 2 never inspected.
 * These tests fail if such a path is ever introduced.
 *
 * The audit found no crossing path. This file pins that finding rather than
 * asserting a change.
 */
class LegacyApplicantDisclosureContainmentTest extends TestCase
{
    use DatabaseTransactions;

    /** The applicant-side field names, from the two legacy forms. */
    private const APPLICANT_DISCLOSURE_FIELDS = [
        'criminal_background',
        'criminal_background_other',
        'eviction_history',
        'bankruptcy_history',
        'employment_status',
        'employment_status_other',
        'income_source',
        'employment_verification_available',
        'income_verification_available',
    ];

    /** The landlord-side policy keys the screening boundary owns. */
    private const LANDLORD_POLICY_KEYS = [
        'criminal_background_requirement',
        'custom_criminal_background_requirement',
        'eviction_history_requirement',
        'custom_eviction_requirement',
        'bankruptcy_requirement',
        'custom_bankruptcy_requirement',
        'credit_score_flexibility',
        'pet_policy_requirement',
        'income_verification_requirement',
    ];

    private const LEGACY_FORMS = [
        'resources/views/offer-listing/landlord/qualification/check.blade.php',
        'resources/views/offers/_offer_terms_form.blade.php',
    ];

    // =====================================================================
    // The two vocabularies do not meet
    // =====================================================================

    /**
     * @test
     *
     * The rental qualification page DOES read the landlord's screening policy —
     * legitimately. It exists to show an applicant what a landlord requires so
     * they can self-assess before applying. That is the safe direction: landlord
     * policy out to the applicant, never applicant disclosure into the policy.
     *
     * Because it is a second publication of the same values — on a route with no
     * auth middleware — it has to suppress retired values exactly as the listing
     * page does. Reading them raw is how a retired requirement stays published
     * after the field that set it is gone.
     */
    public function the_qualification_page_publishes_landlord_policy_only_through_the_boundary(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/landlord/qualification/check.blade.php'));

        // Retired keys are not read here at all.
        foreach (['employment_requirement', 'custom_employment_requirement', 'employment_verification_requirement'] as $retired) {
            $this->assertStringNotContainsString(
                "\$str('{$retired}')",
                $blade,
                "The qualification page still reads the retired key {$retired}, so a stale value stays published."
            );
        }

        // Every surviving policy value is resolved by the boundary, not inline.
        foreach ([
            'criminal_background_requirement',
            'eviction_history_requirement',
            'bankruptcy_requirement',
            'credit_score_flexibility',
            'pet_policy_requirement',
            'income_verification_requirement',
        ] as $key) {
            $this->assertMatchesRegularExpression(
                '/LandlordScreeningPolicy::displayValue\(\s*\n?\s*\'' . preg_quote($key, '/') . '\'/',
                $blade,
                "The qualification page reads {$key} without going through LandlordScreeningPolicy::displayValue()."
            );
        }

        // And it publishes no blanket ban of its own.
        $this->assertStringNotContainsString('rqc-req-value">{{ $criminalReq', $blade);
    }

    /** @test */
    public function the_offer_terms_form_names_no_landlord_screening_policy_key(): void
    {
        $blade = file_get_contents(base_path('resources/views/offers/_offer_terms_form.blade.php'));

        foreach (self::LANDLORD_POLICY_KEYS as $key) {
            $this->assertStringNotContainsString(
                $key,
                $blade,
                "The offer terms form names the landlord policy key {$key}; an applicant disclosure could reach the landlord's published screening policy."
            );
        }
    }

    /**
     * @test
     *
     * The review page is the one place the two vocabularies genuinely meet: it puts
     * the landlord's policy and the applicant's own disclosure side by side. That is
     * the safe direction, but only while it stays a comparison — it must read the
     * policy through the boundary and write nothing at all.
     */
    public function the_review_page_compares_the_two_vocabularies_without_ever_writing(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/landlord/qualification/review.blade.php'));

        // No write path of any kind.
        foreach (['saveMeta(', '->save()', '::create(', '->update(', '<form'] as $writePattern) {
            $this->assertStringNotContainsString(
                $writePattern,
                $blade,
                "The review page gained a write path ({$writePattern}); an applicant disclosure could reach the landlord's policy."
            );
        }

        // Landlord policy is resolved, not read raw.
        $this->assertStringContainsString('LandlordScreeningPolicy', $blade);

        // And the applicant's disclosure is never used to populate a policy key.
        foreach (self::LANDLORD_POLICY_KEYS as $key) {
            $this->assertStringNotContainsString('name="' . $key . '"', $blade);
        }
    }

    /** @test */
    public function no_legacy_form_writes_a_landlord_screening_policy_key(): void
    {
        // The containment property that actually matters: an applicant-side form
        // may READ the policy to display it, but nothing on that side may write,
        // bind, or post into it.
        foreach (self::LEGACY_FORMS as $form) {
            $blade = file_get_contents(base_path($form));

            foreach (self::LANDLORD_POLICY_KEYS as $key) {
                foreach ([
                    'name="' . $key . '"',
                    "wire:model=\"{$key}\"",
                    "saveMeta('{$key}'",
                ] as $writePattern) {
                    $this->assertStringNotContainsString(
                        $writePattern,
                        $blade,
                        "{$form} exposes a write path into the landlord policy key {$key}."
                    );
                }
            }
        }
    }

    /** @test */
    public function the_landlord_screening_surfaces_do_not_read_any_applicant_disclosure_field(): void
    {
        $surfaces = [
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/applicant-requirements.blade.php',
            'app/Support/OfferListing/LandlordScreeningPolicy.php',
            'config/landlord_screening_options.php',
        ];

        foreach ($surfaces as $surface) {
            $source = file_get_contents(base_path($surface));

            foreach (self::APPLICANT_DISCLOSURE_FIELDS as $field) {
                // Guard against the substring trap: 'criminal_background' is a
                // prefix of 'criminal_background_requirement', so match the field
                // only where it is NOT followed by more identifier characters.
                $this->assertDoesNotMatchRegularExpression(
                    '/\b' . preg_quote($field, '/') . '(?![A-Za-z0-9_])/',
                    $source,
                    "{$surface} reads the applicant disclosure field {$field}."
                );
            }
        }
    }

    /** @test */
    public function neither_landlord_component_reads_an_applicant_disclosure_field(): void
    {
        foreach ([LandlordOfferListing::class, LandlordOfferListingEdit::class] as $component) {
            $source = file_get_contents((new ReflectionClass($component))->getFileName());

            foreach (self::APPLICANT_DISCLOSURE_FIELDS as $field) {
                $this->assertDoesNotMatchRegularExpression(
                    '/saveMeta\(\s*[\'"]' . preg_quote($field, '/') . '[\'"]/',
                    $source,
                    "{$component} persists the applicant disclosure field {$field} as listing meta."
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/get->' . preg_quote($field, '/') . '(?![A-Za-z0-9_])/',
                    $source,
                    "{$component} hydrates from the applicant disclosure field {$field}."
                );
            }
        }
    }

    // =====================================================================
    // The applicant disclosure lands where it always did, and nowhere else
    // =====================================================================

    /** @test */
    public function the_applicant_disclosure_is_written_to_its_own_table_not_to_listing_meta(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/RentalQualificationController.php'));

        // It is a column on rental_qualification_checks, created through the model.
        $this->assertStringContainsString('RentalQualificationCheck::create([', $controller);
        $this->assertStringContainsString("'criminal_background'", $controller);

        // And it never becomes a landlord listing meta.
        foreach (self::LANDLORD_POLICY_KEYS as $key) {
            $this->assertStringNotContainsString($key, $controller, "RentalQualificationController writes {$key}.");
        }
        $this->assertStringNotContainsString('saveMeta(', $controller);
    }

    /** @test */
    public function the_offer_path_keeps_the_disclosure_on_the_offer_not_on_the_listing(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/OfferController.php'));

        // The offer path stores the applicant's own answer against the offer.
        $this->assertMatchesRegularExpression(
            '/\$offer->saveMeta\(\s*[\'"]criminal_background[\'"]/',
            $controller
        );

        // It must not write any landlord screening policy key anywhere.
        foreach (self::LANDLORD_POLICY_KEYS as $key) {
            $this->assertStringNotContainsString(
                $key,
                $controller,
                "OfferController names {$key}; an applicant answer could overwrite the landlord's policy."
            );
        }
    }

    // =====================================================================
    // The boundary refuses applicant vocabulary on principle
    // =====================================================================

    /** @test */
    public function the_screening_policy_governs_no_applicant_disclosure_field(): void
    {
        foreach (self::APPLICANT_DISCLOSURE_FIELDS as $field) {
            $this->assertFalse(
                LandlordScreeningPolicy::isGovernedField($field),
                "The screening policy governs {$field}, which is an applicant disclosure, not a landlord policy."
            );
            $this->assertSame([], LandlordScreeningPolicy::optionsFor($field));
        }
    }

    /** @test */
    public function a_payload_mixing_the_two_vocabularies_writes_only_the_landlord_keys(): void
    {
        // The projection is fed applicant field names alongside landlord ones.
        // Only the landlord keys may come out the other side, and the applicant
        // answers must not have leaked into them.
        $projected = LandlordScreeningPolicy::project([
            'criminal_background'             => 'Criminal background disclosed',
            'criminal_background_other'       => 'Disclosed in person',
            'eviction_history'                => 'Yes',
            'employment_status'               => 'Retired',
            'criminal_background_requirement' => 'Individualized review of convictions',
        ]);

        foreach (self::APPLICANT_DISCLOSURE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $projected, "The projection emitted the applicant field {$field}.");
        }

        $this->assertSame('Individualized review of convictions', $projected['criminal_background_requirement']);

        foreach ($projected as $key => $value) {
            $this->assertNotSame('Criminal background disclosed', $value, "An applicant disclosure leaked into {$key}.");
            $this->assertNotSame('Retired', $value, "An applicant employment answer leaked into {$key}.");
        }
    }

    /**
     * @test
     *
     * Phase 2 deliberately does not redesign the applicant self-disclosure
     * CONTROLS. It changed one of these files, but only on its display side —
     * the part that republishes the landlord's policy. The applicant-facing
     * inputs, including their own "No criminal background" option, are a
     * separate concern and remain exactly as they were.
     */
    public function the_applicant_disclosure_controls_are_untouched_by_phase_two(): void
    {
        foreach (self::LEGACY_FORMS as $form) {
            $blade = file_get_contents(base_path($form));

            $this->assertStringContainsString(
                'name="criminal_background"',
                $blade,
                "{$form} no longer has the applicant disclosure control — the legacy forms were changed by a phase that was not scoped to them."
            );

            foreach (['Criminal background disclosed', 'Prefer to discuss'] as $applicantOption) {
                $this->assertStringContainsString(
                    $applicantOption,
                    $blade,
                    "{$form} lost the applicant option '{$applicantOption}'."
                );
            }

            // The applicant's own control is not driven by the landlord policy
            // allowlist — the two must not converge into one option list.
            $this->assertStringNotContainsString(
                "LandlordScreeningPolicy::optionsFor('criminal_background_requirement')",
                $blade,
                "{$form} renders the applicant control from the landlord policy allowlist."
            );
        }
    }
}
