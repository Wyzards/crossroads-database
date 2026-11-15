<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per week (named by the ET date of its Friday close): the frozen decisions and one-time Discord ids. */
class VettingCycle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'week_of' => 'date:Y-m-d',
        'close_votes' => 'array',
        'candidates' => 'array',
        'poll_results' => 'array',
        'closed_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function steps()
    {
        return $this->hasMany(VettingCycleStep::class, 'cycle_id');
    }

    public function toArray()
    {
        $a = parent::toArray();
        $a['week_of'] = $this->week_of?->format('Y-m-d');
        return $a;
    }
}
