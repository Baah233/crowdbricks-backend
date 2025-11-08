<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectDocument extends Model
{
    protected $fillable = ['project_id', 'path', 'name', 'size'];

    protected $appends = ['url', 'formatted_size'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the full URL for the document
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

    /**
     * Get human-readable file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }
}