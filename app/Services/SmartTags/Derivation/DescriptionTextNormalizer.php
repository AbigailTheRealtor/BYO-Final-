<?php

namespace App\Services\SmartTags\Derivation;

/**
 * Normalises listing prose before phrase matching and hashing.
 *
 * Strips markup, decodes entities, applies Unicode compatibility folding when
 * intl is available, reads curly quotes and dashes as ASCII, lowercases,
 * collapses whitespace and caps length. The same text therefore always hashes
 * the same, so cosmetic edits do not trigger a reparse.
 */
final class DescriptionTextNormalizer
{
    public const DEFAULT_MAX_LENGTH = 12000;

    public static function normalize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $folded = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($folded)) {
                $text = $folded;
            }
        }

        $text = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{2032}", "\u{201C}", "\u{201D}", "\u{201E}", "\u{2033}",
             "\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{00A0}", "\u{2022}"],
            ["'", "'", "'", "'", "'", '"', '"', '"', '"',
             '-', '-', '-', '-', '-', '-', ' ', ' . '],
            $text,
        );

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[ \t\r\f\v]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;
        $text = trim($text);

        $max = (int) SmartTagSourceRules::descriptionSetting('max_length', self::DEFAULT_MAX_LENGTH);
        if ($max > 0 && mb_strlen($text, 'UTF-8') > $max) {
            $text = mb_substr($text, 0, $max, 'UTF-8');
        }

        return $text;
    }
}
