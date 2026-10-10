<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CropCycle extends Model
{
    use HasUuids;

    protected $fillable = [

        'farm_id',

        'farm_section_id',

        'created_by_user_id',

        'crop_name',

        'season',

        'planting_date',

        'expected_harvest_date',

        'expected_yield_kg_per_hectare',

        'expected_area_hectares',

        'actual_yield_kg',

        'status',

        'notes',
    ];

    protected $casts = [

        'planting_date' => 'date',

        'expected_harvest_date' => 'date',

        'expected_yield_kg_per_hectare'
            => 'decimal:2',

        'expected_area_hectares'
            => 'decimal:2',

        'actual_yield_kg'
            => 'decimal:2',
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

    public function section()
    {
        return $this->belongsTo(
            FarmSection::class,
            'farm_section_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function yieldRecords(): HasMany
{
    return $this->hasMany(
        related: YieldRecord::class,
        foreignKey: 'crop_cycle_id',
    );
}
}