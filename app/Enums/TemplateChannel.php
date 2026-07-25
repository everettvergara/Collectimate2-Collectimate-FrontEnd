<?php

namespace App\Enums;

enum TemplateChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Chat = 'chat';
}
