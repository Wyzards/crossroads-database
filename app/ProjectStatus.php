<?php

namespace App;

enum ProjectStatus: string
{
    case PLAYABLE = 'playable';
    case IN_DEVELOPMENT = 'in_deveopment';
    case ARCHIVED = 'archived';
    case HONORABLE_MENTION = 'honorable_mention';
    case HIDDEN = 'hidden';
}
