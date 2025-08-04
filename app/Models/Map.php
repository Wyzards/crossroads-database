<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Map extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'image_name',
        'docker_folder',
        'allocated_ram_mb',
        'description',
        'min_players',
        'max_players'
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(MapInstance::class);
    }
}
