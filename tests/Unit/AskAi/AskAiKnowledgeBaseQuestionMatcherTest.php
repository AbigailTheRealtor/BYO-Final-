<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFaqConfigService;
use App\Support\AskAi\AskAiKnowledgeBaseQuestionMatcher as M;
use PHPUnit\Framework\TestCase;

/**
 * The matcher's rules, without a container: exact label only, gated by property type,
 * ambiguity refused.
 */
class AskAiKnowledgeBaseQuestionMatcherTest extends TestCase
{
    public function test_an_exact_label_matches_regardless_of_case_punctuation_and_spacing(): void
    {
        $this->assertSame('commercial_restroom_count', M::match('seller', 'How many restrooms are there?', 'Commercial Property'));
        $this->assertSame('commercial_restroom_count', M::match('seller', '  how MANY restrooms   are there ', 'Commercial Property'));
    }

    public function test_a_paraphrase_is_not_matched(): void
    {
        // Paraphrases stay with the existing keyword maps; this class guarantees the floor only.
        $this->assertNull(M::match('seller', 'how many bathrooms does the commercial space have', 'Commercial Property'));
        $this->assertNull(M::match('seller', 'restrooms', 'Commercial Property'));
        $this->assertNull(M::match('seller', '', 'Commercial Property'));
    }

    public function test_a_question_gated_to_another_property_type_is_not_matched(): void
    {
        $this->assertNull(M::match('seller', 'How many restrooms are there?', 'Residential Property'));
    }

    public function test_a_label_shared_by_several_property_types_resolves_only_with_the_type(): void
    {
        // "What location features are nearby?" is income_, commercial_ and land_location_features.
        $this->assertSame('income_location_features', M::match('seller', 'What location features are nearby?', 'Income Property'));
        $this->assertSame('land_location_features', M::match('seller', 'What location features are nearby?', 'Vacant Land'));

        // No type: only universal questions are candidates, and this label is not universal.
        $this->assertNull(M::match('seller', 'What location features are nearby?', null));
    }

    public function test_no_label_names_two_keys_for_one_role_and_type(): void
    {
        // If a config edit ever gives two keys the same label within one gated set, the
        // matcher refuses both. This pins that the shipped config has no such collision.
        $collisions = [];
        $types = [
            'seller'   => ['Residential Property', 'Income Property', 'Commercial Property', 'Business Opportunity', 'Vacant Land'],
            'buyer'    => ['Residential Property', 'Income Property', 'Commercial Property', 'Business Opportunity', 'Vacant Land'],
            'landlord' => ['Residential Property', 'Commercial Property'],
            'tenant'   => ['Residential Property', 'Commercial Property'],
        ];
        foreach ($types as $role => $list) {
            foreach ($list as $type) {
                $byLabel = [];
                foreach (AskAiFaqConfigService::gatedQuestions($role, $type) as $entries) {
                    foreach ($entries as $key => $entry) {
                        $byLabel[M::normalize((string) ($entry['label'] ?? ''))][] = $key;
                    }
                }
                foreach ($byLabel as $label => $keys) {
                    if (count($keys) > 1) {
                        $collisions[] = "{$role} / {$type}: '{$label}' => " . implode(', ', $keys);
                    }
                }
            }
        }

        $this->assertSame([], $collisions, implode("\n", $collisions));
    }

    public function test_an_unknown_role_matches_nothing(): void
    {
        $this->assertNull(M::match('agent', 'How many restrooms are there?', 'Commercial Property'));
    }
}
