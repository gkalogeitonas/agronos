<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Jobs\RemoveBrokerUser;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory;

    /** Farm filter value for devices on no farm, as in `?farm=unassigned`. */
    public const UNASSIGNED_FARM_FILTER = 'unassigned';

    protected $fillable = [
        'user_id',
        'farm_id',
        'name',
        'uuid',
        'secret',
        'type',
        'status',
        'last_seen_at',
        'battery_level',
        'signal_strength',
        'mqtt_username',
        'mqtt_password',
        'lora_frame_counter',
        'lora_aes_key',
    ];

    protected $hidden = [
        'secret',
        'lora_aes_key',
    ];

    protected $casts = [
        'status' => DeviceStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleted(function (Device $device): void {
            // A polymorphic relation has no database cascade
            $device->alerts()->withoutGlobalScopes()->delete();

            if ($device->mqtt_username) {
                RemoveBrokerUser::dispatch($device->mqtt_username);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'subject');
    }

    /**
     * Battery percentage below which a device is reported as low battery.
     */
    public static function lowBatteryPercent(): int
    {
        return config('agronos.low_battery_percent');
    }

    public function isLowBattery(): bool
    {
        return $this->battery_level !== null && $this->battery_level < self::lowBatteryPercent();
    }

    /**
     * Scope to devices whose reported battery level is below the low-battery threshold.
     */
    public function scopeLowBattery(Builder $query): Builder
    {
        return $query->where('battery_level', '<', self::lowBatteryPercent());
    }

    /**
     * Scope to devices on the farm a list filter names: a farm id, or `unassigned` for devices on no farm.
     */
    public function scopeOnFarmFilter(Builder $query, string $farm): Builder
    {
        return $farm === self::UNASSIGNED_FARM_FILTER
            ? $query->whereNull($this->qualifyColumn('farm_id'))
            : $query->where($this->qualifyColumn('farm_id'), (int) $farm);
    }

    public function isLoRa(): bool
    {
        return $this->type === DeviceType::LORA->value;
    }
}
