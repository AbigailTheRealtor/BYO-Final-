<?php

namespace Tests\Unit\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\ListingMatchFacts;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * P1-A guard — the match engine scores FACTS, and a provider-side builder is the
 * only thing that knows where those facts came from.
 *
 * BuyerMatchScorer and BuyerMatchResultBuilder read {@see ListingMatchFacts};
 * BridgeListingMatchFactsBuilder is the one place a `bridge_properties` row and
 * its `raw_json` become facts. `BuyerMatchScorer::score(BridgeProperty, …)` stays
 * as the Bridge entry point — the live callers and the P1-A0 oracle call it — and
 * this guard pins that it only adapts: build the facts, score the facts.
 *
 * Deliberately narrow. It scans CODE tokens only (comments stripped, because the
 * docblocks explain what used to be read), and it forbids provider storage,
 * provider field names and I/O — not matching-domain helpers such as
 * ImportantPlaceMatcher, MonthlyEquivalent or GreatCircleDistance.
 */
class BuyerMatchScorerInputGuardTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../..';

    private const SCORER         = 'app/Services/Stellar/Matching/BuyerMatchScorer.php';
    private const RESULT_BUILDER = 'app/Services/Stellar/Matching/BuyerMatchResultBuilder.php';
    private const FACTS          = 'app/Services/Stellar/Matching/ListingMatchFacts.php';
    private const SCORE          = 'app/Services/Stellar/Matching/ListingMatchScore.php';
    private const BRIDGE_BUILDER = 'app/Services/Bridge/BridgeListingMatchFactsBuilder.php';

    /**
     * Provider storage, provider field names and I/O the match rules must not touch.
     * Field names are matched as quoted literals so identifiers such as
     * scoreLeaseTermPreference() are not mistaken for a read.
     */
    private const FORBIDDEN_IN_RULES = [
        'raw_json', 'json_decode', 'STELLAR_',
        "'LeaseTerm'", "'LeaseAmountFrequency'", "'AssociationFeeFrequency'", "'BuildingAreaTotal'",
        "'CommunityFeatures'", "'AssociationAmenities'", "'GreenEnergyEfficient'",
        "'GreenBuildingVerificationType'", "'DaysOnMarket'", "'ElementarySchool'", "'HighSchool'",
        'ListingPeriodFacts::leaseFrequency', 'ListingPeriodFacts::associationFeeFrequency',
        'DB::', 'Http::', 'Cache::', 'Illuminate\\Support\\Facades', 'GuzzleHttp',
        '::query(', '::where(', '::find(', '::forNativeKey(',
        'BridgeApiService', 'LazyBridgeImportService', 'BridgeListingLookupService', 'BridgePropertyNormalizer',
    ];

    public function test_the_scorer_reads_no_provider_storage_field_or_service(): void
    {
        $this->assertSame([], $this->violations(self::SCORER, $this->code(self::SCORER)));
    }

    public function test_the_result_builder_reads_no_provider_storage_field_or_service(): void
    {
        $this->assertSame([], $this->violations(self::RESULT_BUILDER, $this->code(self::RESULT_BUILDER)));
    }

    public function test_no_scoring_rule_reads_a_bridge_row(): void
    {
        $scorer = $this->code(self::SCORER);

        // The row is never read field by field: its only uses are the entry point's
        // hand-off to the facts builder and carrying it onto the result.
        $this->assertDoesNotMatchRegularExpression('/\$listing\s*->/', $scorer);

        foreach ((new \ReflectionClass(BuyerMatchScorer::class))->getMethods() as $method) {
            if (in_array($method->getName(), ['score', 'scoreAll'], true)) {
                continue;
            }
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                $this->assertFalse(
                    $type instanceof ReflectionNamedType && $type->getName() === BridgeProperty::class,
                    "BuyerMatchScorer::{$method->getName()}() takes a BridgeProperty — scoring rules read ListingMatchFacts"
                );
            }
        }
    }

    public function test_every_category_rule_takes_listing_match_facts(): void
    {
        $rules = [
            'scoreFacts', 'scoreLocation', 'scorePrice', 'scoreSize', 'scoreYearBuilt', 'scorePropertyType',
            'scoreAmenities', 'scoreFinancial', 'scoreLifestyle', 'scoreNonResidential',
            'scoreIncomeProperty', 'scoreCommercialSale', 'scoreBusinessOpportunity', 'scoreVacantLand',
        ];

        foreach ($rules as $rule) {
            $first = (new ReflectionMethod(BuyerMatchScorer::class, $rule))->getParameters()[0]->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $first, "{$rule}()");
            $this->assertSame(ListingMatchFacts::class, $first->getName(), "{$rule}() must take ListingMatchFacts");
        }
    }

    public function test_the_bridge_entry_point_only_adapts(): void
    {
        $body = $this->methodBody(BuyerMatchScorer::class, 'score');

        $this->assertStringContainsString('BridgeListingMatchFactsBuilder::build($listing)', $body);
        $this->assertStringContainsString('$this->scoreFacts($facts, $criteria)', $body);
        $this->assertSame(
            2,
            substr_count($body, '$listing'),
            'score() may hand $listing to the facts builder and carry it onto the result — nothing else'
        );
    }

    public function test_the_result_builder_derives_facts_only_through_the_provider_boundary(): void
    {
        $code = $this->code(self::RESULT_BUILDER);

        $this->assertDoesNotMatchRegularExpression('/\$listing\s*->/', $code);
        $this->assertSame(1, substr_count($code, '->listing'), 'only factsFor() may touch the result\'s row');
        $this->assertStringContainsString('BridgeListingMatchFactsBuilder::build($result->listing)', $code);
        $this->assertStringNotContainsString('BridgeProperty', $code);
    }

    public function test_the_facts_and_score_objects_are_provider_neutral(): void
    {
        foreach ([self::FACTS, self::SCORE] as $file) {
            $code = $this->code($file);
            foreach (['Bridge', 'App\\Models', 'Illuminate', 'raw_json', 'STELLAR', 'Canonical', 'PropertyCandidate'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $code, "{$file} must not depend on {$forbidden}");
            }
        }
    }

    public function test_the_bridge_facts_builder_decodes_once_and_touches_no_storage(): void
    {
        $code = $this->code(self::BRIDGE_BUILDER);

        $this->assertSame(1, substr_count($code, 'json_decode('), 'raw_json is decoded once per listing');
        foreach (['DB::', 'Http::', 'Cache::', '::query(', '::where(', '->save(', 'dispatch(', 'BridgeApiService'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "the facts builder must not use {$forbidden}");
        }
    }

    public function test_the_guard_detects_planted_violations(): void
    {
        $planted = '<?php $d = json_decode($listing->raw_json, true); $t = $d[\'LeaseTerm\'] ?? null; '
            . '$z = $d[\'STELLAR_FloodZoneCode\'] ?? null; DB::table("bridge_properties")->get();';

        $violations = $this->violations('Planted.php', $planted);

        foreach (['raw_json', 'json_decode', 'STELLAR_', "'LeaseTerm'", 'DB::'] as $expected) {
            $this->assertContains("Planted.php uses {$expected}", $violations);
        }
    }

    /** @return list<string> */
    private function violations(string $file, string $code): array
    {
        $out = [];
        foreach (self::FORBIDDEN_IN_RULES as $forbidden) {
            if (str_contains($code, $forbidden)) {
                $out[] = "{$file} uses {$forbidden}";
            }
        }

        return $out;
    }

    /** A file's code with comments and docblocks stripped. */
    private function code(string $relative): string
    {
        $code = '';
        foreach (token_get_all((string) file_get_contents(self::ROOT . '/' . $relative)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** One method's source lines, comments included (the body is short and comment-free). */
    private function methodBody(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines      = file((string) $reflection->getFileName());

        return implode('', array_slice($lines, $reflection->getStartLine(), $reflection->getEndLine() - $reflection->getStartLine()));
    }
}
