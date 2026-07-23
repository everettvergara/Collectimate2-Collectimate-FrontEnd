<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Draft = 'draft';
}
