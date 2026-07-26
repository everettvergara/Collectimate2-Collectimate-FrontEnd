<?php

namespace App\Enums;

enum SmsTargetMode: string
{
    case GroupRoundRobin = 'group_round_robin';
    case SpecificDevice = 'specific_device';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::GroupRoundRobin => 'Round robin to group',
            self::SpecificDevice => 'Specific device',
        };
    }
}
