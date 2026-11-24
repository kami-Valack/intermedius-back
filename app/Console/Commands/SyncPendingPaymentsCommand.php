<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class SyncPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:sync-pending {--limit=100}';

    protected $description = 'Consulta estados de pagamentos pendentes na GPO FACI.';

    public function handle(PaymentService $payments): int
    {
        $count = $payments->syncPendingPayments((int) $this->option('limit'));
        $this->info($count.' pagamento(s) confirmado(s) pela GPO FACI.');

        return self::SUCCESS;
    }
}
