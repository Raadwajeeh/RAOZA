<?php

namespace App\Console\Commands;

use App\Domain\Commerce\Services\CommerceReconciliationService;
use Illuminate\Console\Command;

class ReconcileCommerce extends Command
{
    protected $signature = 'commerce:reconcile {--limit=50 : Maximum payments and refunds to inspect per run}';

    protected $description = 'Reconcile unsettled provider payments and refunds safely';

    public function handle(CommerceReconciliationService $service): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 200) {
            $this->error('The --limit value must be an integer between 1 and 200.');

            return self::INVALID;
        }

        $summary = $service->reconcile($limit);
        $this->table(['Payments checked', 'Payments changed', 'Refunds checked', 'Refunds changed', 'Failures'], [[
            $summary['payments_checked'],
            $summary['payments_changed'],
            $summary['refunds_checked'],
            $summary['refunds_changed'],
            $summary['failures'],
        ]]);

        return $summary['failures'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
