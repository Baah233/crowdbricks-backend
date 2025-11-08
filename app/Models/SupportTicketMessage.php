<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'message',
        'is_admin',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
