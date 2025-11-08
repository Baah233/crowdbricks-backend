<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * Get all published news articles
     */
    public function index()
    {
        $news = News::with('author:id,first_name,last_name')
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
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
     * Get a single news article by slug
     */
    public function show($slug)
    {
        $news = News::with('author:id,first_name,last_name,email')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        if ($news->image && !str_starts_with($news->image, 'http')) {
            $news->image = url($news->image);
        }

        return response()->json($news);
    }
}
