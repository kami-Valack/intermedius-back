<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AgentLink;
use App\Models\Commission;
use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class RentalService
{
    public function __construct(
        private PaymentService $payments,
        private SettingsService $settings,
        private NotificationService $notifications
    ) {
    }

    public function quote(Property $property): array
    {
        $rent = (int) $property->price;
        $platformPct = $this->settings->platformCommissionPct();
        $platformFee = Money::percentOf($rent, $platformPct);
        $link = $property->approvedAgents()->orderBy('id')->first();
        $agentPct = $link ? (string) $link->commission_pct : $this->settings->agentCommissionPct();
        $agentAmount = $link ? Money::percentOf($rent, $agentPct) : 0;

        return [
            'monthly_rent' => $rent,
            'platform_pct' => $platformPct,
            'platform_fee' => $platformFee,
            'tenant_total' => $rent + $platformFee,
            'agent_commission_pct' => $link ? $agentPct : '0.00',
            'agent_commission_amount' => $agentAmount,
            'agent_id' => $link?->agent_id,
        ];
    }

    public function create(User $tenant, Property $property, array $data): Rental
    {
        if ($property->status !== Property::STATUS_ACTIVE || $property->category !== 'rent') {
            throw new BusinessException('Este imóvel não está disponível para arrendamento.', 'property_inactive', 422);
        }

        $quote = $this->quote($property);

        $rental = DB::transaction(function () use ($tenant, $property, $data, $quote) {
            return Rental::create([
                'user_id' => $tenant->id,
                'property_id' => $property->id,
                'owner_id' => $property->user_id,
                'agent_id' => $quote['agent_id'],
                'tenant_name' => $data['tenant'],
                'tenant_phone' => $data['phone'],
                'tenant_nif' => $data['nif'],
                'start_date' => $data['start_date'],
                'duration_months' => $data['duration_months'],
                'monthly_rent' => $quote['monthly_rent'],
                'platform_pct' => $quote['platform_pct'],
                'platform_fee' => $quote['platform_fee'],
                'tenant_total' => $quote['tenant_total'],
                'agent_commission_pct' => $quote['agent_commission_pct'],
                'agent_commission_amount' => $quote['agent_commission_amount'],
                'status' => Rental::STATUS_PENDING_PAYMENT,
            ]);

        });

        try {
            $this->payments->initiate(
                $rental,
                PaymentService::PURPOSE_RENTAL,
                $quote['tenant_total'],
                $data['payment_method'],
                $tenant
            );
        } catch (BusinessException $exception) {
            if (! $rental->payment()->exists()) {
                $rental->status = Rental::STATUS_CANCELLED;
                $rental->save();
            }

            throw $exception;
        }

        return $rental->load(['property', 'payment']);
    }

    public function activate(Rental $rental): void
    {
        if ($rental->status === Rental::STATUS_ACTIVE) {
            return;
        }

        $rental->status = Rental::STATUS_ACTIVE;
        $rental->activated_at = now();
        $rental->contract_number = $this->uniqueContractNumber();
        $rental->save();

        $rental->property->status = Property::STATUS_RENTED;
        $rental->property->save();

        if ($rental->agent_id && $rental->agent_commission_amount > 0) {
            Commission::create([
                'rental_id' => $rental->id,
                'property_id' => $rental->property_id,
                'owner_id' => $rental->owner_id,
                'agent_id' => $rental->agent_id,
                'pct' => $rental->agent_commission_pct,
                'amount' => $rental->agent_commission_amount,
                'status' => Commission::STATUS_DUE,
            ]);
        }

        foreach (array_filter([$rental->tenant, $rental->owner, $rental->agent]) as $user) {
            $this->notifications->notify(
                $user,
                'rental_activated',
                'Contrato de arrendamento emitido',
                'O pagamento foi confirmado e o contrato '.$rental->contract_number.' foi gerado. Este documento não constitui aconselhamento jurídico.',
                ['rental_id' => $rental->id]
            );
        }
    }

    public function payCommission(User $owner, Commission $commission, string $method = 'reference'): Commission
    {
        if ($owner->id !== $commission->owner_id && ! $owner->isAdmin()) {
            throw new BusinessException('Só o proprietário pode liquidar esta comissão.', 'forbidden', 403);
        }
        if ($commission->status === Commission::STATUS_PAID) {
            throw new BusinessException('Comissão já liquidada.', 'already_paid', 409);
        }

        $commission->status = Commission::STATUS_PENDING_PAYMENT;
        $commission->save();

        try {
            $this->payments->initiate(
                $commission,
                PaymentService::PURPOSE_COMMISSION,
                (int) $commission->amount,
                $method,
                $owner
            );
        } catch (BusinessException $exception) {
            if (! $commission->payment()->exists()) {
                $commission->status = Commission::STATUS_DUE;
                $commission->save();
            }

            throw $exception;
        }

        return $commission->load('payment');
    }

    private function uniqueContractNumber(): string
    {
        do {
            $number = 'CTR-'.now()->year.'-'.random_int(1000, 9999);
        } while (Rental::query()->where('contract_number', $number)->exists());

        return $number;
    }
}
