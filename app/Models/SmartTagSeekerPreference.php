<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One canonical Smart Tag a Buyer or Tenant asked for, on one criteria record.
 *
 * Written only by SmartTagSeekerPreferenceWriter and read only by
 * SmartTagSeekerPreferenceReader. Nothing else may write here: the writer is
 * where SmartTagSelectionPolicy runs, and a second write path would be a second
 * place for a non-seeker-selectable or out-of-context key to get in.
 */
class SmartTagSeekerPreference extends Model
{
    use HasFactory;

    protected $table = 'smart_tag_seeker_preferences';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'seeker_role',
        'tag_key',
        'context',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'user_id'    => 'integer',
    ];
}
