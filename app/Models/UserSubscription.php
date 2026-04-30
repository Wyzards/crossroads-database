<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'total_months',
        'current_streak',
        'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(CrossroadsUser::class);
    }
}
