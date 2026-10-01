<?php

namespace App\Console\Commands;

use App\Domain\Commerce\Services\PendingOrderExpirationService;
use Illuminate\Console\Command;

final class ExpirePendingOrders extends Command
{
    protected $signature = 'commerce:expire-pending-orders {--limit=100 : Maximum orders to inspect}';
    protected $description = 'Cancel eligible abandoned unpaid orders and release their reservations';

    public function handle(PendingOrderExpirationService $service): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('The --limit value must be an integer between 1 and 500.');
            return self::INVALID;
        }
        $summary = $service->expire($limit);
        $this->table(['Checked', 'Expired', 'Skipped'], [array_values($summary)]);
        return self::SUCCESS;
    }
}
