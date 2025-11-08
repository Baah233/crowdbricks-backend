<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Project::class);
    }
}