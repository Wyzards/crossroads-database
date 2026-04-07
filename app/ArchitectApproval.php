<?php

namespace App;

enum ArchitectApproval: string
{
    case APPROVED = 'approved';
    case UNAPPROVED = 'unapproved';
    case DISQUALIFIED = 'disqualified';
}
