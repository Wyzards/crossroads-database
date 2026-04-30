<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Era extends Model
{
    protected $fillable = [
        'name',
        'started_at',
        'ended_at',
        'is_active',
        'role_id'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(CrossroadsUser::class, 'era_id');
    }
}
