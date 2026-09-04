<?php

namespace Tests\Feature\FairHousing;

use App\Support\OfferListing\LandlordProviderTextPolicy;
use App\Support\OfferListing\LandlordScreeningPolicy;
use Tests\TestCase;

/**
 * Fair Housing Phase 3 — the provider-text policy itself.
 *
 * THE POSITIVE CONTROLS ARE THE POINT OF THIS FILE. A moderation boundary that
 * rejects everything passes every "unsafe text is blocked" assertion ever written,
 * so the accepted cases below are not decoration — they are what stops this suite
 * going green on a policy that has quietly become useless. Several of them contain
 * the exact nouns the rules look for ("wheelchair", "benefit", "no ...") and must
 * survive anyway, because the rules match exclusion STRUCTURE and not vocabulary.
 */
class LandlordProviderTextPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LandlordProviderTextPolicy::flushCache();
    }

    /* =====================================================================
     * POSITIVE CONTROLS — must be ACCEPTED
     * ===================================================================== */

    public function legitimateProvider(): array
    {
        return [
            'credit + income'        => ['landlord_approval_conditions', 'Credit score 650+, income 3x rent'],
            'lease term + insurance' => ['landlord_approval_conditions', '12-month minimum lease, renter\'s insurance required'],
            'documentation'          => ['landlord_approval_conditions', 'Two most recent pay stubs or benefit award letter'],
            'deposit timing'         => ['landlord_approval_conditions', 'Security deposit due at signing.'],
            'placeholder example'    => ['landlord_approval_conditions', 'Credit score 650+, Income 3x monthly rent, No prior evictions'],
            'ordinary pet terms'     => ['pet_restrictions', 'Maximum 2 pets, 50 lb weight limit'],
            'pet placeholder'        => ['pet_restrictions', 'Maximum 2 pets, No aggressive breeds, 50 lb weight limit'],
            'pet deposit'            => ['pet_restrictions', 'Pet deposit $300, cats and small dogs only'],
            'accessibility FACT'     => ['additional_details', 'Property is wheelchair accessible with a zero-step entry'],
            'property facts'         => ['additional_details', 'Quiet building, no smoking indoors, on-site laundry.'],
            'no-prior-evictions'     => ['landlord_approval_conditions', 'No prior evictions in the last five years'],
        ];
    }

    /**
     * @test
     * @dataProvider legitimateProvider
     */
    public function legitimate_provider_text_is_accepted(string $field, string $text): void
    {
        $decision = LandlordProviderTextPolicy::decide($field, $text);

        $this->assertTrue(
            $decision['allowed'],
            "FALSE POSITIVE on [{$field}] \"{$text}\" — category {$decision['category']}, matched \"{$decision['matched']}\"."
        );
        $this->assertNull(LandlordProviderTextPolicy::publishError($field, $text));
        $this->assertSame($text, LandlordProviderTextPolicy::displayValue($field, $text));
    }

    /* =====================================================================
     * NEGATIVE CONTROLS — must be BLOCKED
     * ===================================================================== */

    public function prohibitedProvider(): array
    {
        return [
            'professionals only'   => ['landlord_approval_conditions', 'Professionals only, no children.', 'protected_class_preference'],
            'no children'          => ['landlord_approval_conditions', 'No children please.',              'protected_class_preference'],
            'adults preferred'     => ['landlord_approval_conditions', 'Adults preferred.',                'protected_class_preference'],
            'must speak english'   => ['landlord_approval_conditions', 'Must speak English.',              'protected_class_preference'],
            'no wheelchair users'  => ['landlord_approval_conditions', 'No wheelchair users.',             'disability_exclusion'],
            'live independently'   => ['landlord_approval_conditions', 'Must be able to live independently.', 'disability_exclusion'],
            'no section 8'         => ['landlord_approval_conditions', 'No Section 8.',                    'source_of_income_exclusion'],
            'no vouchers'          => ['landlord_approval_conditions', 'No housing vouchers accepted.',    'source_of_income_exclusion'],
            'employment only'      => ['landlord_approval_conditions', 'Employment income only.',          'source_of_income_exclusion'],
            'no ESA (pets)'        => ['pet_restrictions',             'No emotional support animals.',    'assistance_animal_as_pet'],
            'no service dogs'      => ['pet_restrictions',             'No service dogs.',                 'assistance_animal_as_pet'],
            'ESA pays pet fee'     => ['pet_restrictions',             'Service animals are subject to the pet fee.', 'assistance_animal_as_pet'],
            'steering suited'      => ['additional_details',           'Better suited to young professionals.', 'steering'],
            'steering neighbourhood' => ['additional_details',         'This neighborhood is mostly families.', 'steering'],
        ];
    }

    /**
     * @test
     * @dataProvider prohibitedProvider
     */
    public function prohibited_provider_text_is_blocked(string $field, string $text, string $category): void
    {
        $decision = LandlordProviderTextPolicy::decide($field, $text);

        $this->assertFalse($decision['allowed'], "MISSED on [{$field}] \"{$text}\".");
        $this->assertSame($category, $decision['category']);
        $this->assertNotSame('', (string) $decision['matched'], 'A block must name the offending phrase.');
        $this->assertNotNull(LandlordProviderTextPolicy::publishError($field, $text));

        // Suppressed for every reader, without touching the stored bytes.
        $this->assertNull(LandlordProviderTextPolicy::displayValue($field, $text));
    }

    /* =====================================================================
     * The consumer firewall
     * ===================================================================== */

    /**
     * @test
     *
     * The same words from a consumer are a lawful statement about their own life.
     * The policy cannot reach them, and the mechanism is not a carve-out that could
     * be forgotten: consumer keys are simply not governed fields, so `decide()`
     * returns `allowed` and `displayValue()` returns the text unchanged.
     */
    public function consumer_first_person_text_is_never_moderated(): void
    {
        $consumer = [
            'accessibility_requirements'    => 'I need a wheelchair-accessible unit',
            'screening_concerns_explanation' => 'I have an emotional support animal',
            'income_source'                 => 'I receive Section 8 assistance',
            'additional_information'        => 'No children — I live alone',
        ];

        foreach ($consumer as $field => $text) {
            $decision = LandlordProviderTextPolicy::decide($field, $text);

            $this->assertTrue($decision['allowed'], "Consumer field {$field} was moderated.");
            $this->assertFalse(LandlordProviderTextPolicy::isGovernedField($field));
            $this->assertSame($text, LandlordProviderTextPolicy::displayValue($field, $text));
        }
    }

    /** @test */
    public function only_landlord_provider_prose_fields_are_governed(): void
    {
        $this->assertSame(
            ['landlord_approval_conditions', 'pet_restrictions', 'additional_details'],
            array_keys(LandlordProviderTextPolicy::fields())
        );
    }

    /* =====================================================================
     * Storage: bounded, never rewritten
     * ===================================================================== */

    /** @test */
    public function storage_bounds_length_but_never_rewrites_text(): void
    {
        $unsafe = 'Professionals only, no children.';

        // The write keeps the landlord's exact words — refusal happens at publish.
        $this->assertSame($unsafe, LandlordProviderTextPolicy::projectForStorage('landlord_approval_conditions', $unsafe));

        $long = str_repeat('a', 5000);
        $this->assertSame(
            LandlordProviderTextPolicy::maxLength('landlord_approval_conditions'),
            mb_strlen(LandlordProviderTextPolicy::projectForStorage('landlord_approval_conditions', $long))
        );

        // Ungoverned keys pass through untouched.
        $this->assertSame($long, LandlordProviderTextPolicy::projectForStorage('some_other_key', $long));
    }

    /** @test */
    public function curly_punctuation_cannot_evade_a_rule(): void
    {
        $this->assertFalse(
            LandlordProviderTextPolicy::isAllowed('landlord_approval_conditions', "No  children\u{00A0}please"),
            'Whitespace/punctuation normalisation failed.'
        );
    }

    /* =====================================================================
     * Container independence — the Phase 2 lesson
     * ===================================================================== */

    /**
     * @test
     *
     * `LandlordScreeningPolicy` is called from the Ask AI landlord extractor, whose
     * unit test has no application booted. A bare `config()` there raises,
     * `buildForListing()` catches every Throwable, and the symptom is an ENTIRELY
     * EMPTY listing context with the real error swallowed. This class is reachable
     * from the same place, so it must answer with no container at all.
     *
     * Run in a separate process with no framework boot.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function the_policy_answers_without_a_booted_container(): void
    {
        $script = <<<'PHP'
            require %s;
            $p = App\Support\OfferListing\LandlordProviderTextPolicy::class;
            $blocked = $p::isAllowed('landlord_approval_conditions', 'No Section 8.') ? 'ALLOW' : 'BLOCK';
            $safe    = $p::isAllowed('landlord_approval_conditions', 'Credit score 650+') ? 'ALLOW' : 'BLOCK';
            echo $blocked . '|' . $safe;
PHP;

        $autoload = var_export(base_path('vendor/autoload.php'), true);
        $file     = tempnam(sys_get_temp_dir(), 'p3') . '.php';
        file_put_contents($file, "<?php\n" . sprintf($script, $autoload));

        $output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        $this->assertSame('BLOCK|ALLOW', trim((string) $output),
            'The policy did not behave correctly without a booted container.');
    }

    /* =====================================================================
     * Parent-gated custom text (the five ungoverned inputs + fixed income)
     * ===================================================================== */

    /** @test */
    public function every_custom_field_declares_its_real_parent_and_trigger(): void
    {
        $expected = [
            'custom_credit_score_requirement'    => ['min_credit_score', 'Other'],
            'custom_income_requirement'          => ['income_qualification_method', 'Other'],
            'min_monthly_income_fixed'           => ['income_qualification_method', 'Fixed Monthly Income'],
            'custom_smoking_policy_requirement'  => ['smoking_policy_requirement', 'Other'],
            'custom_reference_requirement'       => ['reference_requirement', 'Other'],
            'custom_preferred_move_in_timeframe' => ['preferred_move_in_timeframe', 'Other'],
        ];

        foreach ($expected as $key => [$parent, $trigger]) {
            $definition = LandlordScreeningPolicy::customFields()[$key] ?? null;
            $this->assertNotNull($definition, "{$key} is not governed.");
            $this->assertSame($parent, $definition['parent']);
            $this->assertSame($trigger, $definition['unlocks_on'],
                "{$key}'s trigger must come from the real form, not an assumed 'Other'.");
        }
    }

    /** @test */
    public function custom_text_survives_only_when_its_parent_authorises_it(): void
    {
        foreach (LandlordScreeningPolicy::customFields() as $key => $definition) {
            $parent  = $definition['parent'];
            $trigger = $definition['unlocks_on'];

            $allowed = LandlordScreeningPolicy::projectCustomFields([
                $parent => $trigger,
                $key    => 'legitimate text',
            ]);
            $this->assertSame('legitimate text', $allowed[$key], "{$key} was dropped with a valid parent.");

            $denied = LandlordScreeningPolicy::projectCustomFields([
                $parent => 'No requirement',
                $key    => 'CRAFTED-BYPASS',
            ]);
            $this->assertSame('', $denied[$key], "{$key} persisted without its parent condition.");
        }
    }

    /** @test */
    public function a_parent_changed_later_orphans_its_custom_text_on_read(): void
    {
        $this->assertNull(
            LandlordScreeningPolicy::customDisplayValue('custom_credit_score_requirement', 'No requirement', 'stale text'),
            'Orphaned custom text is still displayable.'
        );
        $this->assertSame(
            'live text',
            LandlordScreeningPolicy::customDisplayValue('custom_credit_score_requirement', 'Other', 'live text')
        );
    }
}
