<?php

namespace App\Enums;

enum ContactInfoType: string
{
    case Email = 'email';
    case Mobile = 'mobile';
    case Landline = 'landline';
    case Fax = 'fax';
    case Facebook = 'facebook';
    case Linkedin = 'linkedin';
    case X = 'x';
    case Instagram = 'instagram';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Mobile => 'Mobile',
            self::Landline => 'Landline',
            self::Fax => 'Fax',
            self::Facebook => 'Facebook',
            self::Linkedin => 'LinkedIn',
            self::X => 'X',
            self::Instagram => 'Instagram',
            self::Website => 'Website',
            self::Other => 'Other',
        };
    }

    public function isSocial(): bool
    {
        return in_array($this, [
            self::Facebook,
            self::Linkedin,
            self::X,
            self::Instagram,
            self::Website,
        ], true);
    }
}
