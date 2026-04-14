<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcknowledgementDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'accepted_bid_summary_id',
        'user_id',
        'selected_agent_user_id',
        'id_document_path',
        'proof_of_funds_path',
        'pre_approval_letter_path',
        'proof_of_income_path',
        'property_record_link',
    ];

    public function summary()
    {
        return $this->belongsTo(AcceptedBidSummary::class, 'accepted_bid_summary_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'selected_agent_user_id');
    }
}
