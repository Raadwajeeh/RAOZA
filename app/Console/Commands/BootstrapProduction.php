<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Services\ProductionBootstrapService;
use Illuminate\Console\Command;
use RuntimeException;

final class BootstrapProduction extends Command
{
    protected $signature = 'production:bootstrap {manifest : Absolute or repository-relative JSON manifest} {--dry-run : Validate without writing} {--confirm : Explicitly authorize applying reviewed data}';
    protected $description = 'Validate or apply a reviewed production catalog and reference-data manifest';

    public function handle(ProductionBootstrapService $service): int
    {
        $path = (string) $this->argument('manifest');
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) $path = base_path($path);
        try {
            $validated = $service->validateManifest($path);
            $this->table(['Category','Count'], collect($validated['summary'])->map(fn ($count,$name)=>[$name,$count])->values()->all());
            if ($this->option('dry-run') || ! $this->option('confirm')) {
                $this->info('Manifest validation passed; no data was written. Use --confirm to apply it.');
                return self::SUCCESS;
            }
            $service->apply($validated['data']);
            $this->info('Reviewed production bootstrap data applied successfully.');
            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
