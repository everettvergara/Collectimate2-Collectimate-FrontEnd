<?php

namespace App\Enums;

enum SmsBatchSource: string
{
    case AccountActivitySingle = 'account_activity_single';
    case AccountActivityBulk = 'account_activity_bulk';
}
