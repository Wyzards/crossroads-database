<?php

namespace App;

/** Where the project's community lives on Discord: its own server, a network's shared server, or none. */
enum DiscordKind: string
{
    case OWN = 'own';
    case NETWORK = 'network';
    case NONE = 'none';
}
