<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'short_description',
        'full_description',
        'target_amount',
        'raised_amount',
        'category',
        'location',
        'type',
        'minimum_investment',
        'expected_yield',
        'timeline',
        'funding_status',
        'developer_name',
        'developer_verified',
        'developer_rating',
        'developer_completed_projects',
        'image_path',
        'slug',
        'status',
    ];

    // Auto-generate slug when title is set
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->title);
            }
        });
    }

    /**
     * Relationship: A project belongs to a user (owner)
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: A project can have many pledges
     */
    public function pledges()
    {
        return $this->hasMany(Pledge::class);
    }

    /**
     * Accessor: Get full image URL
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }

        return asset('images/default-project.jpg'); // fallback image
    }
}
