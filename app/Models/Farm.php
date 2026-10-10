<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Farm extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_user_id',
        'name',
        'location_name',
        'country',
        'state_region',
        'address_text',
        'latitude',
        'longitude',
        'size_hectares',
        'primary_crop_name',
        'health_score',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'size_hectares' => 'decimal:2',
        'health_score' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function owner()
    {
        return $this->belongsTo(
            User::class,
            'owner_user_id'
        );
    }

    public function sections()
    {
        return $this->hasMany(FarmSection::class);
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
        foreignKey: 'farm_id',
    );
}

}