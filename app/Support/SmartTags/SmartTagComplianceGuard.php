<?php

namespace App\Support\SmartTags;

/**
 * Keeps prohibited concepts out of the Smart Tag taxonomy.
 *
 * Smart Tags describe the PROPERTY. They never describe people, who lives
 * nearby, protected classes, or the perceived quality of a neighbourhood — the
 * same line the Location DNA governance draws ("describe the place, not the
 * people") and that the 55+ Lock-and-Leave remediation enforced.
 *
 * WHY THE PATTERNS LIVE IN CODE, NOT CONFIG. The taxonomy is config. A guard
 * whose patterns sat in the same kind of file could be relaxed by the edit it
 * exists to catch. Changing this list is a code change with its own review.
 *
 * WHAT IS SCANNED: every tag key, label and description, and every description
 * phrase pattern — the text a person reads and the text a parser matches on.
 * Native form OPTION strings are not scanned: they are existing form values
 * (e.g. "Handicap Accessibility") that map TO a compliant key, not tag text.
 *
 * Word boundaries throughout — a substring scan reads "race" in "terrace".
 */
final class SmartTagComplianceGuard
{
    /**
     * @var array<string, string> category => PCRE (no delimiters; matched case-insensitively)
     */
    public const PROHIBITED_PATTERNS = [
        'people_suitability'  => '\b(?:family|families|kid|kids|child|children)[\s_-]*(?:friendly|oriented)\b|\bgreat[\s_-]+for[\s_-]+(?:families|kids|children|retirees|singles|couples)\b|\bperfect[\s_-]+for[\s_-]+(?:families|kids|retirees|singles|couples|students)\b',
        'demographic_groups'  => '\b(?:retirees?|retirement|young[\s_-]+professionals?|empty[\s_-]+nesters?|seniors?|elderly|singles|couples|bachelors?|millennials?|students?|demographics?)\b',
        'age_restriction'     => '\b(?:55|62)[\s_-]*(?:\+|plus)|\bover[\s_-]+(?:55|62)\b|\bage[\s_-]+restricted\b|\bactive[\s_-]+adult\b|\badults?[\s_-]+only\b',
        'neighbourhood_quality' => '\b(?:safe|safety|good|bad|great|best|nice|quiet|desirable|exclusive|prestigious|upscale|affluent|wealthy|low[\s_-]+income|high[\s_-]+income|high[\s_-]+crime|low[\s_-]+crime|up[\s_-]+and[\s_-]+coming)[\s_-]+(?:neighbou?rhoods?|areas?|communit(?:y|ies)|locations?|streets?|part[\s_-]+of[\s_-]+town)\b|\bcrime\b|\btype[\s_-]+of[\s_-]+people\b|\bkind[\s_-]+of[\s_-]+people\b|\bwho[\s_-]+lives\b',
        'schools_steering'    => '\bschool[\s_-]+district\b|\b(?:good|great|top|best|excellent|rated)[\s_-]+schools?\b',
        'religion'            => '\b(?:church|churches|mosque|synagogue|temple|religio\w*|christian|jewish|muslim|catholic|hindu|buddhist)\b',
        'national_origin_race' => '\b(?:race|racial|ethnic\w*|national[\s_-]+origin|nationality|immigrants?|hispanic|latino|latina)\b',
        'disability'          => '\b(?:disabilit(?:y|ies)|disabled|handicap\w*|wheelchair|mentally[\s_-]+ill)\b',
        'sex_familial'        => '\b(?:gender|sexual|lgbt\w*|married|pregnan\w*|familial)\b',
        'source_of_income'    => '\bsection[\s_-]*8\b|\bvouchers?\b|\bwelfare\b|\bsource[\s_-]+of[\s_-]+income\b',
    ];

    /**
     * @return array<string, string> category => matched text, empty when clean
     */
    public static function violations(string $text): array
    {
        $found = [];
        foreach (self::PROHIBITED_PATTERNS as $category => $pattern) {
            if (preg_match('~' . $pattern . '~iu', $text, $m) === 1) {
                $found[$category] = $m[0];
            }
        }

        return $found;
    }

    public static function isClean(string $text): bool
    {
        return self::violations($text) === [];
    }

    /** A tag key is scanned with underscores read as spaces. */
    public static function keyIsClean(string $key): bool
    {
        return self::isClean(str_replace('_', ' ', $key));
    }

    /**
     * @param array<string, mixed> $taxonomy  raw config/smart_tags.php
     * @return string[]
     */
    public static function taxonomyViolations(array $taxonomy): array
    {
        $errors = [];
        foreach ((array) ($taxonomy['tags'] ?? []) as $key => $tag) {
            $texts = [
                'key'         => str_replace('_', ' ', (string) $key),
                'label'       => (string) ($tag['label'] ?? ''),
                'description' => (string) ($tag['description'] ?? ''),
            ];
            foreach ($texts as $field => $text) {
                foreach (self::violations($text) as $category => $match) {
                    $errors[] = "{$key}: prohibited concept ({$category}) in {$field}: \"{$match}\"";
                }
            }
        }

        foreach ((array) ($taxonomy['categories'] ?? []) as $cKey => $category) {
            foreach (self::violations(str_replace('_', ' ', (string) $cKey) . ' ' . (string) ($category['label'] ?? '')) as $cat => $match) {
                $errors[] = "category {$cKey}: prohibited concept ({$cat}): \"{$match}\"";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $sources  raw config/smart_tag_sources.php
     * @return string[]
     */
    public static function phraseViolations(array $sources): array
    {
        $errors = [];
        foreach ((array) ($sources['description']['rules'] ?? []) as $tagKey => $rules) {
            foreach ((array) $rules as $rule) {
                foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                    // Read the pattern as words: strip regex punctuation first.
                    $readable = preg_replace('~\\\\[a-z]|[\\\\()?:|\[\]{}*+^$.]~i', ' ', (string) $pattern) ?? '';
                    foreach (self::violations($readable) as $category => $match) {
                        $errors[] = "{$tagKey}: prohibited concept ({$category}) in description phrase: \"{$match}\"";
                    }
                }
            }
        }

        return $errors;
    }
}
