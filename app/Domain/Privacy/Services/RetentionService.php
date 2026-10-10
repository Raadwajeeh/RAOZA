<?php

namespace App\Domain\Privacy\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class RetentionService
{
    /** @return array<string,array{configured:bool,days:?int,eligible:int}> */
    public function report(): array
    {
        $report = [];
        foreach ($this->definitions() as $category => $definition) {
            $days = $this->days($category);
            $report[$category] = [
                'configured' => $days !== null,
                'days' => $days,
                'eligible' => $days === null || ! Schema::hasTable($definition['table'])
                    ? 0
                    : $this->eligibleQuery($definition, $days)->count(),
            ];
        }

        return $report;
    }

    /** @return array<string,int> */
    public function prune(bool $confirmed): array
    {
        if (! config('retention.pruning_enabled')) {
            throw new RuntimeException('Retention pruning is disabled. Set RETENTION_PRUNING_ENABLED only after policy approval.');
        }
        if (! $confirmed) {
            throw new RuntimeException('Retention pruning requires explicit confirmation.');
        }

        return DB::transaction(function (): array {
            $deleted = [];
            foreach ($this->definitions() as $category => $definition) {
                $days = $this->days($category);
                $deleted[$category] = $days === null || ! Schema::hasTable($definition['table'])
                    ? 0
                    : $this->eligibleQuery($definition, $days)->delete();
            }

            return $deleted;
        }, 3);
    }

    /** @return array<string,array{table:string,column:string,unix?:bool}> */
    private function definitions(): array
    {
        return [
            'analytics_events' => ['table' => 'analytics_events', 'column' => 'occurred_at'],
            'sessions' => ['table' => 'sessions', 'column' => 'last_activity', 'unix' => true],
            'consent_records' => ['table' => 'consent_records', 'column' => 'consented_at'],
            'failed_jobs' => ['table' => 'failed_jobs', 'column' => 'failed_at'],
            'password_reset_tokens' => ['table' => 'password_reset_tokens', 'column' => 'created_at'],
        ];
    }

    /** @param array{table:string,column:string,unix?:bool} $definition */
    private function eligibleQuery(array $definition, int $days): Builder
    {
        $cutoff = now()->subDays($days);
        $value = ($definition['unix'] ?? false) ? $cutoff->timestamp : $cutoff;

        return DB::table($definition['table'])
            ->whereNotNull($definition['column'])
            ->where($definition['column'], '<=', $value);
    }

    private function days(string $category): ?int
    {
        $value = config("retention.categories.{$category}.days");
        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }
}
