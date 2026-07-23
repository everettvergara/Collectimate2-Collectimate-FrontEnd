<?php

namespace App\Enums;

enum AgentProfileStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
}
