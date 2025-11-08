<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentStoreRequest;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use App\Events\InvestmentSubmitted;
use App\Notifications\InvestmentSubmittedNotification;

class InvestmentController extends Controller
{
    public function store(InvestmentStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Check if user is approved to invest
        if ($user->status !== 'approved') {
            return response()->json([
                'message' => 'Your account is pending admin approval. You will be able to invest once approved.',
                'status' => 'pending_approval'
            ], 403);
        }

        $project = Project::find($data['project_id']);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        // Check if project is approved and open for funding
        if ($project->approval_status !== 'approved' || !in_array($project->funding_status, ['funding', 'open'])) {
            return response()->json(['message' => 'Project not open for investment'], 422);
        }

        DB::beginTransaction();
        try {
            $investment = Investment::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'amount' => (int)$data['amount'],
                'currency' => $data['currency'] ?? 'GHS',
                'payment_method' => $data['payment_method'],
                'status' => 'pending',
                'metadata' => $data['metadata'] ?? null,
            ]);

            Audit::create([
                'actor_type' => get_class($user),
                'actor_id' => $user->id,
                'action' => 'investment_submitted',
                'details' => [
                    'investment_id' => $investment->id,
                    'project_id' => $project->id,
                    'amount' => $investment->amount,
                ],
            ]);

            Event::dispatch(new InvestmentSubmitted($investment));

            DB::commit();

            $admins = \App\Models\User::where('user_type', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new InvestmentSubmittedNotification($investment));
                }

            return response()->json([
                'message' => 'Investment submitted',
                'investment' => $investment->load('project'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Investment create failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to submit investment'], 500);
        }
    }
}