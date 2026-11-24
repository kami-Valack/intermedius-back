<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AgentLink;
use App\Models\CommunityOffer;
use App\Models\CommunityRequest;
use App\Models\Property;
use App\Models\User;
use App\Support\Normalizer;

class CommunityService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function create(User $client, array $data): CommunityRequest
    {
        if ($client->role !== User::ROLE_CLIENT) {
            throw new BusinessException('Só clientes podem publicar pedidos da comunidade.', 'forbidden', 403);
        }

        $request = CommunityRequest::create([
            'user_id' => $client->id,
            'category' => $data['category'],
            'tipologia' => $data['tipologia'],
            'municipality' => Normalizer::text($data['municipality']),
            'neighborhood' => Normalizer::text($data['neighborhood']),
            'budget' => $data['budget'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => CommunityRequest::STATUS_OPEN,
        ]);

        $this->notifyProfessionals($request);

        return $request;
    }

    public function close(User $client, CommunityRequest $request): CommunityRequest
    {
        if ($request->user_id !== $client->id) {
            throw new BusinessException('Só o autor pode fechar o pedido.', 'forbidden', 403);
        }
        $request->status = CommunityRequest::STATUS_CLOSED;
        $request->save();

        return $request;
    }

    public function respond(User $professional, CommunityRequest $request, Property $property, bool $accept): CommunityOffer
    {
        if ($request->status !== CommunityRequest::STATUS_OPEN) {
            throw new BusinessException('Este pedido já está fechado.', 'request_closed', 422);
        }
        if ($property->status !== Property::STATUS_ACTIVE) {
            throw new BusinessException('O imóvel não está activo.', 'property_inactive', 422);
        }
        if (! $this->canOffer($professional, $property)) {
            throw new BusinessException('Não está autorizado a responder com este imóvel.', 'forbidden', 403);
        }
        if (! $this->matches($request, $property)) {
            throw new BusinessException('O imóvel não é elegível para este pedido.', 'not_eligible', 422);
        }

        $existing = CommunityOffer::query()
            ->where('community_request_id', $request->id)
            ->where('property_id', $property->id)
            ->where('user_id', $professional->id)
            ->first();
        if ($existing) {
            throw new BusinessException('Já respondeu a este pedido com este imóvel.', 'offer_duplicate', 409);
        }

        $offer = CommunityOffer::create([
            'community_request_id' => $request->id,
            'property_id' => $property->id,
            'user_id' => $professional->id,
            'accepted' => $accept,
        ]);

        if ($accept) {
            $this->notifications->notify(
                $request->client,
                'community_offer',
                'Resposta ao seu pedido',
                'Um profissional aceitou apresentar um imóvel compatível.',
                ['request_id' => $request->id, 'property_id' => $property->id]
            );
        }

        return $offer;
    }

    public function matches(CommunityRequest $request, Property $property): bool
    {
        if (Normalizer::fingerprint($request->municipality) !== Normalizer::fingerprint($property->municipality)) {
            return false;
        }
        if ($property->tipologia() !== $request->tipologia) {
            return false;
        }
        if ($request->category !== 'all' && $property->category !== $request->category) {
            return false;
        }

        return true;
    }

    public function canOffer(User $user, Property $property): bool
    {
        if ($property->user_id === $user->id && $user->isPublisher() && $user->status === User::STATUS_ACTIVE) {
            return true;
        }

        return AgentLink::query()
            ->where('property_id', $property->id)
            ->where('agent_id', $user->id)
            ->where('status', AgentLink::STATUS_APPROVED)
            ->exists();
    }

    private function notifyProfessionals(CommunityRequest $request): void
    {
        $properties = Property::query()
            ->where('status', Property::STATUS_ACTIVE)
            ->with(['owner', 'approvedAgents.agent'])
            ->get()
            ->filter(fn (Property $property) => $this->matches($request, $property));

        $notified = [];
        foreach ($properties as $property) {
            $sameNeighborhood = Normalizer::fingerprint($request->neighborhood) === Normalizer::fingerprint($property->neighborhood);
            $withinBudget = ! $request->budget || $property->price <= $request->budget;
            $users = collect([$property->owner])->merge($property->approvedAgents->pluck('agent'))->filter();
            foreach ($users as $user) {
                if (isset($notified[$user->id])) {
                    continue;
                }
                $notified[$user->id] = true;
                $this->notifications->notify(
                    $user,
                    'community_request',
                    'Pedido da comunidade',
                    'Um cliente procura um imóvel como o seu em '.$request->municipality.'.',
                    [
                        'request_id' => $request->id,
                        'property_id' => $property->id,
                        'same_neighborhood' => $sameNeighborhood,
                        'within_budget' => $withinBudget,
                    ]
                );
            }
        }
    }
}
