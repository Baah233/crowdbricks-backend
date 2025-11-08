<?php

namespace App\Listeners;

use App\Events\InvestmentSubmitted;
use App\Models\User;
use App\Models\Audit;
use App\Notifications\InvestmentSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for InvestmentSubmitted event.
 * - Sends notifications to admins (email) using the InvestmentSubmittedNotification
 * - Writes an audit record to audits table
 *
 * Marked as queueable (ShouldQueue) in production — implement queue setup for heavy workloads.
 */
class SendInvestmentSubmittedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(InvestmentSubmitted $event)
    {
        $investment = $event->investment;

        // create a lightweight audit entry
        Audit::create([
            'actor_type' => get_class($investment->user),
            'actor_id' => $investment->user_id,
            'action' => 'investment_submitted',
            'details' => [
                'investment_id' => $investment->id,
                'project_id' => $investment->project_id,
                'amount' => $investment->amount,
                'payment_method' => $investment->payment_method ?? null,
            ],
        ]);

        // Notify administrators
        // Use a config or env value for admin emails; fallback to config('mail.from.address')
        $adminEmails = config('platform.admin_emails', []);
        if (empty($adminEmails)) {
            $adminEmails = [config('mail.from.address')];
        }

        foreach ($adminEmails as $email) {
            // we use Notification::route
            \Notification::route('mail', $email)
                ->notify(new InvestmentSubmittedNotification($investment));
        }

        return;
    }
}