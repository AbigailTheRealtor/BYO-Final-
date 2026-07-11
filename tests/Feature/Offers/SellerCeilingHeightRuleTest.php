<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #4 — ceiling_height is a single-value <select> bound to a string prop, but the shared
 * SellerPublishValidation rule validated it as `nullable|array` (+ ceiling_height.*).
 * That made every Commercial Seller listing that picked a ceiling height fail submit.
 * The rule is now `nullable|string|in:<options>` to match the actual single-string UI.
 */
class SellerCeilingHeightRuleTest extends TestCase
{
    private function rules(): array
    {
        $m = new ReflectionMethod(SellerOfferListing::class, 'getConditionalRules');
        $m->setAccessible(true);
        return $m->invoke(new SellerOfferListing());
    }

    public function test_ceiling_height_rule_is_a_single_string_not_an_array(): void
    {
        $rules = $this->rules();

        $this->assertArrayHasKey('ceiling_height', $rules);
        $this->assertStringContainsString('string', $rules['ceiling_height']);
        $this->assertStringNotContainsString('array', $rules['ceiling_height']);
        // The per-element rule must no longer exist (it only made sense for a multiselect).
        $this->assertArrayNotHasKey('ceiling_height.*', $rules);
    }

    public function test_commercial_single_ceiling_height_value_passes(): void
    {
        $rule = ['ceiling_height' => $this->rules()['ceiling_height']];

        // A real single-select value now validates (previously rejected by nullable|array).
        $this->assertTrue(Validator::make(['ceiling_height' => '11-14 Feet'], $rule)->passes());
        // Empty stays allowed (nullable).
        $this->assertTrue(Validator::make(['ceiling_height' => ''], $rule)->passes());
        // Out-of-list value is still rejected.
        $this->assertTrue(Validator::make(['ceiling_height' => 'Bogus Height'], $rule)->fails());
    }
}
