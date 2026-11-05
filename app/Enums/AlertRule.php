<?php

namespace App\Enums;

enum AlertRule: string
{
    case DEVICE_OFFLINE = 'device_offline';

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::DEVICE_OFFLINE => AlertSeverity::DANGER,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::DEVICE_OFFLINE => 'Device offline',
        };
    }
}
