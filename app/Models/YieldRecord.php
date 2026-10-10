<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class YieldRecord extends Model
{
    use HasUuids;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'yield_records';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'recorded_by_user_id',
        'farm_id',
        'farm_section_id',
        'crop_cycle_id',
        'crop_name',
        'harvest_date',
        'quantity_kg',
        'loss_kg',
        'revenue',
        'notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'harvest_date'          => 'date',
        'quantity_kg'           => 'decimal:2',
        'loss_kg'               => 'decimal:2',
        'net_yield_kg'          => 'decimal:2',
        'efficiency_percentage' => 'decimal:2',
        'revenue'               => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Read-only generated columns (never mass-assign these)
    |--------------------------------------------------------------------------
    */

    protected $guarded = [
        'net_yield_kg',
        'efficiency_percentage',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The user who recorded this yield entry.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'recorded_by_user_id',
        );
    }

    /**
     * The farm this yield record belongs to.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(
            related: Farm::class,
            foreignKey: 'farm_id',
        );
    }

    /**
     * The optional farm section this yield record is scoped to.
     */
    public function farmSection(): BelongsTo
    {
        return $this->belongsTo(
            related: FarmSection::class,
            foreignKey: 'farm_section_id',
        );
    }

    /**
     * The optional crop cycle this yield record is linked to.
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(
            related: CropCycle::class,
            foreignKey: 'crop_cycle_id',
        );
    }
}