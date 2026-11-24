<?php

namespace App\Providers;

use App\Exceptions\BusinessException;
use App\Models\Commission;
use App\Models\Rental;
use App\Models\User;
use App\Models\Visit;
use App\Services\Payments\GpoFaciGateway;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(PaymentGateway::class, function ($app) {
            if (config('intermediarius.payment_gateway') === 'gpo') {
                return new GpoFaciGateway();
            }

            if (config('intermediarius.payment_gateway') === 'fake' && $app->environment('local', 'testing')) {
                return new FakePaymentGateway();
            }

            if (config('intermediarius.payment_gateway') === 'fake') {
                throw new BusinessException(
                    'O gateway fake só pode ser usado em local/testing.',
                    'fake_gateway_disabled',
                    503
                );
            }

            throw new BusinessException('PAYMENT_GATEWAY inválido ou não configurado.', 'gateway_unconfigured', 503);
        });
    }

    public function boot()
    {
        JsonResource::withoutWrapping();
        Relation::enforceMorphMap([
            'user' => User::class,
            'visit' => Visit::class,
            'rental' => Rental::class,
            'commission' => Commission::class,
        ]);
    }
}
