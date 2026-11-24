<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_request_id', 'property_id', 'user_id', 'accepted',
    ];

    protected $casts = [
        'accepted' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(CommunityRequest::class, 'community_request_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
