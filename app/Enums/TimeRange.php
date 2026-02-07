<?php

namespace App\Enums;

enum TimeRange: string
{
    case HOUR = '-1h';
    case DAY = '-24h';
    case WEEK = '-7d';
    case MONTH = '-30d';
    case QUARTER = '-90d';

    public function label(): string
    {
        return match ($this) {
            self::HOUR => '1 hour',
            self::DAY => '24 hours',
            self::WEEK => '7 days',
            self::MONTH => '30 days',
            self::QUARTER => '90 days',
        };
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[] = ['value' => $case->value, 'label' => $case->label()];
        }

        return $out;
    }

    public static function default(): self
    {
        return self::DAY;
    }
}
