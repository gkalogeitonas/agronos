<?php

namespace App\Models;

use App\Jobs\RemoveBrokerUser;
use App\Services\MqttTopics;
use App\Traits\BelongsToTenant;
use Database\Factories\GatewayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A LoRa gateway the tenant registered so it can publish to the broker (GLOSSARY: Gateway).
 */
class Gateway extends Model
{
    /** @use HasFactory<GatewayFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'mqtt_username',
        'mqtt_password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'mqtt_password',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Gateway $gateway): void {
            RemoveBrokerUser::dispatch($gateway->mqtt_username);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mqtt_password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A new broker username, prefixed so gateways are recognisable on the shared broker, e.g. gw-{uuid}.
     */
    public static function generateUsername(): string
    {
        return 'gw-'.Str::uuid();
    }

    /**
     * The topic base the gateway's ACL rules cover, e.g. agronos/lora/{username}.
     */
    public function topicBase(): string
    {
        return MqttTopics::loraGatewayBase($this->mqtt_username);
    }

    /**
     * The topic the gateway's web config must publish to, e.g. agronos/lora/{username}/data.
     */
    public function dataTopic(): string
    {
        return $this->topicBase().'/data';
    }
}
