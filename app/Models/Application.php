<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A proposed listing (pipeline v1). The applicant edits `working`; submitting copies it to
 * `snapshot` and bumps `revision`. Reviewers decide on the snapshot, naming the revision they saw.
 */
class Application extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const WITHDRAWN = 'withdrawn';

    /** The applicant may edit while no final decision has been made. */
    public const EDITABLE = [self::DRAFT, self::SUBMITTED];

    protected $fillable = [
        'applicant_discord_id', 'status', 'relationship', 'working', 'snapshot', 'revision',
        'submitted_at', 'thread_id', 'card_message_id', 'review_thread_id', 'project_id',
    ];

    protected $casts = [
        'working' => 'array',
        'snapshot' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function files()
    {
        return $this->hasMany(ApplicationFile::class);
    }

    public function reviews()
    {
        return $this->hasMany(ApplicationReview::class)->orderBy('id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
