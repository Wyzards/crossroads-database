<?php

namespace App;

/** Featured level of a listed project; the bot shows it as an emoji in the channel name. */
enum ProjectTier: string
{
    case NORMAL = 'normal';
    case STAR = 'star';
    case FLAME = 'flame';
}
