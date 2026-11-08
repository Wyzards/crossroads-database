<?php

namespace App;

/** What kind of thing a project is. Maps get no channel and list in the maps forum. */
enum ProjectFormat: string
{
    case SERVER = 'server';
    case MAP = 'map';
}
