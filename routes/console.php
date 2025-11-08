<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CalculateQuarterlyDividends;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule quarterly dividend calculations
// Runs on the first day of each quarter at 2 AM
Schedule::job(new CalculateQuarterlyDividends())
    ->quarterly()
    ->at('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Manual command to calculate dividends for testing
Artisan::command('dividends:calculate {projectId?}', function ($projectId = null) {
    $this->info('Calculating quarterly dividends...');
    CalculateQuarterlyDividends::dispatch($projectId);
    $this->info('Dividend calculation job dispatched successfully!');
})->purpose('Calculate and create quarterly dividends');
