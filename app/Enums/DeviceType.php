<?php

namespace App\Enums;

enum DeviceType: string
{
    case WIFI = 'wifi';
    case LORA = 'lora';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::WIFI->value => 'WiFi',
            self::LORA->value => 'LoRa',
            self::OTHER->value => 'Other',
        ];
    }
}
