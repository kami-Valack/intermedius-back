<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'status', 'payload_meta'];

    protected $casts = [
        'payload_meta' => 'array',
    ];
}
