<?php

namespace App\Enums;

enum AlertRule: string
{
    case DEVICE_OFFLINE = 'device_offline';
    case LOW_BATTERY = 'low_battery';

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::DEVICE_OFFLINE => AlertSeverity::DANGER,
            self::LOW_BATTERY => AlertSeverity::WARNING,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::DEVICE_OFFLINE => 'Device offline',
            self::LOW_BATTERY => 'Low battery',
        };
    }
}
