<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class News extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'category',
        'excerpt',
        'content',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'is_published',
        'published_at',
        'author_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = ['image_url', 'og_image_url'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($news) {
            if (empty($news->slug)) {
                $slug = Str::slug($news->title);
                $count = static::where('slug', 'LIKE', "$slug%")->count();
                $news->slug = $count ? "{$slug}-{$count}" : $slug;
            }
        });

        static::updating(function ($news) {
            if ($news->isDirty('title')) {
                $slug = Str::slug($news->title);
                // Only update slug if it's different and doesn't conflict
                if ($slug !== $news->slug) {
                    $count = static::where('slug', 'LIKE', "$slug%")
                        ->where('id', '!=', $news->id)
                        ->count();
                    $news->slug = $count ? "{$slug}-{$count}" : $slug;
                }
            }
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the full URL for the image
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return url($this->image);
    }

    /**
     * Get the full URL for the OG image
     */
    public function getOgImageUrlAttribute()
    {
        // Use og_image if set, otherwise fall back to main image
        $image = $this->og_image ?? $this->image;
        
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http')) {
            return $image;
        }

        return url($image);
    }
}
