<?php

namespace App\Enums;

enum SensorType: string
{
    case MOISTURE = 'moisture';
    case TEMPERATURE = 'temperature';
    case HUMIDITY = 'humidity';
    case LIGHT = 'light';
    case BATTERY = 'battery';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::MOISTURE->value => 'Moisture',
            self::TEMPERATURE->value => 'Temperature',
            self::HUMIDITY->value => 'Humidity',
            self::LIGHT->value => 'Light',
            self::BATTERY->value => 'Battery',
            self::OTHER->value => 'Other',
        ];
    }

    public function unit(): string
    {
        return match ($this) {
            self::MOISTURE => '%',
            self::TEMPERATURE => '°C',
            self::HUMIDITY => '%',
            self::LIGHT => 'lux',
            self::BATTERY => '%',
            self::OTHER => '',
        };
    }

    /**
     * Values that are relevant for farm-facing UIs (exclude internal-only types).
     */
    public static function farmRelevantValues(): array
    {
        return array_values(array_filter(self::values(), fn ($v) => $v !== self::BATTERY->value));
    }

    /**
     * Whether this sensor type is relevant for farm-facing display.
     */
    public function isFarmRelevant(): bool
    {
        return in_array($this->value, self::farmRelevantValues(), true);
    }
}
