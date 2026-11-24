<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PropertyService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function create(User $user, array $data, array $photos, ?UploadedFile $video = null): Property
    {
        if (! $user->canPublish()) {
            throw new BusinessException('Conta não está aprovada para publicar imóveis.', 'account_pending', 403);
        }

        $fingerprint = Normalizer::fingerprint(
            $data['province'],
            $data['municipality'],
            $data['neighborhood'],
            $data['private_address']
        );

        return DB::transaction(function () use ($user, $data, $photos, $video, $fingerprint) {
            $exists = Property::query()
                ->where('address_fingerprint', $fingerprint)
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw new BusinessException('Este imóvel já foi publicado.', 'property_duplicate', 409);
            }

            $property = Property::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'category' => $data['category'],
                'type' => $data['type'],
                'price' => $data['price'],
                'province' => $data['province'],
                'municipality' => Normalizer::text($data['municipality']),
                'neighborhood' => Normalizer::text($data['neighborhood']),
                'bedrooms' => $data['bedrooms'] ?? 0,
                'baths' => $data['baths'] ?? 0,
                'area' => $data['area'] ?? null,
                'private_address' => $data['private_address'],
                'private_lat' => $data['private_lat'] ?? null,
                'private_lng' => $data['private_lng'] ?? null,
                'visual_pin_x' => random_int(30, 70),
                'visual_pin_y' => random_int(20, 70),
                'description' => $data['description'],
                'amenities' => $data['amenities'] ?? [],
                'status' => Property::STATUS_PENDING,
                'address_fingerprint' => $fingerprint,
            ]);

            foreach ($photos as $index => $photo) {
                $path = $photo->store('properties/'.$property->id, 'public');
                PropertyMedia::create([
                    'property_id' => $property->id,
                    'kind' => 'photo',
                    'path' => $path,
                    'sort_order' => $index,
                    'is_cover' => $index === 0,
                ]);
            }

            if ($video) {
                $path = $video->store('properties/'.$property->id, 'public');
                PropertyMedia::create([
                    'property_id' => $property->id,
                    'kind' => 'video',
                    'path' => $path,
                    'sort_order' => 0,
                    'is_cover' => false,
                ]);
            }

            return $property->load(['media', 'owner', 'approvedAgents.agent']);
        });
    }

    public function delete(User $user, Property $property): void
    {
        if ($user->id !== $property->user_id && ! $user->isAdmin()) {
            throw new BusinessException('Não pode remover este imóvel.', 'forbidden', 403);
        }
        if ($property->status === Property::STATUS_ACTIVE && ! $user->isAdmin()) {
            throw new BusinessException('Imóvel publicado só pode ser removido por um administrador.', 'forbidden', 403);
        }
        $property->delete();
    }

    public function review(Property $property, string $status, ?string $reason, User $admin): Property
    {
        if (! in_array($status, [Property::STATUS_ACTIVE, Property::STATUS_REJECTED], true)) {
            throw new BusinessException('Estado de moderação inválido.', 'invalid_status', 422);
        }
        $property->status = $status;
        $property->rejection_reason = $status === Property::STATUS_REJECTED ? $reason : null;
        if ($status === Property::STATUS_REJECTED) {
            $property->address_fingerprint = null;
        }
        $property->save();

        $this->notifications->notify(
            $property->owner,
            'property_reviewed',
            $status === Property::STATUS_ACTIVE ? 'Imóvel aprovado' : 'Imóvel rejeitado',
            $status === Property::STATUS_ACTIVE
                ? 'O seu anúncio foi publicado.'
                : ('O anúncio foi rejeitado'.($reason ? ': '.$reason : '.')),
            ['property_id' => $property->id, 'status' => $status]
        );

        return $property;
    }

    public function toggleFavorite(User $user, Property $property): bool
    {
        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->first();
        if ($existing) {
            $existing->delete();

            return false;
        }
        Favorite::create(['user_id' => $user->id, 'property_id' => $property->id]);

        return true;
    }
}
