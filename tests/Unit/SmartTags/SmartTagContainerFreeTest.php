<?php

namespace Tests\Unit\SmartTags;

use PHPUnit\Framework\TestCase;

/**
 * The taxonomy, selection policy, derivers and parser must answer with NO booted
 * application — they are pure boundaries, called from places a framework boot is
 * not guaranteed (the LandlordScreeningPolicy lesson).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SmartTagContainerFreeTest extends TestCase
{
    /** @test */
    public function the_pure_boundaries_answer_without_a_booted_container(): void
    {
        $root = dirname(__DIR__, 3);

        $script = <<<'PHP'
            $root = %s;
            require $root . '/vendor/autoload.php';
            // Resolve App\ from this checkout even if vendor/ is shared with another one.
            spl_autoload_register(static function (string $class) use ($root): void {
                if (str_starts_with($class, 'App\\')) {
                    $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                    if (is_file($file)) { require $file; }
                }
            }, true, true);

            use App\Support\SmartTags\SmartTagTaxonomy;
            use App\Support\SmartTags\SmartTagSelectionPolicy;
            use App\Support\SmartTags\SmartTagContext;
            use App\Support\SmartTags\SmartTagSource;
            use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;

            $valid = SmartTagTaxonomy::validationErrors() === [] ? 'VALID' : 'INVALID';
            $policy = SmartTagSelectionPolicy::project(['quartz_countertops', 'family_friendly'], SmartTagContext::ResidentialSale, 'owner');
            $parsed = array_keys((new ListingDescriptionTagParser())->parse('Quartz countertops.', SmartTagContext::ResidentialSale, SmartTagSource::NativeListingDescription));
            echo $valid . '|' . implode(',', $policy->accepted) . '|' . implode(',', $parsed);
PHP;

        $file = tempnam(sys_get_temp_dir(), 'smarttags') . '.php';
        file_put_contents($file, "<?php\n" . sprintf($script, var_export($root, true)));
        $output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        $this->assertSame('VALID|quartz_countertops|quartz_countertops', trim((string) $output));
    }
}
