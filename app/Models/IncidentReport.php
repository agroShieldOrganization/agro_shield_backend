<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class IncidentReport extends Model
{
    use HasUuids;

    protected $table =
        'incident_reports';

    // This model uses UUID primary keys, so Eloquent must treat the key as
    // a non-incrementing string to return/report IDs correctly after create().
    public $incrementing =
        false;

    protected $keyType =
        'string';

    protected $fillable = [

        'reporter_user_id',

        'incident_type',

        'severity',

        'title',

        'location_name',

        'latitude',

        'longitude',

        'description',

        'affected_area_hectares',

        'contact_phone',

        'verification_status',

        'public_visibility',

        'occurred_at',

        'submitted_at',
    ];

    protected $casts = [

        'occurred_at' =>
            'datetime',

        'submitted_at' =>
            'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function reporter()
    {
        return $this->belongsTo(
            User::class,
            'reporter_user_id'
        );
    }

    public function files()
    {
        return $this->hasMany(
            IncidentReportFile::class,
            'incident_report_id'
        );
    }
}
