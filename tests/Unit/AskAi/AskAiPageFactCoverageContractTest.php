<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService as C;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService as S;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility as V;
use App\Support\AskAi\AskAiPageFactDisposition as D;
use Tests\Support\AskAi\PageFactCoverageProbe;
use Tests\TestCase;

/**
 * The PAGE → Ask AI coverage contract (universal coverage audit, 2026-09-24).
 *
 * Every meta key a public listing view reads either feeds something Ask AI may publish — a
 * public / allowlisted context field, a criteria page-meta source, or a curated question's
 * declared companion — or is named in AskAiPageFactDisposition with a category and a reason.
 * SILENCE FAILS: a row added to a public page with neither fails here, naming the key.
 *
 * Static and fast on purpose: it reads the Blade source and the authorities. Whether a public
 * fact is actually REACHED by deterministic wording is proven by
 * AskAiPublicPageVisibilityParityTest (rendered pages) and AskAiUniversalCoverageMatrixTest.
 */
class AskAiPageFactCoverageContractTest extends TestCase
{
    private const ROLES = ['seller', 'landlord', 'buyer', 'tenant'];

    public function test_every_page_key_is_answerable_or_dispositioned(): void
    {
        $missing = [];
        foreach (self::ROLES as $role) {
            $answered    = $this->answeredKeys($role);
            $disposition = D::forRole($role);
            foreach (PageFactCoverageProbe::viewKeys($role) as $key) {
                if (!isset($answered[$key]) && !isset($disposition[$key])) {
                    $missing[] = "{$role}.{$key}";
                }
            }
        }

        $this->assertSame([], $missing, "Public page keys Ask AI neither answers nor dispositions — add a public field / criteria source, or an AskAiPageFactDisposition entry with a reason:\n" . implode("\n", $missing));
    }

    public function test_no_disposition_names_a_key_the_page_does_not_read(): void
    {
        $stale = [];
        foreach (self::ROLES as $role) {
            $view = array_flip(PageFactCoverageProbe::viewKeys($role));
            foreach (array_keys(D::forRole($role)) as $key) {
                if (!isset($view[$key])) {
                    $stale[] = "{$role}.{$key}";
                }
            }
        }

        $this->assertSame([], $stale, "Dispositions for keys the page no longer reads:\n" . implode("\n", $stale));
    }

    public function test_no_key_is_both_answered_and_dispositioned(): void
    {
        $both = [];
        foreach (self::ROLES as $role) {
            $answered = $this->answeredKeys($role);
            foreach (array_keys(D::forRole($role)) as $key) {
                if (isset($answered[$key])) {
                    $both[] = "{$role}.{$key} (answered via {$answered[$key]})";
                }
            }
        }

        $this->assertSame([], $both, implode("\n", $both));
    }

    public function test_private_and_prohibited_keys_never_feed_a_public_source(): void
    {
        foreach (self::ROLES as $role) {
            $answered = $this->answeredKeys($role);
            foreach (D::forRole($role) as $key => $d) {
                if (in_array($d['category'], [D::PRIVATE, D::PROHIBITED], true)) {
                    $this->assertArrayNotHasKey($key, $answered, "{$role}.{$key} is {$d['category']} but feeds a public Ask AI source.");
                }
            }
        }
    }

    public function test_every_disposition_has_a_known_category_and_a_reason(): void
    {
        foreach (D::groups() as $category => $reasons) {
            $this->assertContains($category, D::categories());
            foreach ($reasons as $reason => $roles) {
                $this->assertGreaterThan(40, mb_strlen($reason), "A {$category} reason must say why.");
                foreach (array_keys($roles) as $role) {
                    $this->assertContains($role, self::ROLES);
                }
            }
        }
    }

    public function test_there_are_no_unresolved_coverage_gaps(): void
    {
        $gaps = [];
        foreach (self::ROLES as $role) {
            foreach (D::forRole($role) as $key => $d) {
                if ($d['category'] === D::COVERAGE_GAP) {
                    $gaps[] = "{$role}.{$key}";
                }
            }
        }

        $this->assertSame([], $gaps, "Every page fact is answered or classified — a COVERAGE_GAP may not be parked here:\n" . implode("\n", $gaps));
    }

    /** @return array<string, string> meta key => what publishes it */
    private function answeredKeys(string $role): array
    {
        $publishable = array_fill_keys(
            in_array($role, ['buyer', 'tenant'], true) ? S::publicCriteriaKeys($role) : V::publicKeysForRole($role),
            true
        );
        $out = [];
        foreach (C::CANONICAL_SOURCE_MAP[$role] ?? [] as $field => $sources) {
            if (!isset($publishable[$field])) {
                continue;
            }
            foreach ((array) $sources as $source) {
                if (is_string($source) && !str_starts_with($source, 'native:') && !str_starts_with($source, 'synthetic:')) {
                    $out[$source] ??= "listing.{$field}";
                }
            }
        }
        foreach (S::publicCriteriaMetaSources()[$role] ?? [] as $name => $spec) {
            foreach ((array) ($spec['keys'] ?? []) as $key) {
                $out[$key] ??= "criteria_meta.{$name}";
            }
        }
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            if (($entry['role'] ?? null) !== $role) {
                continue;
            }
            array_walk_recursive($entry, static function ($v, $k) use (&$out, $id): void {
                if (in_array($k, ['meta_key', 'selected_in'], true) && is_string($v)) {
                    $out[$v] ??= "companion of {$id}";
                }
            });
        }

        return $out;
    }
}
