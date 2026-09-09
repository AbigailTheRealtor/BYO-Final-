<?php

namespace Tests\Feature\LocationDna;

use App\Support\LocationDna\LocationDataAttribution;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The provider stays OFF, and the prerequisites for turning it on are enumerated
 * rather than remembered.
 *
 * This phase builds everything activation needs and activates nothing. The risk it
 * guards against is not that someone flips a flag maliciously; it is that the
 * prerequisites live in four files and a person's head, so a later phase reads
 * "the attribution work is done" and enables the corpus with the Foursquare NOTICE
 * still unobtained. These assertions are the checklist, executable.
 *
 * A FAILURE HERE IS NOT NECESSARILY A BUG. If activation is genuinely intended,
 * the corresponding assertion is what should be updated — deliberately, in the
 * same change that satisfies the obligation it names.
 */
class OvertureActivationReadinessTest extends TestCase
{
    // The base TestCase migrates the shared in-memory schema only for classes that
    // declare this trait, and these tests render real pages — `layouts.main` reads
    // `settings` through get_setting(). Without it the page 500s on a missing table
    // and the attribution assertions never get to run.
    use DatabaseTransactions;

    /** @test */
    public function the_adapter_gate_defaults_off(): void
    {
        // Default false in config, independent of whatever the environment says —
        // read the file rather than the resolved value so a set env var cannot make
        // this pass.
        $config = require base_path('config/overture_corpus_poi.php');

        $this->assertFalse(
            (bool) $config['enabled'],
            'OVERTURE_CORPUS_POI_ENABLED is set in this environment, or the default changed.'
        );
    }

    /** @test */
    public function no_corpus_version_is_pinned(): void
    {
        $config = require base_path('config/overture_corpus_poi.php');

        $this->assertNull(
            $config['corpus_version'],
            'A corpus version is pinned. Both gates must be off before activation is reviewed.'
        );
    }

    /** @test */
    public function the_registry_gate_defaults_off(): void
    {
        $config = require base_path('config/location_providers.php');

        $this->assertFalse(
            (bool) $config['providers']['overture_corpus']['enabled'],
            'The corpus provider is enabled in the registry.'
        );
    }

    /** @test */
    public function the_two_gates_still_disagree_with_nothing(): void
    {
        // Both gates must agree before a corpus read happens. Asserting them
        // together catches the half-activation — one file edited, the other not —
        // which would otherwise look like "off" while being one edit from "on".
        $adapter  = (bool) (require base_path('config/overture_corpus_poi.php'))['enabled'];
        $registry = (bool) (require base_path('config/location_providers.php'))['providers']['overture_corpus']['enabled'];

        $this->assertFalse($adapter || $registry, 'One of the two corpus gates is on.');
    }

    /** @test */
    public function the_licensing_prerequisite_is_satisfied_and_did_not_activate_anything(): void
    {
        // The NOTICE obligation is now met: the verbatim upstream text, the full
        // Apache-2.0 license and our notice of changes are all committed.
        $this->assertSame([], LocationDataAttribution::noticeObligationsOutstanding());
        $this->assertFalse(LocationDataAttribution::providerBlockedByNotice('overture_corpus'));

        // AND THE PROVIDER IS STILL OFF. This assertion is the point of the test:
        // NOTICE readiness is not activation authorization, and the failure mode worth
        // guarding is someone satisfying the licensing prerequisite and taking that as
        // permission to serve. The gates are re-read here rather than trusted from the
        // tests above, so this pairing cannot pass by accident.
        $adapter  = (bool) (require base_path('config/overture_corpus_poi.php'))['enabled'];
        $registry = (bool) (require base_path('config/location_providers.php'))['providers']['overture_corpus']['enabled'];

        $this->assertFalse(
            $adapter || $registry,
            'A satisfied NOTICE obligation was accompanied by an enabled corpus provider.'
        );
    }

    /** @test */
    public function the_attribution_surfaces_exist_before_activation_can_publish_anything(): void
    {
        // The converse prerequisite: attribution must be BUILT before the provider
        // can be enabled, not alongside it. These are the three artifacts that make
        // a published corpus row credited.
        $this->assertFileExists(base_path('NOTICE'));
        $this->assertFileExists(base_path('resources/views/partials/location-dna/_data-attribution.blade.php'));
        $this->assertFileExists(base_path('resources/views/data-sources.blade.php'));

        $this->get(route('data-sources'))->assertOk();

        $this->assertStringContainsString(
            'partials.location-dna._data-attribution',
            (string) file_get_contents(base_path('resources/views/partials/location-dna-agent-panel.blade.php')),
            'The shared Location DNA panel does not include the attribution component.'
        );
    }
}
