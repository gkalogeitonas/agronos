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

    /**
     * Statuses in display order, for frontend filters.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $labels = self::labels();

        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => $labels[$status->value],
        ], self::cases());
    }
}
