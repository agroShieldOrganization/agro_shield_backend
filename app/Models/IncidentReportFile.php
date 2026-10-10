<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class IncidentReportFile extends Model
{
    use HasUuids;

    protected $table =
        'incident_report_files';

    // File records also use UUID primary keys, so the model needs the same
    // non-incrementing string configuration as the parent incident report.
    public $incrementing =
        false;

    protected $keyType =
        'string';

    protected $fillable = [

        'incident_report_id',

        'original_name',

        'stored_name',

        'mime_type',

        'size_bytes',

        'disk',

        'path',

        'bucket',

        'visibility',

        'uploaded_at',
    ];

    protected $casts = [

        'uploaded_at' =>
            'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function incidentReport()
    {
        return $this->belongsTo(
            IncidentReport::class,
            'incident_report_id'
        );
    }
}
