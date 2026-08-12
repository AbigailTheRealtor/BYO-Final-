<?php

namespace Tests\Feature\Location;

use App\Services\Location\Suggestions\AddressSuggestionProviderInterface;
use ReflectionClass;
use Tests\TestCase;

/**
 * The suggestion contracts ship inert.
 *
 * They exist so that an address suggestion has a typed shape before anything
 * produces one. Nothing implements the interface, nothing binds it, no route or
 * component calls it, and no file in the namespace can reach the network. These
 * assertions are what keep "a contract was added" from quietly becoming "a
 * keystroke now costs a request".
 */
class AddressSuggestionContractInertTest extends TestCase
{
    private const NAMESPACE_DIR = 'app/Services/Location/Suggestions';

    private function namespaceFiles(): array
    {
        return glob(base_path(self::NAMESPACE_DIR) . '/*.php') ?: [];
    }

    public function test_the_namespace_holds_only_the_two_contracts(): void
    {
        $names = array_map('basename', $this->namespaceFiles());
        sort($names);

        $this->assertSame(
            ['AddressCandidate.php', 'AddressSuggestionProviderInterface.php'],
            $names,
            'A provider implementation is a separate, reviewed step — not a side effect of adding a contract.'
        );
    }

    public function test_nothing_implements_the_provider_interface(): void
    {
        $implementors = [];

        foreach ($this->phpFilesUnder(base_path('app')) as $file) {
            $source = file_get_contents($file);

            if (is_string($source) && str_contains($source, 'implements AddressSuggestionProviderInterface')) {
                $implementors[] = $file;
            }
        }

        $this->assertSame([], $implementors, 'No suggestion provider exists yet.');
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
