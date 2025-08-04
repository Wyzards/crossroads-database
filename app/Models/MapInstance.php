<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MapInstance extends Model
{
    use HasFactory;
    protected $fillable = [
        'map_id',
        'owner_id',
        //        'instance_name',
        'docker_container_id',
        // other fields
    ];

    // MapInstance belongs to a Map
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    // MapInstance belongs to owner user
    public function owner(): BelongsTo
    {
        return $this->belongsTo(CrossroadsUser::class, 'owner_id');
    }

    // MapInstance has many secondary users
    public function secondaryUsers(): BelongsToMany
    {
        return $this->belongsToMany(CrossroadsUser::class, 'map_instance_secondary_user', 'map_instance_id', 'crossroads_user_id');
    }
}
