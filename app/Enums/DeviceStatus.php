<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case REGISTERED = 'registered';
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case ERROR = 'error';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::REGISTERED->value => 'Registered',
            self::ONLINE->value => 'Online',
            self::OFFLINE->value => 'Offline',
            self::ERROR->value => 'Error',
        ];
    }
}
