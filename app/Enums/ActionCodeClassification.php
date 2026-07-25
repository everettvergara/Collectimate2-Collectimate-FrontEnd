<?php

namespace App\Enums;

enum ActionCodeClassification: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
