<?php

namespace App\Notifications;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class InvestmentSubmittedNotification extends Notification
{
    use Queueable;

    protected Investment $investment;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    /**
     * Send via both email and database so admin dashboard receives it.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * 📩 Email version
     */
    public function toMail($notifiable)
    {
        $inv = $this->investment->loadMissing('project', 'user');
        $projectTitle = optional($inv->project)->title ?? "Project {$inv->project_id}";
        $investorName = optional($inv->user)->name ?? "Investor {$inv->user_id}";

        return (new MailMessage)
            ->subject("New Investment Submitted — {$projectTitle}")
            ->greeting("Hello Admin,")
            ->line("A new investment pledge has been submitted on CrowdBricks.")
            ->line("Project: {$projectTitle}")
            ->line("Investor: {$investorName}")
            ->line("Amount: ₵" . number_format($inv->amount))
            ->line("Payment method: " . ($inv->payment_method ?? 'N/A'))
            ->action('View in Admin Dashboard', url("/admin/investments/{$inv->id}"))
            ->line('You can review and approve this investment in the admin console.');
    }

    /**
     * 🧾 Database payload — this appears in /admin/notifications
     */
    public function toDatabase($notifiable)
    {
        $inv = $this->investment->loadMissing('project', 'user');
        $projectTitle = optional($inv->project)->title ?? "Project {$inv->project_id}";
        $investorName = optional($inv->user)->name ?? "Investor {$inv->user_id}";

        return [
            'type' => 'investment_submitted',
            'title' => 'New Investment Submitted',
            'body' => "{$investorName} invested ₵" . number_format($inv->amount) . " in {$projectTitle}",
            'investment_id' => $inv->id,
            'project_id' => $inv->project_id,
            'amount' => $inv->amount,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
