<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectImage extends Model
{
    protected $fillable = ['project_id', 'path', 'caption', 'order'];

    protected $appends = ['url'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the full URL for the image
     */
    public function getUrlAttribute(): string
    {
        $storageUrl = Storage::url($this->path);
        
        // If the URL is already absolute, return it
        if (str_starts_with($storageUrl, 'http')) {
            return $storageUrl;
        }
        
        // Otherwise, prepend the app URL
        return url($storageUrl);
    }
}