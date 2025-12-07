<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsZipCode extends Model
{
    use HasFactory;

    protected $table = 'us_zip_codes';

    protected $fillable = [
        'zip_code',
        'city',
        'state_abbrev',
        'state_name',
        'county',
        'latitude',
        'longitude',
    ];

    public function state()
    {
        return $this->belongsTo(UsState::class, 'state_abbrev', 'abbreviation');
    }

    public static function getZipCodesForCity($cityName, $stateAbbrev = null)
    {
        $query = self::where('city', 'ILIKE', $cityName);
        
        if ($stateAbbrev) {
            $query->where('state_abbrev', strtoupper($stateAbbrev));
        }
        
        return $query->orderBy('zip_code')->pluck('zip_code')->toArray();
    }

    public static function searchZipCodes($input, $limit = 10)
    {
        return self::where('zip_code', 'LIKE', $input . '%')
            ->orderBy('zip_code')
            ->limit($limit)
            ->get();
    }
}
