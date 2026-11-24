<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityRequest extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'category', 'tipologia', 'municipality', 'neighborhood', 'budget', 'message', 'status',
    ];

    protected $casts = [
        'budget' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function offers()
    {
        return $this->hasMany(CommunityOffer::class);
    }
}
