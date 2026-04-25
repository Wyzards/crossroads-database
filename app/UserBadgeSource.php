<?php

namespace App\Enums;

enum UserBadgeSource: string
{
    case SYSTEM = 'system';
    case EVENT = 'event';
    case MANUAL = 'manual';
}
