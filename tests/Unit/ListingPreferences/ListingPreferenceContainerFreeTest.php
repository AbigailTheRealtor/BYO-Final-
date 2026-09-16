<?php

namespace Tests\Unit\ListingPreferences;

use PHPUnit\Framework\TestCase;

/**
 * The reason catalog and the reason policy must answer with NO booted
 * application.
 *
 * The same requirement, for the same reason, as SmartTagContainerFreeTest: these
 * are pure boundaries called from places a framework boot is not guaranteed, and
 * when `config()` raises there the symptom arrives several frames away as an
 * EMPTY vocabulary — which would look like "this customer chose no reasons"
 * rather than like a crash. That is the LandlordScreeningPolicy lesson.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ListingPreferenceContainerFreeTest extends TestCase
{
    /** @test */
    public function the_reason_boundaries_answer_without_a_booted_container(): void
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

            use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
            use App\Support\ListingPreferences\ListingPreferenceReasonPolicy;
            use App\Support\ListingPreferences\ListingPreferenceState;

            $valid  = ListingPreferenceReasonCatalog::validationErrors() === [] ? 'VALID' : 'INVALID';
            $policy = ListingPreferenceReasonPolicy::project(
                ['natural_light', 'accessible_features', 'too_expensive'],
                ListingPreferenceState::Save,
            );
            $count = count(ListingPreferenceReasonCatalog::all()) > 0 ? 'NONEMPTY' : 'EMPTY';

            echo $valid . '|' . implode(',', $policy->accepted) . '|' . $count;
PHP;

        $file = tempnam(sys_get_temp_dir(), 'listingprefs') . '.php';
        file_put_contents($file, "<?php\n" . sprintf($script, var_export($root, true)));
        $output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        // natural_light survives; the disability-sensitive tag and the
        // wrong-state reason do not — with no container anywhere.
        $this->assertSame('VALID|natural_light|NONEMPTY', trim((string) $output));
    }
}
