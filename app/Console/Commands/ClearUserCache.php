<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class ClearUserCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-user {userId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cached data for a specific user or all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');

        if ($userId) {
            // Clear cache for specific user
            $this->clearUserCache($userId);
            $this->info("Cache cleared for user ID: {$userId}");
        } else {
            // Clear cache for all users
            $users = User::all();
            $count = 0;

            foreach ($users as $user) {
                $this->clearUserCache($user->id);
                $count++;
            }

            $this->info("Cache cleared for {$count} users");
        }

        return 0;
    }

    /**
     * Clear all cached data for a specific user
     */
    protected function clearUserCache(int $userId): void
    {
        Cache::forget("user.{$userId}.stats");
        Cache::forget("user.{$userId}.investments");
        Cache::forget("user.{$userId}.transactions");
        Cache::forget("user.{$userId}.dividends");
    }
}
