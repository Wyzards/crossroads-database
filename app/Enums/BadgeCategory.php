<?php

namespace App\Enums;

enum BadgeCategory: string
{
    case PROGRESSION = 'progression';
    case COMMUNITY = 'community';
    case ACHIEVEMENT = 'achievement';
    case ARTIFACT = 'artifact';
}
