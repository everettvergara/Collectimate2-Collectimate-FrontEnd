<?php

namespace App\Enums;

enum ContactInfoType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Landline = 'landline';
    case Fax = 'fax';
    case Other = 'other';
}
