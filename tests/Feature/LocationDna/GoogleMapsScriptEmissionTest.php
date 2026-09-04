<?php

namespace Tests\Feature\LocationDna;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The Maps SDK loader must be honest in BOTH directions, and this is the only test in the
 * suite that can say so.
 *
 * WHY IT NEEDS TO EXIST
 * ---------------------
 * `tests/bootstrap.php` blanks `GOOGLE_PLACES_API_KEY` for the whole suite (INV-11), so
 * every other test runs in the degraded state and none of them can distinguish "the loader
 * correctly emits the SDK" from "the loader emits nothing at all". That is precisely the
 * blind spot that let a missing credential take out city autocomplete, county autocomplete,
 * radius geocoding, the map, both draw tools, every boundary overlay and Important Places
 * pins simultaneously, with a green suite.
 *
 * This test sets the credential EXPLICITLY through config rather than relying on ambient
 * state, so it is unaffected by the bootstrap blanking and issues no request either way —
 * asserting on emitted markup, never on Google answering.
 *
 * It deliberately asserts the DEGRADED branch too. A loader that silently emits nothing is
 * indistinguishable from one that is working until a user reports a dead map; the amber
 * panel is the only thing that makes the state visible on the page.
 */
class GoogleMapsScriptEmissionTest extends TestCase
{
    private function render(string $key): string
    {
        config(['services.google.places_key' => $key]);

        return Blade::render('<x-google-maps-script :libraries="\'places,drawing\'" />');
    }

    public function test_the_sdk_is_emitted_when_a_credential_is_present(): void
    {
        $html = $this->render('test-key-not-a-real-credential');

        $this->assertStringContainsString(
            'maps.googleapis.com/maps/api/js',
            $html,
            'the Maps JS SDK is no longer emitted with a credential present — every Location DNA '
            . 'control initialises from inside ldnaInitMap(), which cannot run without it.'
        );
        $this->assertStringContainsString('libraries=places,drawing', $html);
        $this->assertStringNotContainsString('Google Maps is not configured', $html);
    }

    public function test_the_degraded_panel_is_emitted_and_no_sdk_when_the_credential_is_blank(): void
    {
        $html = $this->render('');

        $this->assertStringNotContainsString(
            'maps.googleapis.com',
            $html,
            'a blank credential must never produce an SDK request'
        );
        $this->assertStringContainsString(
            'Google Maps is not configured',
            $html,
            'with no credential the page must SAY so — a silently absent loader is what made this '
            . 'regression invisible until a user reported a dead map.'
        );
    }

    /**
     * A blank `.env` line is a PLACEHOLDER, not a kill switch.
     *
     * This project keeps every third-party credential as a platform secret and ships blank
     * lines for them in `.env` — AWS and Bridge both work exactly this way. Laravel builds
     * its dotenv repository IMMUTABLY, so a real process environment variable wins and the
     * blank line never overwrites it. Pinned here because the whole diagnosis of this
     * incident rests on it: the fix is to restore the secret, NOT to edit `.env`.
     */
    public function test_a_process_environment_credential_beats_a_blank_dotenv_placeholder(): void
    {
        $repo = \Dotenv\Repository\RepositoryBuilder::createWithDefaultAdapters()->immutable()->make();

        $dir = sys_get_temp_dir() . '/ldna-dotenv-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        try {
            file_put_contents("{$dir}/.env", "BYO_PLACEHOLDER_PROBE=\n");

            putenv('BYO_PLACEHOLDER_PROBE=value-from-platform-secret');
            $_SERVER['BYO_PLACEHOLDER_PROBE'] = 'value-from-platform-secret';
            $_ENV['BYO_PLACEHOLDER_PROBE']    = 'value-from-platform-secret';

            \Dotenv\Dotenv::create($repo, $dir, '.env')->safeLoad();

            $this->assertSame(
                'value-from-platform-secret',
                $repo->get('BYO_PLACEHOLDER_PROBE'),
                'a blank .env line overwrote a platform secret — if this ever becomes true, blank '
                . 'placeholders stop being safe and every credential must move into .env.'
            );
        } finally {
            putenv('BYO_PLACEHOLDER_PROBE');
            unset($_SERVER['BYO_PLACEHOLDER_PROBE'], $_ENV['BYO_PLACEHOLDER_PROBE']);
            @unlink("{$dir}/.env");
            @rmdir($dir);
        }
    }
}
