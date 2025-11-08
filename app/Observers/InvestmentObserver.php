<?php

namespace App\Observers;

use App\Models\Investment;
use Illuminate\Support\Facades\Cache;

class InvestmentObserver
{
    /**
     * Handle the Investment "created" event.
     */
    public function created(Investment $investment): void
    {
        $this->clearUserCache($investment->user_id);
    }

    /**
     * Handle the Investment "updated" event.
     */
    public function updated(Investment $investment): void
    {
        $this->clearUserCache($investment->user_id);
    }

    /**
     * Handle the Investment "deleted" event.
     */
    public function deleted(Investment $investment): void
    {
        $this->clearUserCache($investment->user_id);
    }

    /**
     * Clear all cached data for a user
     */
    protected function clearUserCache(int $userId): void
    {
        Cache::forget("user.{$userId}.stats");
        Cache::forget("user.{$userId}.investments");
        Cache::forget("user.{$userId}.transactions");
        Cache::forget("user.{$userId}.dividends");
    }
}
