<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'short_description',
        'description',
        'minimum_investment',
        'target_funding',
        'expected_yield',
        'timeline',
        'location',
        'categories',
        'tags',
        'approval_status',
        'funding_status',
        'current_funding',
        'investors',
    ];

    protected $casts = [
        'categories' => 'array',
        'tags' => 'array',
    ];

    protected $appends = ['image_url'];

    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('order');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * Get the first image URL for the project
     */
    public function getImageUrlAttribute(): ?string
    {
        $firstImage = $this->images()->first();
        return $firstImage ? $firstImage->url : null;
    }
};