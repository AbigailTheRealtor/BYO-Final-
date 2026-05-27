<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFaqAnswer extends Model
{
    protected $table = 'ai_faq_answers';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'question_key',
        'question_group',
        'intelligence_category',
        'answer_text',
        'answer_normalized',
    ];

    protected $casts = [
        'answer_normalized' => 'array',
    ];
}
