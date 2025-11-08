<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PhoneChangeRequestNotification extends Notification
{
    use Queueable;

    protected $user;
    protected $newPhone;
    protected $oldPhone;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $newPhone, ?string $oldPhone = null)
    {
        $this->user = $user;
        $this->newPhone = $newPhone;
        $this->oldPhone = $oldPhone;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'phone_change_request',
            'title' => 'Phone Change Request',
            'body' => "{$this->user->first_name} {$this->user->last_name} requested to change phone number",
            'message' => "{$this->user->first_name} {$this->user->last_name} requested to change phone to {$this->newPhone}",
            'user_id' => $this->user->id,
            'user_name' => "{$this->user->first_name} {$this->user->last_name}",
            'user_email' => $this->user->email,
            'old_phone' => $this->oldPhone,
            'new_phone' => $this->newPhone,
            'time' => now()->diffForHumans(),
        ];
    }
}
