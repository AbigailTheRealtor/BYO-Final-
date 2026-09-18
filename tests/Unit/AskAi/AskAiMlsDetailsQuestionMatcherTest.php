<?php

namespace Tests\Unit\AskAi;

use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Support\AskAi\AskAiMlsDetailsQuestionMatcher as M;
use PHPUnit\Framework\TestCase;

/** The matcher's rules on a hand-built details blob, without a container. */
class AskAiMlsDetailsQuestionMatcherTest extends TestCase
{
    private function details(): MlsSupplementalDetails
    {
        return MlsSupplementalDetails::fromStored([
            'version'  => 1,
            'sections' => [
                ['title' => 'Property Details', 'group' => 'facts', 'rows' => [
                    ['key' => 'GarageSpaces', 'label' => 'Garage Spaces', 'value' => '2'],
                    ['key' => 'SubdivisionName', 'label' => 'Subdivision', 'value' => 'Oak Ridge'],
                    ['key' => 'A', 'label' => 'Stories', 'value' => '1'],
                    ['key' => 'B', 'label' => 'Stories', 'value' => '2'],
                ]],
                ['title' => 'Listing Agent', 'group' => 'contacts', 'rows' => [
                    ['key' => 'ListAgentFullName', 'label' => 'Agent Name', 'value' => 'SENTINEL-AGENT'],
                ]],
            ],
        ]);
    }

    public function test_a_label_matches_bare_or_after_a_what_is_lead_in(): void
    {
        $expected = ['label' => 'Garage Spaces', 'value' => '2'];

        $this->assertSame($expected, M::match($this->details(), 'Garage Spaces'));
        $this->assertSame($expected, M::match($this->details(), 'what are the garage spaces?'));
        $this->assertSame(['label' => 'Subdivision', 'value' => 'Oak Ridge'], M::match($this->details(), 'What is the subdivision'));
    }

    public function test_a_paraphrase_is_not_matched(): void
    {
        $this->assertNull(M::match($this->details(), 'how many cars fit in the garage'));
        $this->assertNull(M::match($this->details(), ''));
    }

    public function test_only_the_facts_group_is_read(): void
    {
        $this->assertNull(M::match($this->details(), 'Agent Name'));
    }

    public function test_a_label_naming_two_different_values_is_refused(): void
    {
        $this->assertNull(M::match($this->details(), 'Stories'));
    }

    public function test_an_empty_blob_matches_nothing(): void
    {
        $this->assertNull(M::match(MlsSupplementalDetails::empty(), 'Garage Spaces'));
    }
}
