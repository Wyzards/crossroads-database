<?php

namespace App;

enum CommunityVetted: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case UNVETTED = 'unvetted';
    case SKIPPED = 'skipped';
}
