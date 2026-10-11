<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        if (getenv('RAOZA_POSTGRES_TEST_OPT_IN') === 'phase7b3-disposable') {
            $required = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
            ];
            foreach ($required as $name => $value) {
                if (getenv($name) !== $value) {
                    throw new RuntimeException("Unsafe PostgreSQL test configuration refused: {$name} must equal {$value}.");
                }
            }
            $database = (string) getenv('DB_DATABASE');
            $username = (string) getenv('DB_USERNAME');
            if (! str_starts_with($database, 'raoza_phase7b3_') || ! str_starts_with($username, 'raoza_phase7b3_')) {
                throw new RuntimeException('Unsafe PostgreSQL test configuration refused: dedicated Phase 7B.3 database and role names are required.');
            }
            if (getenv('DB_URL') !== '' || getenv('DATABASE_URL') !== '') {
                throw new RuntimeException('Unsafe PostgreSQL test configuration refused: DB_URL and DATABASE_URL must be explicitly empty.');
            }

            $app = parent::createApplication();
            $identity = DB::selectOne(
                'select current_database() as database, current_user as username, inet_server_addr()::text as host, r.rolsuper from pg_roles r where r.rolname = current_user'
            );
            if ($identity->database !== $database || $identity->username !== $username || ! str_starts_with($identity->host, '127.0.0.1') || $identity->rolsuper) {
                throw new RuntimeException('PostgreSQL test connection identity validation failed.');
            }

            return $app;
        }

        // `php artisan test` boots Artisan before PHPUnit applies phpunit.xml.
        // Reassert the isolated connection before the test application is created.
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DATABASE_URL' => '',
        ] as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        $app = parent::createApplication();
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Unsafe PHPUnit database configuration refused. The default test suite requires APP_ENV=testing, DB_CONNECTION=sqlite and DB_DATABASE=:memory:.'
            );
        }

        return $app;
    }
}
