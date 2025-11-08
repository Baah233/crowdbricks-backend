<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dividend extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'investment_id',
        'user_id',
        'amount',
        'investment_amount',
        'percentage',
        'type',
        'status',
        'payment_method',
        'payment_reference',
        'declaration_date',
        'payment_date',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'investment_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'declaration_date' => 'date',
        'payment_date' => 'date',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dividend yield percentage
     */
    public function getYieldAttribute(): float
    {
        if ($this->investment_amount <= 0) {
            return 0;
        }
        
        return ($this->amount / $this->investment_amount) * 100;
    }

    /**
     * Check if dividend is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && 
               $this->payment_date && 
               $this->payment_date->isPast();
    }
}
