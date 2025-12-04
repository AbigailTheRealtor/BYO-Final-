<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantCounterTermMeta extends Model
{
    use HasFactory;

    // ensure the correct table name
    protected $table = 'tenant_counter_terms_meta';

    protected $fillable = ['counter_term_id', 'meta_key', 'meta_value'];

    public function counterTerm()
    {
        return $this->belongsTo(TenantCounterTerm::class, 'counter_term_id', 'id');
    }
}
