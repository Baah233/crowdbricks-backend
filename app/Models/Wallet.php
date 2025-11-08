<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'currency',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Get the user that owns the wallet
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Credit the wallet
     */
    public function credit(float $amount, string $type, array $metadata = []): WalletTransaction
    {
        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->save();

        return $this->transactions()->create([
            'user_id' => $this->user_id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Debit the wallet
     */
    public function debit(float $amount, string $type, array $metadata = []): WalletTransaction
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->save();

        return $this->transactions()->create([
            'user_id' => $this->user_id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);
    }
}
