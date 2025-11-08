<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Audit extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}