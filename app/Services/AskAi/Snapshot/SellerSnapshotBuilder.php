<?php

namespace App\Services\AskAi\Snapshot;

use App\Models\AskAiKnowledgeSnapshot;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;

class SellerSnapshotBuilder
{
    public function __construct(
        private AskAiContextBuilderService $contextBuilder
    ) {}

    public function build(AskAiKnowledgeSnapshot $snapshot, int $listingId): void
    {
        $context = $this->contextBuilder->buildForListing('seller', $listingId);

        $listing    = $context['listing']    ?? [];
        $faqAnswers = $context['faq_answers'] ?? [];

        $this->persistFacts($snapshot, $listing);
        $this->persistQuestions($snapshot, 'seller');
        $this->persistAnswers($snapshot, $faqAnswers);
    }

    private function persistFacts(AskAiKnowledgeSnapshot $snapshot, array $listing): void
    {
        $sortOrder = 0;
        foreach ($listing as $key => $value) {
            if ($key === null || $key === '') {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }

            $encoded    = is_array($value) ? json_encode($value) : (string) $value;
            $visibility = SnapshotFactVisibility::classify($key);

            $snapshot->facts()->create([
                'canonical_key'  => $key,
                'value'          => $encoded,
                'visibility'     => $visibility,
                'listing_type'   => $snapshot->listing_type,
                'listing_id'     => $snapshot->listing_id,
                'label'          => SnapshotFactVisibility::deriveLabel($key),
                'value_type'     => SnapshotFactVisibility::detectValueType($value),
                'source_path'    => 'context.listing.' . $key,
                'classification' => $visibility === 'restricted' ? 'compliance_sensitive' : 'public_factual',
                'public_allowed' => $visibility === 'public_allowed',
                'restricted'     => $visibility === 'restricted',
                'sort_order'     => $sortOrder++,
            ]);
        }
    }

    private function persistQuestions(AskAiKnowledgeSnapshot $snapshot, string $role): void
    {
        $registry  = AskAiFieldQuestionRegistryService::registry();
        $sortOrder = 0;

        foreach ($registry as $path => $entry) {
            $roles = $entry['roles'] ?? [];
            if (!in_array($role, $roles, true)) {
                continue;
            }

            $configKey = $entry['config_key'] ?? null;
            if ($configKey === null || $configKey === '') {
                continue;
            }

            $snapshot->questions()->create([
                'canonical_key'        => $path,
                'field_type'           => $entry['field_type'] ?? 'faq',
                'keyword_route_status' => $entry['keyword_route_status'] ?? null,
                'label'                => $entry['label'] ?? null,
                'sample_question'      => $entry['sample_question'] ?? null,
                'sample_question_2'    => $entry['sample_question_2'] ?? null,
                'question_text'        => $entry['sample_question'] ?? null,
                'question_type'        => $entry['field_type'] ?? 'faq',
                'source_path'          => 'registry.faq.' . $path,
                'sort_order'           => $sortOrder++,
            ]);
        }

        $listingRegistry = AskAiFieldQuestionRegistryService::listingFieldRegistry();
        foreach ($listingRegistry as $path => $entry) {
            $roles = $entry['roles'] ?? [];
            if (!in_array($role, $roles, true)) {
                continue;
            }

            $configKey = $entry['config_key'] ?? null;
            if ($configKey === null || $configKey === '') {
                continue;
            }

            $snapshot->questions()->create([
                'canonical_key'        => $path,
                'field_type'           => $entry['field_type'] ?? 'listing_model',
                'keyword_route_status' => $entry['keyword_route_status'] ?? null,
                'label'                => $entry['label'] ?? null,
                'sample_question'      => $entry['sample_question'] ?? null,
                'sample_question_2'    => $entry['sample_question_2'] ?? null,
                'question_text'        => $entry['sample_question'] ?? null,
                'question_type'        => $entry['field_type'] ?? 'listing_model',
                'source_path'          => 'registry.listing.' . $path,
                'sort_order'           => $sortOrder++,
            ]);
        }
    }

    private function persistAnswers(AskAiKnowledgeSnapshot $snapshot, array $faqAnswers): void
    {
        $sortOrder = 0;
        foreach ($faqAnswers as $key => $answer) {
            if ($key === null || $key === '') {
                continue;
            }
            if ($answer === null || $answer === '') {
                continue;
            }

            // Normalize: the context builder may return enriched arrays
            // (e.g. ['config_key' => ..., 'answer_text' => ...]). Extract
            // the answer_text scalar; fall back to json_encode only if
            // answer_text is absent so the stored value is always readable.
            $answerText = is_array($answer)
                ? ($answer['answer_text'] ?? json_encode($answer))
                : (string) $answer;

            // Link to the matching question row when one was persisted for this key.
            $questionId = $snapshot->questions()
                ->where('canonical_key', $key)
                ->value('id');

            $snapshot->answers()->create([
                'canonical_key'  => $key,
                'answer_text'    => $answerText,
                'question_id'    => $questionId,
                'classification' => 'faq_answer',
                'visibility'     => 'public_allowed',
                'source_path'    => 'context.faq_answers.' . $key,
                'sort_order'     => $sortOrder++,
            ]);
        }
    }
}
