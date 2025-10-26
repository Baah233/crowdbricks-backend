<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'target_amount' => $this->target_amount,
            'raised_amount' => $this->raised_amount,
            'category' => $this->category,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'is_active' => $this->is_active,
            'user' => $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null,

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
