<?php

namespace App\Enums;

enum KnowledgeItemType: string
{
    case Text = 'text';
    case Url = 'url';
    case Pdf = 'pdf';
}
