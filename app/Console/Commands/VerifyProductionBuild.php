<?php

namespace App\Console\Commands;

use App\Support\ViteBuildVerifier;
use Illuminate\Console\Command;
use RuntimeException;

final class VerifyProductionBuild extends Command
{
    protected $signature = 'production:verify-build {path=public/build : Build directory, absolute or relative to the application root}';

    protected $description = 'Verify that the Vite manifest and every referenced production asset are present';

    public function handle(ViteBuildVerifier $verifier): int
    {
        $path = (string) $this->argument('path');
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        try {
            $result = $verifier->verify($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Vite build verified: {$result['entries']} manifest entries, {$result['files']} files.");

        return self::SUCCESS;
    }
}
