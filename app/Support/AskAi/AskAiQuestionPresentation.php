<?php

namespace App\Support\AskAi;

/**
 * AskAiQuestionPresentation — how a listing's ANSWERABLE Ask AI questions are shown.
 *
 * Ask AI is selection-based: a viewer picks one of the verified questions this listing can
 * answer and reads its precomputed answer. This class takes the set the question service
 * already built (AskAiPublicPropertyQuestionService — visibility, property type,
 * ConditionalTerms and Fair Housing are all decided THERE) and presents it:
 *
 *   featured  a small subset, in config order, for the listing page and the top of the modal;
 *   all       every answerable question, for "View all questions" and search.
 *
 * It only orders, subsets and rewords. It cannot add a question, change an answer, or admit
 * one the service withheld: every output row is an input row, and `featured` is a subset of
 * `all` by construction. Leaving a question out of `featured` removes nothing from Ask AI.
 *
 * Display wording is cosmetic. A generic "What does the listing state for Total Sq Ft?" may
 * read "What is the total square footage?", but the id and answer are the input's, and the
 * original wording and every alias stay in the row's search terms, so search still finds it.
 *
 * config/ask_ai_question_presentation.php is read only here. Pure apart from that read.
 */
final class AskAiQuestionPresentation
{
    private const GENERIC = "/^What does the (?:listing|(buyer|tenant)'s listing) state for (.+)\\?$/u";

    /**
     * @param  string                   $role       'seller' | 'landlord' | 'buyer' | 'tenant'
     * @param  list<array<string,mixed>> $questions the service's rows ({id, question, answer, aliases, …})
     * @param  array<string,mixed>      $meta       the listing's decoded meta (property type only)
     * @return array{featured: list<array>, all: list<array>}
     */
    public static function build(string $role, array $questions, array $meta = []): array
    {
        $conf = self::conf();
        $all  = [];
        foreach ($questions as $q) {
            if (!is_array($q) || !is_string($q['id'] ?? null) || !is_string($q['question'] ?? null)) {
                continue;
            }
            $all[] = self::row($role, $q, $conf);
        }

        return ['featured' => self::featured($role, $all, $meta, $conf), 'all' => $all];
    }

    /** The consumer wording for one question (the original when no rewording is declared). */
    public static function display(string $role, string $question): string
    {
        return self::displayWith($role, $question, self::conf());
    }

    private static function row(string $role, array $q, array $conf): array
    {
        $display = self::displayWith($role, $q['question'], $conf);
        $terms   = array_values(array_unique(array_filter(array_merge(
            [$display, $q['question']],
            array_map('strval', (array) ($q['aliases'] ?? [])),
            array_map('strval', (array) ($conf['search_keywords'][$q['id']] ?? []))
        ), static fn ($t) => trim($t) !== '')));

        return $q + ['display' => $display, 'search_terms' => $terms];
    }

    private static function displayWith(string $role, string $question, array $conf): string
    {
        if (preg_match(self::GENERIC, $question, $m) !== 1) {
            return $question;
        }
        $party = $m[1] ?? '';
        $label = $m[2];
        if ($party !== '') {
            $template = $conf['criteria_display'][$label] ?? null;

            return is_string($template) ? str_replace('{party}', $party, $template) : $question;
        }
        $friendly = $conf['property_display'][$label] ?? null;

        return is_string($friendly) ? $friendly : $question;
    }

    /** @param list<array> $all */
    private static function featured(string $role, array $all, array $meta, array $conf): array
    {
        $max = max(1, (int) ($conf['featured_max'] ?? 6));
        $min = min($max, max(0, (int) ($conf['featured_min'] ?? 4)));

        $byId = [];
        foreach ($all as $row) {
            $byId[$row['id']] = $row;
        }

        $lists  = (array) ($conf['featured'][$role] ?? []);
        $tokens = AskAiPropertyTypeResolver::forListing($role, $meta);
        $order  = [];
        foreach ($tokens as $token) {
            foreach ((array) ($lists[$token] ?? []) as $id) {
                $order[] = $id;
            }
        }
        if ($order === []) {
            $order = (array) ($lists['default'] ?? []);
        }

        $out = [];
        foreach (array_unique($order) as $id) {
            if (isset($byId[$id]) && count($out) < $max) {
                $out[$id] = $byId[$id];
            }
        }
        // Top up from the answerable set in its own order — curated questions come first
        // there — never from outside it.
        foreach ($all as $row) {
            if (count($out) >= $min) {
                break;
            }
            $out[$row['id']] ??= $row;
        }

        return array_values($out);
    }

    private static function conf(): array
    {
        if (function_exists('app') && app()->bound('config')) {
            return (array) config('ask_ai_question_presentation', []);
        }

        return (array) require __DIR__ . '/../../../config/ask_ai_question_presentation.php';
    }
}
