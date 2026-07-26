<?php

namespace App\Enums;

enum SmsBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
