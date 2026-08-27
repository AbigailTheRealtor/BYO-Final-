<?php

namespace Tests\Feature\HireAgent;

use Tests\TestCase;

/**
 * Source-level guards for the Fair Housing retirement.
 *
 * WHY AT SOURCE RATHER THAN THROUGH A RENDER. A rendered assertion proves one page, in one state,
 * for one role. These prove the strings are not in the codebase at all — which is the property
 * that matters for a field being RETIRED rather than merely hidden. It is the same technique
 * HireAgentDetailRedesignFlagTest already uses to prove a config file has exactly one reader.
 *
 * A failure here is not necessarily a bug. It means someone reintroduced retired wording or a
 * retired key, and that is a decision that should be made deliberately and reviewed — not one that
 * slips in because a nearby file was copied.
 */
class HireAgentFairHousingWordingTest extends TestCase
{
    /**
     * Wording that must never describe a landlord's leasing preference.
     *
     * Each of these frames the question around WHO occupies a property rather than what happens
     * there, which is the distinction the retirement turns on.
     */
    private const BANNED_PHRASES = [
        'tenant profile',
        'tenant demographic',
        'type of person',
        'preferred occupant',
        'preferred clientele',
        'Preferred Tenant Type',
        'High-Quality Tenant Profile',
    ];

    /** Keys retired from the compatibility blob. */
    private const RETIRED_KEYS = [
        'tenant_type_preference',
        'tenant_type_preference_other',
    ];

    /** @test */
    public function no_landlord_hire_agent_view_contains_retired_wording(): void
    {
        $hits = [];

        foreach ($this->landlordViewFiles() as $path) {
            $contents = $this->withoutComments((string) file_get_contents($path));

            foreach (self::BANNED_PHRASES as $phrase) {
                if (stripos($contents, $phrase) !== false) {
                    $hits[] = $this->relative($path) . ' :: ' . $phrase;
                }
            }
        }

        $this->assertSame([], $hits,
            "Retired Fair Housing wording reappeared in a landlord Hire Agent view:\n  "
            . implode("\n  ", $hits)
            . "\n\nThese phrases describe who occupies a property rather than what happens there. "
            . 'Preferred Business Use (commercial only) is the sanctioned replacement.');
    }

    /**
     * The retired keys may survive only where they are being talked ABOUT: the remediation command
     * that deletes them, the policy documentation that explains why, and the tests that prove they
     * cannot come back.
     *
     * @test
     */
    public function the_retired_keys_appear_in_no_active_code_path(): void
    {
        // Only the remediation command may name these keys in EXECUTING code — it is the thing
        // that deletes them. Everywhere else, an explanatory comment is fine (comments are
        // stripped before the scan) but a live reference is not.
        $allowed = [
            'app/Console/Commands/RetireTenantTypePreference.php',
        ];

        $hits = [];

        foreach ($this->sourceFiles([base_path('app'), base_path('resources/views'), base_path('routes'), base_path('config')]) as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, $allowed, true)) {
                continue;
            }

            $contents = $this->withoutComments((string) file_get_contents($path));

            foreach (self::RETIRED_KEYS as $key) {
                if (str_contains($contents, $key)) {
                    $hits[] = $relative . ' :: ' . $key;
                }
            }
        }

        $this->assertSame([], $hits,
            "A retired compatibility key reappeared in application code:\n  "
            . implode("\n  ", $hits));
    }

    /**
     * The allowlist is the boundary, so it must not list what the retirement removed. This reads
     * the config directly rather than through the policy: the policy could be changed to filter
     * these out and the config would still be wrong.
     *
     * @test
     */
    public function the_allowlist_config_does_not_name_any_retired_key(): void
    {
        $roles = config('hire_agent_compatibility_keys.roles');
        $all   = [];

        foreach ($roles as $keys) {
            $all = array_merge($all, is_array($keys) ? $keys : []);
        }

        foreach (self::RETIRED_KEYS as $key) {
            $this->assertNotContains($key, $all,
                "'{$key}' is retired and must not be allowlisted for any role.");
        }

        $this->assertNotContains('risk_tolerance', $roles['landlord'],
            'Landlord risk_tolerance was replaced by applicant_screening_approach.');
        $this->assertContains('risk_tolerance', $roles['buyer'],
            'Buyer risk_tolerance is about offer strategy and is unrelated; it must survive.');
    }

    /**
     * The one place the option list lives. A second copy is how a validator starts rejecting an
     * option the form offers.
     *
     * @test
     */
    public function the_business_use_options_are_defined_once_and_name_no_person(): void
    {
        $options = config('landlord_business_use_options.options');

        $this->assertNotEmpty($options);
        $this->assertContains('Other', $options);

        foreach ($options as $option) {
            // NB: not 'professional' — "Professional Services" is a business activity (law,
            // accountancy, consulting), not a description of who works there.
            foreach (['tenant', 'family', 'student', 'occupant', 'resident'] as $personWord) {
                $this->assertStringNotContainsStringIgnoringCase($personWord, $option,
                    "Business use option '{$option}' names a kind of person. Every option must "
                    . 'name a business ACTIVITY.');
            }
        }
    }

    /**
     * The landlord-side assistance-animal controls are gone, not commented out.
     *
     * Commenting them out is what left them one uncomment away from returning, and a Yes/No on
     * service animals is not a preference a housing provider states — it is a disability
     * accommodation. The consumer-side fields of the same name are a different thing entirely and
     * are deliberately not covered here.
     *
     * @test
     */
    public function the_landlord_assistance_animal_preference_controls_are_gone(): void
    {
        $hits = [];

        foreach ($this->landlordViewFiles() as $path) {
            $contents = (string) file_get_contents($path);

            foreach (['wire:model="service_animal"', 'wire:model="support_animal"'] as $control) {
                if (str_contains($contents, $control)) {
                    $hits[] = $this->relative($path) . ' :: ' . $control;
                }
            }
        }

        $this->assertSame([], $hits,
            "A landlord-side assistance-animal preference control is present again:\n  "
            . implode("\n  ", $hits));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function landlordViewFiles(): array
    {
        return $this->sourceFiles([
            base_path('resources/views/livewire/hire-landlord-agent'),
            base_path('resources/views/hire_landlord_agent'),
        ]);
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function sourceFiles(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($it as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Source with comments removed.
     *
     * THE RULE IS ABOUT WHAT A USER SEES, NOT WHAT THE CODE EXPLAINS. Every one of these phrases
     * appears legitimately in the comments that record WHY the field was retired — and a guard
     * that forbade explaining itself would push those explanations out of the codebase, which is
     * the opposite of what this batch wants.
     *
     * Blade comments and PHP comments are stripped; HTML comments deliberately are NOT, because
     * an HTML comment is shipped to the browser and is visible in page source.
     */
    private function withoutComments(string $source): string
    {
        // Blade: {{-- ... --}}, and PHP block comments.
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;
        $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

        // Whole-line // and doc-continuation * comments only — never mid-line, which would
        // mangle a string containing "//".
        $lines = preg_split('/\R/', $source) ?: [];
        $kept  = array_filter($lines, function (string $line): bool {
            $trimmed = ltrim($line);

            return !str_starts_with($trimmed, '//') && !str_starts_with($trimmed, '* ');
        });

        return implode("\n", $kept);
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
