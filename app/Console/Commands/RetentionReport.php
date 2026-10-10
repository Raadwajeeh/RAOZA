<?php

namespace App\Console\Commands;

use App\Domain\Privacy\Services\RetentionService;
use Illuminate\Console\Command;
use RuntimeException;

final class RetentionReport extends Command
{
    protected $signature = 'privacy:retention-report
        {--execute : Delete eligible non-financial runtime data}
        {--confirm : Confirm an explicitly configured destructive run}';

    protected $description = 'Report stale runtime/privacy data; pruning is opt-in and disabled by default';

    public function handle(RetentionService $service): int
    {
        if (! $this->option('execute')) {
            $report = $service->report();
            $this->table(['Category', 'Configured days', 'Eligible rows'], collect($report)->map(
                fn (array $row, string $category) => [$category, $row['days'] ?? 'NOT SET', $row['eligible']],
            )->values()->all());
            $this->warn('Dry run only. Financial, order, payment, refund, inventory and audit records are excluded.');

            return self::SUCCESS;
        }

        try {
            $deleted = $service->prune((bool) $this->option('confirm'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Category', 'Deleted rows'], collect($deleted)->map(fn (int $count, string $category) => [$category, $count])->values()->all());

        return self::SUCCESS;
    }
}
