<?php

namespace App\Enums;

enum SensorType: string
{
    case MOISTURE = 'moisture';
    case TEMPERATURE = 'temperature';
    case HUMIDITY = 'humidity';
    case LIGHT = 'light';
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
            self::OTHER->value => 'Other',
        ];
    }
}
