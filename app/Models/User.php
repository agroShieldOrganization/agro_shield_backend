<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    use HasApiTokens, Notifiable;
    // FIllable Properties
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function pendingEmailChange()
    {
        return $this->hasOne(PendingEmailChange::class);
    }

    // Farmers Model Relationship
    public function farms()
        {
            return $this->hasMany(
                Farm::class,
                'owner_user_id'
            );
        }

        public function cropCycles()
        {
            return $this->hasMany(
                CropCycle::class,
                'created_by_user_id'
            );
        }

        

        public function incidentReports()
            {
                return $this->hasMany(
                    IncidentReport::class,
                    'reporter_user_id'
                );
            }

    public function yieldRecords(): HasMany
        {
            return $this->hasMany(
                related: YieldRecord::class,
                foreignKey: 'recorded_by_user_id',
            );
        }       
   
}
