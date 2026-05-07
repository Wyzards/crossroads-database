<?php

namespace App\Enums;

enum BadgeRarity: string
{
    case COMMON = 'common';
    case UNCOMMON = 'uncommon';
    case RARE = 'rare';
    case LEGENDARY = 'legendary';
}
