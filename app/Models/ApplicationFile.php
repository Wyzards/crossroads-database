<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A file the applicant attached (section file or banner), stored on the API's disk. */
class ApplicationFile extends Model
{
    protected $fillable = ['application_id', 'path', 'name', 'content_type'];

    protected $hidden = ['path'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
