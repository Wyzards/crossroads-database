<?php

namespace App;

enum ProjectStage: string
{
    case IN_DEVELOPMENT = 'in_development';
    case ALPHA = 'alpha';
    case BETA = 'beta';
    case RELEASED = 'released';
    case CLOSED = 'closed';
}
