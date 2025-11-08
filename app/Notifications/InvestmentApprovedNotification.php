<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvestmentApprovedNotification extends Notification
{
    use Queueable;

    protected $investment;
    protected $projectTitle;

    /**
     * Create a new notification instance.
     */
    public function __construct($investment, $projectTitle)
    {
        $this->investment = $investment;
        $this->projectTitle = $projectTitle;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'success',
            'title' => 'Investment Approved',
            'message' => 'Your investment of ₵' . number_format($this->investment->amount, 2) . ' in ' . $this->projectTitle . ' has been approved.',
            'time' => now()->diffForHumans(),
            'investment_id' => $this->investment->id,
            'project_id' => $this->investment->project_id,
        ];
    }
}
