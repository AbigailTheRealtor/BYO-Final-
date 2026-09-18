<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;

/**
 * AskAiMlsDetailsQuestionMatcher — the owner can ask about any MLS Details fact on their
 * imported listing, deterministically.
 *
 * WHY THIS EXISTS
 * ---------------
 * MLS quick import stores Tier-2 facts — legitimate property facts with no editable
 * equivalent (garage spaces, cap rate, subdivision, room counts, ...) — in the supplemental
 * `mls_property_details` blob, and the listing page renders them under "MLS Details". Ask AI
 * never read that blob: an owner could see a fact on their own page and get no answer about it.
 *
 * WHAT IT READS, AND WHAT IT DOES NOT
 * -----------------------------------
 * Only the blob's `facts` group — the rows MlsPropertyDetailsPresenter already produced
 * through MlsFieldCatalog's fail-closed display allow-lists. Never `contacts`, `related` or
 * `listing` (people, brokerages, open houses, the MLS's own bookkeeping), and never the raw
 * feed: only rows that were already cleared for display. Values are answered as stored.
 *
 * OWNER ONLY, BY THE CALLER. Publishing Tier-2 rows to shoppers through Ask AI is a display
 * decision (CLAUDE.md: adding a field to a display allow-list is a licensing decision), so the
 * runner uses this for the owner scope alone; the owner imported this record themselves.
 *
 * EXACT LABEL, NOT FUZZY — the same rule as AskAiKnowledgeBaseQuestionMatcher. A question
 * matches a row when, after normalisation and an optional "what is/are (the)" lead-in, it is
 * the row's label. A label shared by two rows with different values is refused.
 */
final class AskAiMlsDetailsQuestionMatcher
{
    private const LEAD_INS = ['what is the ', 'what are the ', 'what is ', 'what are '];

    /** @return array{label: string, value: string}|null */
    public static function forListing(string $listingType, int $listingId, string $question): ?array
    {
        if (!in_array(AskAiContextBuilderService::canonicalListingType($listingType), ['seller', 'landlord'], true)) {
            return null;
        }

        try {
            $stored = app(AskAiContextBuilderService::class)
                ->listingMeta($listingType, $listingId, MlsQuickImportDraftWriter::META_PROPERTY_DETAILS);
        } catch (\Throwable) {
            return null;
        }

        return self::match(MlsSupplementalDetails::fromStored($stored), $question);
    }

    /** @return array{label: string, value: string}|null */
    public static function match(MlsSupplementalDetails $details, string $question): ?array
    {
        $needle = AskAiKnowledgeBaseQuestionMatcher::normalize($question);
        if ($needle === '') {
            return null;
        }
        foreach (self::LEAD_INS as $lead) {
            if (str_starts_with($needle . ' ', $lead)) {
                $needle = trim(substr($needle, strlen($lead)));
                break;
            }
        }

        $found = [];
        foreach ($details->group('facts') as $section) {
            foreach ((array) ($section['rows'] ?? []) as $row) {
                $label = (string) ($row['label'] ?? '');
                $value = trim((string) ($row['value'] ?? ''));
                if ($value !== '' && AskAiKnowledgeBaseQuestionMatcher::normalize($label) === $needle) {
                    $found[$value] = ['label' => $label, 'value' => $value];
                }
            }
        }

        return count($found) === 1 ? array_values($found)[0] : null;
    }
}
