<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AgentLink;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentLinkService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function invite(User $publisher, Property $property, string $agentEmail, $commissionPct, ?string $terms): AgentLink
    {
        if ($property->user_id !== $publisher->id && ! $publisher->isAdmin()) {
            throw new BusinessException('Só o publicador pode convidar intermediários.', 'forbidden', 403);
        }
        if (! $publisher->isAdmin() && $publisher->status !== User::STATUS_ACTIVE) {
            throw new BusinessException('Conta não está aprovada.', 'account_pending', 403);
        }

        $agent = User::query()->where('email', strtolower($agentEmail))->where('role', User::ROLE_AGENT)->first();
        if (! $agent) {
            throw new BusinessException('Não existe um intermediário com este e-mail.', 'agent_not_found', 422);
        }
        if ($agent->id === $publisher->id) {
            throw new BusinessException('Não pode vincular-se a si próprio.', 'self_link', 422);
        }

        return DB::transaction(function () use ($publisher, $property, $agent, $commissionPct, $terms) {
            $active = AgentLink::query()
                ->where('property_id', $property->id)
                ->whereIn('status', [AgentLink::STATUS_PENDING, AgentLink::STATUS_APPROVED])
                ->lockForUpdate()
                ->get();

            if ($active->count() >= config('intermediarius.max_agents_per_property')) {
                throw new BusinessException('Este imóvel já tem 5 intermediários vinculados.', 'agent_limit', 422);
            }
            if ($active->contains(fn ($link) => $link->agent_id === $agent->id)) {
                throw new BusinessException('Este intermediário já está associado ao imóvel.', 'agent_duplicate', 409);
            }

            $existing = AgentLink::query()
                ->where('property_id', $property->id)
                ->where('agent_id', $agent->id)
                ->first();

            $payload = [
                'owner_id' => $property->user_id,
                'status' => AgentLink::STATUS_PENDING,
                'commission_pct' => $commissionPct,
                'terms' => $terms,
                'tracking_code' => $existing->tracking_code ?? $this->uniqueCode(),
                'status_changed_by' => $publisher->id,
                'status_changed_at' => now(),
            ];

            if ($existing) {
                $existing->fill($payload)->save();
                $link = $existing;
            } else {
                $link = AgentLink::create($payload + [
                    'property_id' => $property->id,
                    'agent_id' => $agent->id,
                ]);
            }

            $this->notifications->notify(
                $agent,
                'agent_invite',
                'Convite de intermediário',
                'Foi convidado para o imóvel '.$property->title.'.',
                ['agent_link_id' => $link->id, 'property_id' => $property->id]
            );

            return $link->load(['property', 'agent', 'owner']);
        });
    }

    public function changeStatus(User $actor, AgentLink $link, string $status): AgentLink
    {
        $allowed = match ($status) {
            AgentLink::STATUS_APPROVED, AgentLink::STATUS_REJECTED => $actor->id === $link->agent_id,
            AgentLink::STATUS_REVOKED => $actor->id === $link->owner_id || $actor->isAdmin(),
            default => false,
        };
        if (! $allowed) {
            throw new BusinessException('Transição de estado não permitida.', 'invalid_transition', 403);
        }
        if ($status === AgentLink::STATUS_APPROVED && $link->status !== AgentLink::STATUS_PENDING) {
            throw new BusinessException('Só convites pendentes podem ser aceites.', 'invalid_transition', 422);
        }
        if ($status === AgentLink::STATUS_REJECTED && $link->status !== AgentLink::STATUS_PENDING) {
            throw new BusinessException('Só convites pendentes podem ser recusados.', 'invalid_transition', 422);
        }
        if ($status === AgentLink::STATUS_REVOKED && in_array($link->status, [AgentLink::STATUS_REVOKED, AgentLink::STATUS_REJECTED], true)) {
            throw new BusinessException('Este vínculo já está encerrado.', 'invalid_transition', 422);
        }

        $link->status = $status;
        $link->status_changed_by = $actor->id;
        $link->status_changed_at = now();
        $link->save();

        $target = $actor->id === $link->agent_id ? $link->owner : $link->agent;
        $this->notifications->notify(
            $target,
            'agent_link_updated',
            'Vínculo de intermediário actualizado',
            'O estado do vínculo passou a '.$status.'.',
            ['agent_link_id' => $link->id, 'status' => $status]
        );

        return $link->fresh(['property', 'agent', 'owner']);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'AGT-'.random_int(100, 999).strtoupper(Str::random(2));
        } while (AgentLink::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
