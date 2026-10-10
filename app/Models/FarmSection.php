<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FarmSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'farm_id',
        'name',
        'area_hectares',
        'latitude',
        'longitude',
        'soil_type',
    ];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropCycles()
    {
        return $this->hasMany(
            CropCycle::class
        );
    }

    public function yieldRecords(): HasMany
{
    return $this->hasMany(
        related: YieldRecord::class,
        foreignKey: 'farm_section_id',
    );
}
}