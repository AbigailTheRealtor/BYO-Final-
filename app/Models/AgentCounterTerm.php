<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentCounterTerm extends Model
{
    use HasFactory;
    protected $table = 'agent_counter_terms';
    protected $guarded  = [];
}
