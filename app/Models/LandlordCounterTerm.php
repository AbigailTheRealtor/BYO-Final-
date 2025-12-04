<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordCounterTerm extends Model
{
    use HasFactory;
        protected $appends = ["get"];

    protected $table = 'landlord_counter_terms';
    protected $guarded  = [];


    
    /**
     * Relationship with user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with auction
     */
    public function auction()
    {
        return $this->belongsTo(LandlordAuction::class, 'landlord_auction_id', 'id')->withDefault();
    }

    /**
     * Relationship with meta data
     *   
     */
    public function meta()
    {
        // tell Eloquent exactly which FK and local key to use
        return $this->hasMany(LandlordCounterTermMeta::class, 'counter_term_id', 'id');
    }


    /**
     * Save meta data
     */
    // App\Models\TenantCounterTerm.php
    public function saveMeta($key, $value)
    {
        return $this->meta()->updateOrCreate(
            ['counter_term_id' => $this->id, 'meta_key' => $key], // match
            ['meta_value' => $value]                              // values
        );
    }


    /**
     * Get meta value
     */
    public function getMeta($key, $default = null)
    {
        $meta = $this->meta()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Get all meta as array
     */
    public function getAllMeta()
    {
        return $this->meta->pluck('meta_value', 'meta_key')->toArray();
    }


        public function getGetAttribute()
    {
        $data = [];
        $metas = LandlordCounterTermMeta::where('counter_term_id', $this->id)->get();
        foreach ($metas as $row) {
            if (gettype(json_decode($row->meta_value)) == 'array') {
                $value = json_decode($row->meta_value);
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }

    
}
