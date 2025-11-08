<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'category',
        'status',
        'priority',
        'admin_response',
        'assigned_to',
        'responded_at',
        'resolved_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function isOpen()
    {
        return $this->status === 'open';
    }

    public function isResolved()
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function getUnreadMessagesCount($isAdmin = false)
    {
        return $this->messages()
            ->where('is_admin', !$isAdmin) // If user is admin, count investor messages; if investor, count admin messages
            ->where('is_read', false)
            ->count();
    }
}
