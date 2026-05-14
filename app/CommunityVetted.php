<?php

namespace App;

enum CommunityVetted: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case UNVETTED = 'unvetted';
    case SKIPPED = 'skipped';
    case PROJECT_HUNT_QUEUE = 'project_hunt_queue';
    case VETTING_QUEUE = 'vetting_queue';
    case CURRENTLY_VETTING = 'currently_vetting';
}
