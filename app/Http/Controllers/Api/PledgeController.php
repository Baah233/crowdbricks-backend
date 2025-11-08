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

class ProjectController extends Controller
{
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
     */
    public function myProjects(Request $request)
    {
        $user = $request->user();
        $projects = Project::where('user_id', $user->id)->with(['images','milestones','updates'])->latest()->get();
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