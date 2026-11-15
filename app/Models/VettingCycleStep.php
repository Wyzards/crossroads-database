<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VettingCycleStep extends Model
{
    protected $guarded = [];

    protected $casts = [
        'next_attempt_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
