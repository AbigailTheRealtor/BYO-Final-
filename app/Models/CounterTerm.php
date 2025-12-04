<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterTerm extends Model
{
    use HasFactory;
    protected $table = 'counter_terms';
    protected $guarded  = [];
    public function buyerAuction()
    {
        return $this->belongsTo(BuyerAgentAuction::class);
    }
}
