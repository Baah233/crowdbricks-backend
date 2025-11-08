<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvestmentAdminController extends Controller
{
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !($user->is_admin ?? false)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,received,confirmed,approved,failed',
            'note' => 'nullable|string',
        ]);

        $status = $request->input('status');

        DB::beginTransaction();
        try {
            $investment = Investment::findOrFail($id);
            $investment->status = $status;
            $investment->save();

            if (in_array($status, ['confirmed','approved'])) {
                $project = Project::find($investment->project_id);
                if ($project) {
                    $project->currentFunding = ($project->currentFunding ?? 0) + $investment->amount;
                    $project->save();
                }
            }

            Audit::create([
                'actor_type' => get_class($user),
                'actor_id' => $user->id,
                'action' => 'investment_status_changed',
                'details' => [
                    'investment_id' => $investment->id,
                    'new_status' => $status,
                    'note' => $request->input('note'),
                ],
            ]);

            DB::commit();

            return response()->json(['message' => 'Status updated', 'investment' => $investment]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Admin update investment failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update status'], 500);
        }
    }
}