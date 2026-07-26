<?php

namespace App\Enums;

enum SmsDeviceType: string
{
    case At = 'AT';
    case Huawei = 'Huawei';
    case Goip = 'GOIP';
    case Demo = 'Demo';

    public function label(): string
    {
        return match ($this) {
            self::At => 'AT Modem',
            self::Huawei => 'Huawei',
            self::Goip => 'GOIP',
            self::Demo => 'Demo',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
