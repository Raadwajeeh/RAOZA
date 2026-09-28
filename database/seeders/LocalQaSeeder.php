<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocalQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Local QA seed skipped outside local/testing.');

            return;
        }

        $this->call(DatabaseSeeder::class);
    }
}
