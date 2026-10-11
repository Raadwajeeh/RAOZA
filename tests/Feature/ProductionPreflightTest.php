<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ProductionPreflightTest extends TestCase
{
    public function test_preflight_fails_closed_outside_production_without_printing_secrets(): void
    {
        $this->app['config']->set('app.key', 'base64:do-not-print-this-value');
        $this->app['config']->set('database.connections.pgsql.password', 'do-not-print-database-password');
        $this->app['config']->set('payments.mollie.api_key', 'test_do-not-print-provider-key');

        $this->artisan('production:preflight', ['--skip-database' => true])
            ->expectsOutputToContain('Production preflight failed. No secret values were printed.')
            ->doesntExpectOutputToContain('do-not-print')
            ->assertFailed();
    }

    public function test_preflight_accepts_hardened_configuration_and_live_gate_is_separate(): void
    {
        $this->app->instance('env', 'production');
        foreach ([
            'app.debug' => false,
            'app.url' => 'https://raoza.nl',
            'app.key' => 'base64:configured-production-key',
            'production.release' => 'abcdef123456',
            'production.trusted_proxies' => '127.0.0.1,10.0.0.0/8',
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'db.internal.example',
            'database.connections.pgsql.database' => 'raoza_production',
            'database.connections.pgsql.username' => 'raoza_application',
            'database.connections.pgsql.password' => 'configured-password',
            'database.connections.pgsql.sslmode' => 'verify-full',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'queue.failed.driver' => 'database-uuids',
            'filesystems.default' => 'local',
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['stderr'],
            'payments.default' => 'mollie',
            'payments.mollie.api_key' => 'test_configured-key',
            'payments.mollie.base_url' => 'https://api.mollie.com/v2',
            'payments.creation_retry_minutes' => 45,
            'payments.creation_claim_minutes' => 2,
            'commerce.demo_mode' => false,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.mail-provider.test',
            'mail.mailers.smtp.username' => 'raoza-account',
            'mail.mailers.smtp.password' => 'configured-mail-password',
            'mail.from.address' => 'verified@raoza.nl',
            'retention.pruning_enabled' => false,
        ] as $key => $value) {
            $this->app['config']->set($key, $value);
        }

        $this->artisan('production:preflight', ['--skip-database' => true])
            ->expectsOutputToContain('Production preflight passed. This does not activate or test external services.')
            ->assertSuccessful();

        $this->artisan('production:preflight', ['--skip-database' => true, '--require-live-services' => true])
            ->expectsOutputToContain('Production preflight failed. No secret values were printed.')
            ->assertFailed();
    }
}
