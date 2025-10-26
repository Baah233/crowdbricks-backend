<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $fillable = [
        'user_id','project_id','amount','currency','gateway','reference','status','meta'
    ];
    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function user() { return $this->belongsTo(User::class); }
}
