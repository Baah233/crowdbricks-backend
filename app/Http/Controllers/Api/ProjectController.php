<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Get all published projects (with optional search)
     */
    public function index(Request $req)
    {
        $query = Project::query()->where('status', 'published');

        if ($s = $req->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%$s%")
                  ->orWhere('short_description', 'like', "%$s%");
            });
        }

        $projects = $query->paginate(12);
        return ProjectResource::collection($projects);
    }

    /**
     * Get a single project by ID
     */
    public function show($id)
    {
        $project = Project::findOrFail($id);
        return new ProjectResource($project);
    }

    /**
     * Store a new project
     */

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'short_description' => 'nullable|string',
        'full_description' => 'nullable|string',
        'location' => 'nullable|string|max:255',
        'type' => 'nullable|string|max:255',
        'target_amount' => 'required|numeric|min:0',
        'raised_amount' => 'nullable|numeric|min:0',
        'minimum_investment' => 'nullable|numeric|min:0',
        'expected_yield' => 'nullable|numeric|min:0',
        'timeline' => 'nullable|string|max:255',
        'funding_status' => 'nullable|string|max:255',
        'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $user = auth()->user();

    // ✅ Handle image upload
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('projects', 'public');
        $validated['image_path'] = $imagePath;
    }

    // ✅ Auto fields
    $validated['slug'] = Str::slug($validated['title']);
    $validated['status'] = 'published';
    $validated['user_id'] = $user->id;

    // ✅ Optionally pull developer info from Profile
    if ($user->profile) {
        $validated['developer_name'] = $user->profile->company ?? $user->name;
        $validated['developer_verified'] = true;
        $validated['developer_rating'] = 5;
        $validated['developer_completed_projects'] = $user->projects()->count();
    } else {
        $validated['developer_name'] = $user->name;
        $validated['developer_verified'] = false;
        $validated['developer_rating'] = 0;
        $validated['developer_completed_projects'] = 0;
    }

    $project = Project::create($validated);

    return response()->json([
        'message' => 'Project created successfully',
        'project' => new ProjectResource($project),
    ], 201);
}


}
