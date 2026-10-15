<?php

namespace App\Enums;

enum FarmStatus: string
{
    case OK = 'ok';
    case WARNING = 'warning';
    case OFFLINE = 'offline';
    case UNKNOWN = 'unknown';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::OK->value => 'OK',
            self::WARNING->value => 'Warning',
            self::OFFLINE->value => 'Offline',
            self::UNKNOWN->value => 'No devices',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => [
            'value' => $status->value,
            'label' => self::labels()[$status->value],
        ], self::cases());
    }

    /**
     * Status is OK when every device is online, Offline when none are, Warning otherwise,
     * and Unknown for a farm without devices.
     */
    public static function fromDeviceCounts(int $online, int $total): self
    {
        return match (true) {
            $total === 0 => self::UNKNOWN,
            $online === $total => self::OK,
            $online === 0 => self::OFFLINE,
            default => self::WARNING,
        };
    }
}
