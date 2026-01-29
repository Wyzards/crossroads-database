<?php

namespace App\Models;

use App\ProjectStaffRank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStaff extends Model
{
    use HasFactory;
    protected $table = 'project_staff';

    protected $fillable = [
        'project_id',
        'user_id',
        'rank',
    ];

    protected $casts = [
        'rank' => ProjectStaffRank::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(CrossroadsUser::class, 'user_id');
    }
}
