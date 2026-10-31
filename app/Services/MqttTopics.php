<?php

namespace App\Services;

/**
 * Builds every Agronos MQTT topic under the configurable prefix (services.emqx.topic_prefix, see ADR-0007).
 */
class MqttTopics
{
    /**
     * The configured prefix without surrounding slashes, e.g. agronos.
     */
    public static function prefix(): string
    {
        return trim(config('services.emqx.topic_prefix'), '/');
    }

    /**
     * The topic base a device publishes and subscribes under, e.g. agronos/devices/{uuid}.
     */
    public static function deviceBase(string $uuid): string
    {
        return self::prefix()."/devices/{$uuid}";
    }

    /**
     * The filter matching every device's sensor readings.
     */
    public static function deviceSensorsFilter(): string
    {
        return self::deviceBase('+').'/sensors';
    }

    /**
     * The topic base a LoRa gateway publishes under, e.g. agronos/lora/{username}.
     */
    public static function loraGatewayBase(string $username): string
    {
        return self::prefix()."/lora/{$username}";
    }

    /**
     * The filter matching every LoRa gateway's uplinks.
     */
    public static function loraDataFilter(): string
    {
        return self::loraGatewayBase('+').'/data';
    }
}
