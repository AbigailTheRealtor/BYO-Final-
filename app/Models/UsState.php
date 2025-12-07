<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsState extends Model
{
    use HasFactory;

    protected $table = 'us_states';

    protected $fillable = ['name', 'abbreviation', 'fips_code'];

    public function counties()
    {
        return $this->hasMany(UsCounty::class, 'state_id');
    }

    public function cities()
    {
        return $this->hasMany(UsCity::class, 'state_id');
    }

    public static function search($query, $limit = 10)
    {
        return self::where('name', 'ILIKE', '%' . $query . '%')
            ->orWhere('abbreviation', 'ILIKE', '%' . $query . '%')
            ->limit($limit)
            ->get();
    }
}
