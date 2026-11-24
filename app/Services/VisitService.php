<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AgentLink;
use App\Models\Availability;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitService
{
    public function __construct(
        private PaymentService $payments,
        private SettingsService $settings,
        private NotificationService $notifications
    ) {
    }

    public function create(User $client, Property $property, Carbon $scheduledAt, string $method): Visit
    {
        if ($property->status !== Property::STATUS_ACTIVE) {
            throw new BusinessException('Só é possível visitar imóveis activos.', 'property_inactive', 422);
        }

        $this->expireHolds();

        $visit = DB::transaction(function () use ($client, $property, $scheduledAt) {
            $agent = $this->pickAgent($property, $scheduledAt);
            if (! $agent) {
                throw new BusinessException('Não há intermediário disponível neste horário.', 'slot_taken', 422);
            }

            $conflict = Visit::query()
                ->where(function ($q) use ($property, $agent, $scheduledAt) {
                    $q->where(function ($q2) use ($property, $scheduledAt) {
                        $q2->where('property_id', $property->id)->where('scheduled_at', $scheduledAt);
                    })->orWhere(function ($q2) use ($agent, $scheduledAt) {
                        $q2->where('agent_id', $agent->id)->where('scheduled_at', $scheduledAt);
                    });
                })
                ->where(function ($q) {
                    $q->whereIn('status', [Visit::STATUS_PAID, Visit::STATUS_COMPLETED])
                        ->orWhere(function ($q2) {
                            $q2->where('status', Visit::STATUS_PENDING_PAYMENT)
                                ->where('hold_expires_at', '>', now());
                        });
                })
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new BusinessException('Este horário já está reservado.', 'slot_taken', 422);
            }

            $visit = Visit::create([
                'user_id' => $client->id,
                'property_id' => $property->id,
                'agent_id' => $agent->id,
                'scheduled_at' => $scheduledAt,
                'status' => Visit::STATUS_PENDING_PAYMENT,
                'hold_expires_at' => now()->addMinutes(config('intermediarius.visit_hold_minutes')),
            ]);

            return $visit;
        });

        try {
            $this->payments->initiate(
                $visit,
                PaymentService::PURPOSE_VISIT,
                $this->settings->visitFee(),
                $method,
                $client
            );
        } catch (BusinessException $exception) {
            if (! $visit->payment()->exists()) {
                $visit->status = Visit::STATUS_CANCELLED;
                $visit->hold_expires_at = null;
                $visit->save();
            }

            throw $exception;
        }

        return $visit->load(['property.media', 'agent', 'payment']);
    }

    public function confirmPaid(Visit $visit): void
    {
        if (in_array($visit->status, [Visit::STATUS_PAID, Visit::STATUS_COMPLETED], true)) {
            return;
        }

        $visit->status = Visit::STATUS_PAID;
        $visit->paid_at = now();
        $visit->code = $this->uniqueVisitCode();
        $visit->hold_expires_at = null;
        $visit->save();

        foreach ([$visit->client, $visit->agent, $visit->property->owner] as $user) {
            if ($user) {
                $this->notifications->notify(
                    $user,
                    'visit_paid',
                    'Visita confirmada',
                    'O pagamento da visita foi confirmado.',
                    ['visit_id' => $visit->id]
                );
            }
        }
    }

    public function checkin(User $agent, string $code): Visit
    {
        $visit = Visit::query()->where('code', strtoupper(trim($code)))->first();
        if (! $visit) {
            throw new BusinessException('Código de visita inválido.', 'invalid_code', 422);
        }
        if ($visit->agent_id !== $agent->id) {
            throw new BusinessException('Só o intermediário associado pode validar o check-in.', 'forbidden', 403);
        }
        if ($visit->status === Visit::STATUS_COMPLETED) {
            throw new BusinessException('Este código já foi utilizado.', 'code_reused', 409);
        }
        if ($visit->status !== Visit::STATUS_PAID) {
            throw new BusinessException('A visita ainda não tem pagamento confirmado.', 'unpaid', 422);
        }

        $visit->status = Visit::STATUS_COMPLETED;
        $visit->checked_in_at = now();
        $visit->save();

        return $visit->load(['property', 'client', 'agent']);
    }

    public function expireHolds(): void
    {
        Visit::query()
            ->where('status', Visit::STATUS_PENDING_PAYMENT)
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => Visit::STATUS_EXPIRED]);
    }

    private function pickAgent(Property $property, Carbon $scheduledAt): ?User
    {
        $agentIds = AgentLink::query()
            ->where('property_id', $property->id)
            ->where('status', AgentLink::STATUS_APPROVED)
            ->pluck('agent_id');

        if ($property->owner && $property->owner->role === User::ROLE_AGENT) {
            $agentIds = $agentIds->push($property->user_id)->unique();
        }

        $isoDay = (int) $scheduledAt->isoWeekday();
        $time = $scheduledAt->format('H:i');

        foreach ($agentIds as $agentId) {
            $availability = Availability::query()->where('user_id', $agentId)->first();
            if (! $availability || ! in_array($isoDay, $availability->days ?? [], false)) {
                continue;
            }
            if ($time < $availability->start || $time >= $availability->end) {
                continue;
            }
            $minutes = ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
            $start = ((int) substr($availability->start, 0, 2)) * 60 + (int) substr($availability->start, 3, 2);
            if (($minutes - $start) % (int) $availability->slot !== 0) {
                continue;
            }

            return User::find($agentId);
        }

        return null;
    }

    private function uniqueVisitCode(): string
    {
        do {
            $code = 'INT-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Visit::query()->where('code', $code)->exists());

        return $code;
    }
}
