<?php

namespace App\Jobs;

use App\Models\Dividend;
use App\Models\Investment;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateQuarterlyDividends implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    protected $projectId;
    protected $quarterlyRate;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $projectId = null, float $quarterlyRate = 1.25)
    {
        $this->projectId = $projectId;
        $this->quarterlyRate = $quarterlyRate; // Default 1.25% per quarter (5% annual)
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting quarterly dividend calculation', [
            'project_id' => $this->projectId,
            'rate' => $this->quarterlyRate,
        ]);

        DB::beginTransaction();
        try {
            // Get projects to calculate dividends for
            $projects = $this->projectId 
                ? Project::where('id', $this->projectId)->get()
                : Project::where('funding_status', 'funded')->get();

            $dividendsCreated = 0;

            foreach ($projects as $project) {
                // Get all confirmed investments for this project
                $investments = Investment::where('project_id', $project->id)
                    ->where('status', 'confirmed')
                    ->get();

                foreach ($investments as $investment) {
                    // Check if dividend already exists for this quarter
                    $exists = Dividend::where('investment_id', $investment->id)
                        ->where('type', 'quarterly')
                        ->whereMonth('declaration_date', now()->month)
                        ->whereYear('declaration_date', now()->year)
                        ->exists();

                    if (!$exists) {
                        Dividend::create([
                            'project_id' => $project->id,
                            'investment_id' => $investment->id,
                            'user_id' => $investment->user_id,
                            'amount' => $investment->amount * ($this->quarterlyRate / 100),
                            'investment_amount' => $investment->amount,
                            'percentage' => $this->quarterlyRate,
                            'type' => 'quarterly',
                            'status' => 'pending',
                            'payment_method' => 'momo', // Default, can be changed by user
                            'declaration_date' => now(),
                            'payment_date' => now()->addDays(30), // Payment in 30 days
                            'notes' => 'Q' . now()->quarter . ' ' . now()->year . ' quarterly dividend',
                        ]);

                        $dividendsCreated++;
                    }
                }
            }

            DB::commit();

            Log::info('Quarterly dividend calculation completed', [
                'dividends_created' => $dividendsCreated,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quarterly dividend calculation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
