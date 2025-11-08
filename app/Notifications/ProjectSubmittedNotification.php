<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ProjectSubmittedNotification extends Notification
{
    use Queueable;

    protected Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Send both email and database notifications.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * 📩 Email version — goes to all admins.
     */
    public function toMail($notifiable)
    {
        $p = $this->project->loadMissing('developer');
        $developerName = optional($p->developer)->name 
            ?? ($p->developer->first_name ?? 'Developer');

        return (new MailMessage)
            ->subject("New Project Submitted — {$p->title}")
            ->greeting("Hello Admin,")
            ->line("A new project has been submitted for approval.")
            ->line("Title: {$p->title}")
            ->line("Developer: {$developerName}")
            ->line("Target Funding: ₵" . number_format($p->target_funding ?? 0))
            ->action('Review Project', url("/admin/projects/{$p->id}"))
            ->line('Please review and approve this project in the admin console.');
    }

    /**
     * 🧾 Database version — visible in /admin/notifications API.
     */
    public function toDatabase($notifiable)
    {
        $p = $this->project->loadMissing('developer');
        $developerName = optional($p->developer)->name 
            ?? ($p->developer->first_name ?? 'Developer');

        return [
            'type' => 'project_submitted',
            'title' => 'New Project Submitted',
            'body' => "{$developerName} submitted a new project titled '{$p->title}' for approval.",
            'project_id' => $p->id,
            'developer_id' => $p->user_id,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
