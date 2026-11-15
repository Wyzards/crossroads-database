<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The single settings row (id 1): the runner's switch, its ping roles and its lease. */
class VettingSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'lease_expires_at' => 'datetime',
    ];

    public static function row(): static
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
