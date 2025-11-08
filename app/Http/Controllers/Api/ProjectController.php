<?php

namespace App\Http\Controllers\Api;

use App\Events\ProjectSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectStoreRequest;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectImage;
use App\Models\ProjectUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Notifications\ProjectSubmittedNotification;

class ProjectController extends Controller
{
    /**
     * List all approved projects (public)
     */
    public function index(Request $request)
    {
        $query = Project::with([
            'developer:id,first_name,last_name', 
            'images', 
            'milestones',
            'investments' => function($q) {
                $q->where('status', 'confirmed'); // Admin confirmed investments
            }
        ])
            ->where('approval_status', 'approved')
            ->where('funding_status', '!=', 'completed')
            ->latest();

        // Optional filters
        if ($request->has('category')) {
            $query->whereJsonContains('categories', $request->category);
        }
        
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('short_description', 'like', '%' . $search . '%');
            });
        }

        $projects = $query->paginate($request->get('per_page', 12));
        
        // Calculate current funding for each project
        $projects->getCollection()->transform(function ($project) {
            $project->current_funding = $project->investments->sum('amount');
            $project->investors = $project->investments->unique('user_id')->count();
            return $project;
        });

        return response()->json($projects);
    }

    /**
     * Show a single project (public)
     */
    public function show($id)
    {
        // Support both ID and slug lookup
        $query = Project::with([
            'developer:id,first_name,last_name,company',
            'images',
            'documents',
            'milestones',
            'updates' => function($q) {
                $q->latest()->take(10);
            },
            'investments' => function($q) {
                $q->where('status', 'confirmed'); // Admin confirmed investments
            }
        ]);
        
        // Try to find by ID first (if numeric), then by slug
        if (is_numeric($id)) {
            $project = $query->where('id', $id)->first();
        } else {
            $project = $query->where('slug', $id)->first();
        }
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        // Only show approved projects publicly (unless accessed by owner)
        if ($project->approval_status !== 'approved' && (!auth()->check() || auth()->id() !== $project->user_id)) {
            abort(404, 'Project not found');
        }

        // Calculate current funding from completed investments
        $currentFunding = $project->investments->sum('amount');
        $project->current_funding = $currentFunding;
        
        // Calculate number of investors
        $project->investors = $project->investments->unique('user_id')->count();

        return response()->json($project);
    }

    /**
     * Store a new project (developer)
     * Accepts multipart/form-data with images[], documents[], and JSON-encoded arrays for categories, tags, milestones.
     */
    public function store(ProjectStoreRequest $request)
    {
        $user = $request->user();

        $data = $request->only([
            'title',
            'short_description',
            'description',
            'minimum_investment',
            'target_funding',
            'expected_yield',
            'timeline',
            'location',
        ]);

        $data['user_id'] = $user->id;
        $data['slug'] = Str::slug($data['title']) . '-' . substr(uniqid(), -6);

        $data['categories'] = json_decode($request->input('categories') ?? '[]', true) ?: [];
        $data['tags'] = json_decode($request->input('tags') ?? '[]', true) ?: [];

        // default statuses and funding
        $data['approval_status'] = $request->boolean('submit_for_approval') ? 'pending' : 'draft';
        $data['funding_status'] = 'funding';
        $data['current_funding'] = 0;
        $data['investors'] = 0;

        $project = Project::create($data);

        // handle milestones JSON
        $ms = json_decode($request->input('milestones') ?? '[]', true) ?: [];
        foreach ($ms as $i => $m) {
            Milestone::create([
                'project_id' => $project->id,
                'title' => $m['title'] ?? ('Milestone ' . ($i + 1)),
                'months' => intval($m['months'] ?? 0),
                'status' => $m['status'] ?? 'planned',
                'order' => $i,
            ]);
        }

        // handle images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('projects/images', 'public');
                ProjectImage::create([
                    'project_id' => $project->id,
                    'path' => $path,
                    'order' => $i,
                ]);
            }
        }

        // handle documents
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('projects/documents', 'public');
                ProjectDocument::create([
                    'project_id' => $project->id,
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        // dispatch event if submitted
        if ($project->approval_status === 'pending') {
            event(new ProjectSubmitted($project));
        }

        return response()->json(['message' => 'Project created', 'project' => $project->load('images','documents','milestones')], 201);
    }

    /**
     * Update an existing project (developer)
     */
    public function update(ProjectStoreRequest $request, $id)
    {
        $project = Project::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->only([
            'title',
            'short_description',
            'description',
            'minimum_investment',
            'target_funding',
            'expected_yield',
            'timeline',
            'location',
        ]);

        if (isset($data['title'])) {
            $project->slug = Str::slug($data['title']) . '-' . substr(uniqid(), -6);
        }

        $project->fill($data);
        $project->categories = json_decode($request->input('categories') ?? '[]', true) ?: $project->categories;
        $project->tags = json_decode($request->input('tags') ?? '[]', true) ?: $project->tags;
        $project->save();

        // optionally update milestones if provided (replace)
        $ms = json_decode($request->input('milestones') ?? '[]', true);
        if (is_array($ms) && count($ms)) {
            $project->milestones()->delete();
            foreach ($ms as $i => $m) {
                Milestone::create([
                    'project_id' => $project->id,
                    'title' => $m['title'] ?? ('Milestone ' . ($i + 1)),
                    'months' => intval($m['months'] ?? 0),
                    'status' => $m['status'] ?? 'planned',
                    'order' => $i,
                ]);
            }
        }

        // handle new images/documents same as store
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('projects/images', 'public');
                ProjectImage::create([
                    'project_id' => $project->id,
                    'path' => $path,
                    'order' => $project->images()->count() + $i,
                ]);
            }
        }

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('projects/documents', 'public');
                ProjectDocument::create([
                    'project_id' => $project->id,
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        if ($request->boolean('submit_for_approval')) {
            $project->approval_status = 'pending';
            $project->save();
            event(new ProjectSubmitted($project));
        }

        return response()->json(['message' => 'Project updated', 'project' => $project->load('images','documents','milestones')]);
    }

    /**
     * List projects for current user
     *
     * NOTE: This method was missing in your controller and caused the "Call to undefined method ...::myProjects()"
     * error. Adding it ensures the GET /user/projects route works as expected.
     */
    public function myProjects(Request $request)
    {
        $user = $request->user();
        $projects = Project::where('user_id', $user->id)
            ->with(['images','milestones','updates','documents'])
            ->latest()
            ->get();

        return response()->json($projects);
    }

    /**
     * Submit project for approval (explicit endpoint)
     */
    public function submitForApproval(Request $request, $id)
    {
        $project = Project::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $project->approval_status = 'pending';
        $project->save();
        event(new ProjectSubmitted($project));
        return response()->json(['message' => 'Submitted for approval', 'project' => $project]);
    }

    /**
     * Add a project update (public for developers)
     */
    public function addUpdate(Request $request, $id)
    {
        $project = Project::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $title = $request->input('title') ?? 'Update';
        $content = $request->input('content') ?? '';

        $attachments = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('projects/updates', 'public');
                $attachments[] = $path;
            }
        }

        $update = ProjectUpdate::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'title' => $title,
            'content' => $content,
            'attachments' => $attachments,
        ]);

        return response()->json(['message' => 'Update posted', 'update' => $update]);
    }
}