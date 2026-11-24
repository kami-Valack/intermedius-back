<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $merge = [];
        if ($this->has('privateAddress')) {
            $merge['private_address'] = $this->input('privateAddress');
        }
        if ($this->filled('privatePoint') && ! $this->filled('private_lat')) {
            $parts = array_map('trim', explode(',', (string) $this->input('privatePoint')));
            $merge['private_lat'] = $parts[0] ?? null;
            $merge['private_lng'] = $parts[1] ?? null;
        }
        if (is_string($this->input('amenities'))) {
            $merge['amenities'] = array_values(array_filter(array_map('trim', explode(',', $this->input('amenities')))));
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules()
    {
        $min = config('intermediarius.min_photos');
        $max = config('intermediarius.max_photos');
        $photoKb = config('intermediarius.max_photo_kb');
        $videoKb = config('intermediarius.max_video_mb') * 1024;

        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:rent,sale,commercial'],
            'type' => ['required', 'string', 'max:64'],
            'price' => ['required', 'integer', 'min:1'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'baths' => ['nullable', 'integer', 'min:0'],
            'area' => ['nullable', 'integer', 'min:1'],
            'private_address' => ['required', 'string', 'max:255'],
            'private_lat' => ['nullable', 'numeric'],
            'private_lng' => ['nullable', 'numeric'],
            'description' => ['required', 'string'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:80'],
            'photos' => ['required', 'array', 'min:'.$min, 'max:'.$max],
            'photos.*' => ['file', 'image', 'max:'.$photoKb],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.$videoKb],
        ];
    }
}
