<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = ['project_id', 'title', 'months', 'status', 'order'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}