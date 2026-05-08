<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsZipCodeCity extends Model
{
    use HasFactory;

    protected $table = 'us_zip_code_cities';

    protected $fillable = [
        'zip_code',
        'city',
        'state_abbrev',
        'county',
    ];

    public function primaryZip()
    {
        return $this->belongsTo(UsZipCode::class, 'zip_code', 'zip_code');
    }
}
