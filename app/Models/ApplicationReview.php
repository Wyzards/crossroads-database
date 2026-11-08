<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One reviewer decision on one revision of an application. */
class ApplicationReview extends Model
{
    public const ACCEPT_WATCHLIST = 'accept_watchlist';
    public const ACCEPT_VETTING = 'accept_vetting';
    public const ACCEPT_HUNT = 'accept_hunt';
    public const REJECT_RESUBMIT = 'reject_resubmit';
    public const REJECT_FINAL = 'reject_final';

    public const REJECTIONS = [self::REJECT_RESUBMIT, self::REJECT_FINAL];

    protected $fillable = ['application_id', 'revision', 'decision', 'reason', 'reviewer_discord_id'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
