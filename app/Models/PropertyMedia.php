<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PropertyMedia extends Model
{
    use HasFactory;

    protected $fillable = ['property_id', 'kind', 'path', 'sort_order', 'is_cover'];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
