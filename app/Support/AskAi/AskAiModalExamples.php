<?php

namespace App\Support\AskAi;

/**
 * The example questions the free-text Ask AI modal suggests.
 *
 * The owner keeps each page's own examples. Everyone else is shown only questions this
 * listing can answer for them: the questions on its public Ask AI card, which
 * AskAiPublicPropertyQuestionService built from facts a non-owner may see. A generic list
 * ("Is this buyer pre-approved?", "School districts nearby?") would advertise answers a
 * shopper is then refused, or that the listing never had.
 */
final class AskAiModalExamples
{
    private const LIMIT = 4;

    /**
     * @param  array<int, string>                      $ownerExamples   the page's own examples
     * @param  array<int, array<string, mixed>>|mixed  $publicQuestions the card's {question, answer, ...} rows
     * @return array<int, string>
     */
    public static function forViewer(bool $viewerIsOwner, array $ownerExamples, $publicQuestions): array
    {
        if ($viewerIsOwner) {
            return array_values($ownerExamples);
        }

        $out = [];
        foreach (is_array($publicQuestions) ? $publicQuestions : [] as $row) {
            $question = is_array($row) ? trim((string) ($row['question'] ?? '')) : '';
            if ($question !== '' && ! in_array($question, $out, true)) {
                $out[] = $question;
            }
            if (count($out) >= self::LIMIT) {
                break;
            }
        }

        return $out;
    }
}
