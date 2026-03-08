<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrossroadsUser extends Model
{
    use HasFactory;
    protected $table = 'crossroads_users';

    protected $appends = ['discordId', 'minecraftUuid'];
    protected $hidden = ['discord_id', 'minecraft_uuid'];

    protected $fillable = [
        'minecraft_uuid',
        'discord_id',
        // other user fields
    ];

    // One user owns many map instances
    public function ownedInstances(): HasMany
    {
        return $this->hasMany(MapInstance::class, 'owner_id', 'id');
    }

    // User can be a secondary user on many map instances
    public function secondaryInstances(): BelongsToMany
    {
        return $this->belongsToMany(MapInstance::class, 'map_instance_secondary_user', 'crossroads_user_id', 'map_instance_id');
    }

    public function getDiscordIdAttribute()
    {
        return $this->attributes['discord_id'] ?? null;
    }

    public function getMinecraftUuidAttribute()
    {
        return $this->attributes['minecraft_uuid'] ?? null;
    }
}
