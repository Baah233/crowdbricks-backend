<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NewsAdminController extends Controller
{
    /**
     * Get all news articles (admin)
     */
    public function index()
    {
        $news = News::with('author:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($article) {
                if ($article->image && !str_starts_with($article->image, 'http')) {
                    $article->image = url($article->image);
                }
                return $article;
            });

        return response()->json($news);
    }

    /**
     * Create a new news article
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'excerpt' => 'required|string|max:500',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'category', 'excerpt', 'content', 'is_published', 'meta_title', 'meta_description', 'meta_keywords']);
        
        // Generate slug
        $data['slug'] = Str::slug($request->title);
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('news', 'public');
            $data['image'] = '/storage/' . $path;
        }

        // Handle OG image upload
        if ($request->hasFile('og_image')) {
            $path = $request->file('og_image')->store('news', 'public');
            $data['og_image'] = '/storage/' . $path;
        }

        // Set author
        $data['author_id'] = auth()->id();

        // Set published date if publishing
        if (!empty($data['is_published'])) {
            $data['published_at'] = now();
        }

        $news = News::create($data);

        return response()->json([
            'message' => 'News article created successfully',
            'news' => $news->load('author:id,first_name,last_name,email')
        ], 201);
    }

    /**
     * Update a news article
     */
    public function update(Request $request, $id)
    {
        $news = News::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:100',
            'excerpt' => 'sometimes|required|string|max:500',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'category', 'excerpt', 'content', 'is_published', 'meta_title', 'meta_description', 'meta_keywords']);

        // Update slug if title changed
        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($news->image) {
                $oldPath = str_replace('/storage/', '', $news->image);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('news', 'public');
            $data['image'] = '/storage/' . $path;
        }

        // Handle OG image upload
        if ($request->hasFile('og_image')) {
            // Delete old OG image
            if ($news->og_image) {
                $oldPath = str_replace('/storage/', '', $news->og_image);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('og_image')->store('news', 'public');
            $data['og_image'] = '/storage/' . $path;
        }

        // Set published date if newly publishing
        if (isset($data['is_published']) && $data['is_published'] && !$news->is_published) {
            $data['published_at'] = now();
        }

        $news->update($data);
        
        // Refresh the model to get updated attributes
        $news->refresh();
        
        // Load author relationship
        $news->load('author:id,first_name,last_name,email');
        
        // Transform image URL to absolute URL
        if ($news->image && !str_starts_with($news->image, 'http')) {
            $news->image = url($news->image);
        }

        return response()->json([
            'message' => 'News article updated successfully',
            'news' => $news
        ]);
    }

    /**
     * Delete a news article
     */
    public function destroy($id)
    {
        $news = News::findOrFail($id);

        // Delete image if exists
        if ($news->image) {
            $path = str_replace('/storage/', '', $news->image);
            Storage::disk('public')->delete($path);
        }

        $news->delete();

        return response()->json([
            'message' => 'News article deleted successfully'
        ]);
    }

    /**
     * Toggle publish status
     */
    public function togglePublish($id)
    {
        $news = News::findOrFail($id);
        
        $news->is_published = !$news->is_published;
        
        if ($news->is_published && !$news->published_at) {
            $news->published_at = now();
        }
        
        $news->save();

        return response()->json([
            'message' => $news->is_published ? 'Article published' : 'Article unpublished',
            'news' => $news->load('author:id,first_name,last_name,email')
        ]);
    }
}
