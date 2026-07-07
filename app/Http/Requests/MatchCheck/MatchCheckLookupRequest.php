<?php

namespace App\Http\Requests\MatchCheck;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a Match Check consumer lookup submission (MLS Direct Import · Phase 4 · git-C14).
 *
 * v1 exposes exactly two identifier modes (owner decision §7.2): MLS # and free-text
 * address. RESO ListingKey is intentionally NOT a UI field this slice — the orchestrator's
 * analyzeByListingKey() entry exists but is not surfaced. The two rules mirror the two
 * MatchCheckOrchestrator entry points the controller dispatches to.
 */
class MatchCheckLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already stacks the `auth` middleware; this is belt-and-braces.
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'mode'       => 'required|string|in:mls,address',
            'mls_number' => 'required_if:mode,mls|nullable|string|max:64',
            'address'    => 'required_if:mode,address|nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in'                => 'Choose whether to look up by MLS # or by address.',
            'mls_number.required_if' => 'Enter the MLS # to check.',
            'address.required_if'    => 'Enter the property address to check.',
        ];
    }
}
