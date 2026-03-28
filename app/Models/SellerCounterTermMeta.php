<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerCounterTermMeta extends Model
{
    use HasFactory;

    protected $table = 'seller_counter_term_metas';
    protected $guarded = [];

    public function counterTerm()
    {
        return $this->belongsTo(SellerCounterTerm::class, 'counter_term_id');
    }
}
