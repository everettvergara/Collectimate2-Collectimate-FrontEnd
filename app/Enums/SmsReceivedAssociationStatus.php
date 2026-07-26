<?php

namespace App\Enums;

enum SmsReceivedAssociationStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Manual = 'manual';
    case Ignored = 'ignored';
}
