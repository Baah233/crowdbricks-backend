<?php

namespace App\Observers;

use App\Models\Dividend;
use Illuminate\Support\Facades\Cache;

class DividendObserver
{
    /**
     * Handle the Dividend "created" event.
     */
    public function created(Dividend $dividend): void
    {
        $this->clearUserCache($dividend->user_id);
    }

    /**
     * Handle the Dividend "updated" event.
     */
    public function updated(Dividend $dividend): void
    {
        $this->clearUserCache($dividend->user_id);
    }

    /**
     * Handle the Dividend "deleted" event.
     */
    public function deleted(Dividend $dividend): void
    {
        $this->clearUserCache($dividend->user_id);
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
