<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'device_name',
        'browser',
        'platform',
        'location',
        'country_code',
        'status',
        'failure_reason',
        'is_suspicious',
        'login_at',
        'logout_at',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_suspicious' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a new device/location for the user
     */
    public function isNewDevice(): bool
    {
        return !self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->where('device_name', $this->device_name)
            ->where('ip_address', $this->ip_address)
            ->exists();
    }

    /**
     * Get session duration in minutes
     */
    public function getSessionDurationAttribute(): ?int
    {
        if (!$this->logout_at) {
            return null;
        }
        
        return $this->login_at->diffInMinutes($this->logout_at);
    }
}
