<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserXpEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'xp_event_definition_id',
        'xp_amount',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(CrossroadsUser::class);
    }

    public function definition()
    {
        return $this->belongsTo(XpEventDefinition::class, 'xp_event_definition_id');
    }
}
