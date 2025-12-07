<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsCounty extends Model
{
    use HasFactory;

    protected $table = 'us_counties';

    protected $fillable = ['name', 'fips_code', 'state_id'];

    public function state()
    {
        return $this->belongsTo(UsState::class, 'state_id');
    }

    public function cities()
    {
        return $this->hasMany(UsCity::class, 'county_id');
    }

    public static function search($query, $limit = 10)
    {
        return self::with('state')
            ->where('name', 'ILIKE', '%' . $query . '%')
            ->limit($limit)
            ->get();
    }
}
