<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model {
    protected $fillable = [
        'user_id','type','company','bio','phone','location','website','social_links','additional_info'
    ];
    protected $casts = [
        'social_links' => 'array',
        'additional_info' => 'array'
    ];

    public function user(){ return $this->belongsTo(User::class); }
}
