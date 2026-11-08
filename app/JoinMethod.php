<?php

namespace App;

/** How players get in: the project's own address, or a network hub plus join_instructions. */
enum JoinMethod: string
{
    case DIRECT = 'direct';
    case NETWORK = 'network';
}
