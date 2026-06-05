<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'offer_auction_id'     => 'required|integer|exists:offer_auctions,id',
            'requested_date'       => 'required|date|after_or_equal:today',
            'requested_start_time' => 'required',
            'requested_end_time'   => 'required|after:requested_start_time',
            'requester_message'    => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'offer_auction_id.exists'          => 'The selected listing was not found.',
            'requested_date.after_or_equal'    => 'The showing date must be today or a future date.',
            'requested_end_time.after'         => 'The end time must be after the start time.',
        ];
    }
}
