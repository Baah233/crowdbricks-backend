<?php

namespace App\Listeners;

use App\Events\ProjectSubmitted;
use App\Models\Audit;
use App\Models\User;
use App\Notifications\ProjectSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfProjectSubmission implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ProjectSubmitted $event)
    {
        $project = $event->project->load('developer');

        // 🧾 Log the submission in the audit table
        Audit::create([
            'actor_type' => get_class($project->developer),
            'actor_id' => $project->user_id,
            'action' => 'project_submitted',
            'details' => [
                'project_id' => $project->id,
                'title' => $project->title,
            ],
        ]);

        // 📧 Email fallback: notify configured admin addresses
        $emails = config('platform.admin_emails', []);
        if (empty($emails) && config('mail.from.address')) {
            $emails = [config('mail.from.address')];
        }

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new ProjectSubmittedNotification($project));
        }

        // 🧠 Database notifications: send to all admin users
        $admins = User::where('user_type', 'admin')->get();
        Notification::send($admins, new ProjectSubmittedNotification($project));
    }
}
