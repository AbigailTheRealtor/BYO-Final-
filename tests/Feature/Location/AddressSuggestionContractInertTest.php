<?php

namespace Tests\Feature\Location;

use App\Services\Location\Suggestions\AddressSuggestionProviderInterface;
use ReflectionClass;
use Tests\TestCase;

/**
 * The suggestion layer stays inert at the edges.
 *
 * The contracts exist so that an address suggestion has a typed shape before
 * anything produces one. Nothing binds the interface, no route or component
 * calls it, and no file in the namespace can reach the network. These assertions
 * are what keep "a contract was added" from quietly becoming "a keystroke now
 * costs a request".
 *
 * WHAT CHANGED WHEN THE FIRST PROVIDER LANDED
 * -------------------------------------------
 * Two assertions here used to say "no implementation exists yet": the namespace
 * held exactly the two contract files, and nothing in `app/` implemented the
 * interface. Both were true statements about a phase, not about a safety
 * property — the safety property is that a suggestion cannot become a coordinate
 * and cannot cost a request, and neither of those is expressed by counting
 * files.
 *
 * {@see \App\Services\Location\Suggestions\AddressPointSuggestionProvider} is the
 * separately-reviewed step those two assertions were guarding the door for, so
 * they are replaced rather than deleted: the namespace inventory is still
 * asserted exactly (a third file is still a diff somebody has to justify), and
 * "nothing implements it" becomes "the only implementation is the local corpus
 * one, and it declares itself local". A *network* provider is still a separate
 * decision with its own review, and that is now what these assert.
 *
 * Everything else here is unchanged and still load-bearing: no container
 * binding, no route or Http caller, no outbound client, no Google symbol.
 */
class AddressSuggestionContractInertTest extends TestCase
{
    private const NAMESPACE_DIR = 'app/Services/Location/Suggestions';

    private function namespaceFiles(): array
    {
        return glob(base_path(self::NAMESPACE_DIR) . '/*.php') ?: [];
    }

    public function test_the_namespace_holds_the_two_contracts_and_the_corpus_provider(): void
    {
        $names = array_map('basename', $this->namespaceFiles());
        sort($names);

        $this->assertSame(
            [
                'AddressCandidate.php',
                'AddressPointSuggestionProvider.php',
                'AddressSuggestionProviderInterface.php',
            ],
            $names,
            'Another provider is a separate, reviewed step — not a side effect of adding one.'
        );
    }

    public function test_the_only_provider_is_the_local_corpus_one(): void
    {
        $implementors = [];

        foreach ($this->phpFilesUnder(base_path('app')) as $file) {
            $source = file_get_contents($file);

            if (is_string($source) && str_contains($source, 'implements AddressSuggestionProviderInterface')) {
                $implementors[] = str_replace(base_path() . '/', '', $file);
            }
        }

        $this->assertSame(
            ['app/Services/Location/Suggestions/AddressPointSuggestionProvider.php'],
            $implementors,
            'A network suggestion provider is a separate decision with its own review.'
        );
    }

    public function test_every_provider_that_exists_declares_itself_local(): void
    {
        // A suggestion surface fires per keystroke. The moment one of these
        // returns true, somebody has decided a keystroke may cost a request —
        // and that decision should arrive as a failing test, not as a bill.
        $provider = new \App\Services\Location\Suggestions\AddressPointSuggestionProvider();

        $this->assertFalse($provider->requiresNetwork());
    }

    public function test_the_provider_interface_is_bound_to_nothing(): void
    {
        // Resolving an unbound interface raises. If this ever stops raising,
        // something has wired a suggestion provider into the container.
        $this->expectException(\Illuminate\Contracts\Container\BindingResolutionException::class);

        app(AddressSuggestionProviderInterface::class);
    }

    public function test_the_interface_forces_a_provider_to_declare_its_network_cost(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(AddressSuggestionProviderInterface::class))->getMethods()
        );

        sort($methods);

        // A suggestion surface fires per keystroke, so a caller must be able to
        // learn what a provider costs before calling it — not after.
        $this->assertSame(
            ['isAvailable', 'providerId', 'requiresNetwork', 'suggest'],
            $methods
        );
    }

    public function test_no_file_in_the_namespace_constructs_an_outbound_client(): void
    {
        foreach ($this->namespaceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach (['Http::', 'GuzzleHttp', 'curl_init', 'file_get_contents(\'http'] as $needle) {
                $this->assertStringNotContainsString($needle, $source, basename($file));
            }
        }
    }

    public function test_no_google_symbol_appears_in_the_namespace(): void
    {
        foreach ($this->namespaceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach (['GOOGLE_PLACES', 'googleapis', 'GoogleCredential', 'Places\\'] as $needle) {
                $this->assertStringNotContainsString($needle, $source, basename($file));
            }
        }
    }

    public function test_no_route_or_component_references_the_suggestion_contracts(): void
    {
        $callers = [];

        foreach (array_merge(
            $this->phpFilesUnder(base_path('routes')),
            $this->phpFilesUnder(base_path('app/Http'))
        ) as $file) {
            $source = file_get_contents($file);

            if (is_string($source) && (
                str_contains($source, 'AddressSuggestionProviderInterface')
                || str_contains($source, 'AddressCandidate')
            )) {
                $callers[] = $file;
            }
        }

        $this->assertSame([], $callers, 'Wiring a suggestion surface is a later, separately-reviewed step.');
    }

    /** @return list<string> */
    private function phpFilesUnder(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
