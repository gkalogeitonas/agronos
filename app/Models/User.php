<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected static function booted(): void
    {
        // The database cascade skips model events, so delete devices and gateways one by one to clean up their broker users
        static::deleting(function (User $user): void {
            Device::forTenant($user->id)->get()->each->delete();
            Gateway::forTenant($user->id)->get()->each->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function farms()
    {
        return $this->hasMany(Farm::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class);
    }
}
