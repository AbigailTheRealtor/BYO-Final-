<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordCounterTermMeta extends Model
{
    use HasFactory;

    // ensure the correct table name
    protected $table = 'landlord_counter_terms_meta';

    protected $fillable = ['counter_term_id', 'meta_key', 'meta_value'];

    public function counterTerm()
    {
        return $this->belongsTo(LandlordCounterTerm::class, 'counter_term_id', 'id');
    }
}
