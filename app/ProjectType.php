<?php

namespace App;

enum ProjectType: string
{
    case MAP = "map";
    case OTHER = "other";
    case RPG = "rpg";
    case MMO = "mmo";
    case SMP = "smp";
}