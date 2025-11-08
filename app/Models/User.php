<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
    'first_name',
    'last_name',
    'company',
    'email',
    'password',
    'ghana_card',
    'user_type',
    'role',
    'status',
    'phone',
    'phone_verified',
    'phone_verification_code',
    'phone_change_request',
    'phone_change_status',
    'verification_id',
    'email_notifications',
    'sms_notifications',
    'two_factor_enabled',
    'two_factor_secret',
    'two_factor_required',
    'profile_picture',
    'profile_completion',
];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verification_code_expires_at' => 'datetime',
    ];

    protected $appends = ['name'];

    // Accessor: get full name easily
    public function getNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Generate a unique verification ID for the user
     * Format: CB-XXXX (CrowdBricks + 4 digits)
     */
    public static function generateVerificationId()
    {
        do {
            // Generate a random 4-digit number
            $number = rand(1000, 9999);
            $verificationId = "CB-{$number}";
        } while (self::where('verification_id', $verificationId)->exists());

        return $verificationId;
    }

    /**
     * Calculate profile completion percentage
     */
    public function calculateProfileCompletion()
    {
        $fields = [
            'first_name' => 10,
            'last_name' => 10,
            'email' => 10,
            'phone' => 15,
            'phone_verified' => 15,
            'profile_picture' => 20,
            'two_factor_enabled' => 20,
        ];

        $completed = 0;
        foreach ($fields as $field => $weight) {
            if ($field === 'phone_verified' || $field === 'two_factor_enabled') {
                if ($this->$field) $completed += $weight;
            } else {
                if (!empty($this->$field)) $completed += $weight;
            }
        }

        return $completed;
    }

    /**
     * Update profile completion
     */
    public function updateProfileCompletion()
    {
        $this->profile_completion = $this->calculateProfileCompletion();
        $this->saveQuietly();
    }

    // Relationships (optional)
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class)->orderBy('login_at', 'desc');
    }

    public function dividends()
    {
        return $this->hasMany(Dividend::class)->orderBy('declaration_date', 'desc');
    }
}
