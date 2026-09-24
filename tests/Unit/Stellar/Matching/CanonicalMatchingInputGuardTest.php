<?php

namespace Tests\Unit\Stellar\Matching;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * P1-B — two source-level guards.
 *
 *  1. The canonical builder and the residual object are provider-neutral and pure:
 *     their CODE (comments excluded, and the historical `App\Services\Stellar\Matching`
 *     namespace declaration excluded) names no provider, no provider model or
 *     ingestion class, no `raw_json`, no `STELLAR_*` key, no RESO field name and no
 *     RESO property-type literal — the type comes from PropertyTypeVocabulary — and
 *     reaches for no decoder, database, network, cache, container or Smart Tags
 *     machinery. Provider knowledge lives only in a reader; Smart Tags are attached
 *     beside the facts by the caller.
 *
 *  2. Nothing is wired: no file in app/ references the three P1-B classes, or the new
 *     vocabulary accessor, apart from the P1-B classes themselves. Live matching still
 *     runs BridgeProperty → BridgeListingMatchFactsBuilder.
 */
class CanonicalMatchingInputGuardTest extends TestCase
{
    private const NEUTRAL_FILES = [
        'app/Services/Stellar/Matching/CanonicalListingMatchFactsBuilder.php',
        'app/Services/Stellar/Matching/ListingMatchResidualFacts.php',
    ];

    private const P1B_FILES = [
        'app/Services/Stellar/Matching/CanonicalListingMatchFactsBuilder.php',
        'app/Services/Stellar/Matching/ListingMatchResidualFacts.php',
        'app/Services/Bridge/BridgeResidualMatchFactsReader.php',
    ];

    private const FORBIDDEN_NAMES = [
        'Stellar', 'Bridge', 'BridgeProperty', 'raw_json', 'PropertyCandidate',
        'MlsCanonicalListingResolver', 'MlsListingAdapter', 'MlsProvider',
        // RESO field names the scorer's facts come from
        'ListingKey', 'ListPrice', 'LeaseAmountFrequency', 'LivingArea', 'LotSizeSquareFeet', 'YearBuilt',
        'BuildingAreaTotal', 'PropertyType', 'PropertySubType', 'PoolPrivateYN', 'GarageYN', 'WaterfrontYN',
        'ViewYN', 'AssociationFee', 'AssociationFeeFrequency', 'AssociationYN', 'TaxAnnualAmount',
        'NewConstructionYN', 'PetsAllowed', 'CommunityFeatures', 'AssociationAmenities', 'GreenEnergyEfficient',
        'GreenBuildingVerificationType', 'LeaseTerm', 'DaysOnMarket', 'ElementarySchool', 'HighSchool',
        'Latitude', 'Longitude', 'City', 'StateOrProvince', 'PostalCode', 'CountyOrParish',
    ];

    /**
     * Dependencies matched by STRUCTURE rather than as whole words. `STELLAR_` is a
     * prefix: a word boundary after the underscore could never match a real key such
     * as `STELLAR_CDDYN`, so none is used.
     *
     * @var array<string,string> label => pattern
     */
    private const FORBIDDEN_PATTERNS = [
        'a STELLAR_* key'         => '/STELLAR_/',
        'json_decode'             => '/\bjson_decode\s*\(/',
        'database access'         => '/\b(DB|Schema)\s*::|Illuminate\\\\Database\\\\|->\s*(query|table|select|insert|update|delete)\s*\(/',
        'network access'          => '/\b(Http|GuzzleHttp|Guzzle\w*)\b|\bcurl_\w+\s*\(|\b(file_get_contents|fopen|fsockopen|stream_socket_client)\s*\(/',
        'cache or storage'        => '/\b(Cache|Storage|Redis|Session)\s*::/',
        'a framework facade'      => '/Illuminate\\\\Support\\\\Facades\\\\/',
        'the container or config' => '/\b(config|env|app|resolve)\s*\(/',
        'Smart Tags machinery'    => '/SmartTag|withSmartTags/',
    ];

    private const RESO_TYPE_LITERALS = [
        'Residential', 'Residential Lease', 'Income', 'Commercial Sale', 'Commercial Lease',
        'Business Opportunity', 'Vacant Land', 'ResidentialIncome', 'Land',
    ];

    public function test_the_builder_and_the_residual_object_are_provider_neutral(): void
    {
        foreach (self::NEUTRAL_FILES as $file) {
            [$code, $literals] = self::code($file);

            foreach (self::FORBIDDEN_NAMES as $name) {
                $this->assertDoesNotMatchRegularExpression('/\b' . preg_quote($name, '/') . '\b/', $code, "{$file} names {$name}");
            }
            foreach (self::FORBIDDEN_PATTERNS as $label => $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $code, "{$file} reaches for {$label}");
            }
            $this->assertSame([], array_values(array_intersect($literals, self::RESO_TYPE_LITERALS)), "{$file} hard-codes a property type");
        }
    }

    public function test_no_application_code_consumes_the_p1b_classes(): void
    {
        $needles = ['CanonicalListingMatchFactsBuilder', 'ListingMatchResidualFacts', 'BridgeResidualMatchFactsReader', 'recognisedTypeFor'];
        $allowed = array_merge(self::P1B_FILES, ['app/Support/Listing/PropertyTypeVocabulary.php']);
        $found   = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/app'));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen(self::root()) + 1);
            $source   = (string) file_get_contents($file->getPathname());

            foreach ($needles as $needle) {
                if (str_contains($source, $needle) && !in_array($relative, $allowed, true)) {
                    $found[] = "{$relative} → {$needle}";
                }
            }
        }

        $this->assertSame([], $found, 'P1-B must not be wired into any live path');

        // The accessor's only consumer is the canonical builder.
        [$vocabulary] = self::code('app/Support/Listing/PropertyTypeVocabulary.php');
        $this->assertStringNotContainsString('CanonicalListing', $vocabulary);
        [$reader] = self::code('app/Services/Bridge/BridgeResidualMatchFactsReader.php');
        $this->assertStringNotContainsString('recognisedTypeFor', $reader);
    }

    public function test_the_legacy_builder_is_still_the_live_path_and_does_not_delegate(): void
    {
        [$legacy] = self::code('app/Services/Bridge/BridgeListingMatchFactsBuilder.php');
        [$scorer] = self::code('app/Services/Stellar/Matching/BuyerMatchScorer.php');

        $this->assertStringNotContainsString('BridgeResidualMatchFactsReader', $legacy);
        $this->assertStringNotContainsString('CanonicalListingMatchFactsBuilder', $legacy);
        $this->assertStringContainsString('BridgeListingMatchFactsBuilder::build(', $scorer);
    }

    /**
     * The file's code with comments and the namespace declaration removed, plus its
     * string literals (unquoted).
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function code(string $relative): array
    {
        $code     = '';
        $literals = [];

        foreach (token_get_all((string) file_get_contents(self::root() . '/' . $relative)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $literals[] = substr($token[1], 1, -1);
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return [preg_replace('/^namespace\s+[^;]+;/m', '', $code), $literals];
    }

    private static function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
