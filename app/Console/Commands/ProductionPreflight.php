<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProductionPreflight extends Command
{
    protected $signature = 'production:preflight
        {--require-live-services : Require a Mollie Live key rather than a test key}
        {--skip-database : Validate resolved configuration without querying the selected database}';

    protected $description = 'Fail-closed validation of resolved production deployment configuration';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $canonicalUrl = (string) config('production.canonical_url');
        $driver = (string) config('database.default');
        $database = config("database.connections.{$driver}", []);
        $paymentKey = (string) config('payments.mollie.api_key');
        $mailer = (string) config('mail.default');
        $smtp = config('mail.mailers.smtp', []);
        $mailFrom = (string) config('mail.from.address');
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) config('production.trusted_proxies')))));

        $checks = [
            'Application environment is production' => app()->environment('production'),
            'Debug mode is disabled' => config('app.debug') === false,
            'Canonical URL is the explicitly configured HTTPS URL' => $url === $canonicalUrl
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_HOST) !== null,
            'Application key is present and not a template value' => $this->isConfiguredSecret((string) config('app.key')),
            'Release identifier is configured' => ! in_array((string) config('production.release'), ['', 'unknown', 'local', 'GIT_SHA_OR_RELEASE_ID'], true),
            'Trusted proxies are empty for a direct edge or explicitly allowlisted' => $proxies === []
                || collect($proxies)->every(fn (string $proxy): bool => $this->validProxy($proxy)),
            'Selected database driver is supported' => in_array($driver, ['pgsql', 'mysql'], true),
            'Database application credentials are configured' => $this->isConfigured((string) ($database['host'] ?? ''))
                && $this->isConfigured((string) ($database['database'] ?? ''))
                && $this->isConfigured((string) ($database['username'] ?? ''))
                && $this->isConfiguredSecret((string) ($database['password'] ?? '')),
            'Database connection policy is hardened' => $this->databasePolicyIsHardened($driver, $database),
            'Database sessions are encrypted and secure' => config('session.driver') === 'database'
                && config('session.encrypt') === true
                && config('session.secure') === true
                && config('session.http_only') === true
                && in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Shared cache and durable database queue are configured' => in_array(config('cache.default'), ['database', 'redis'], true)
                && config('queue.default') === 'database'
                && config('queue.failed.driver') === 'database-uuids',
            'Persistent local media disk is selected' => config('filesystems.default') === 'local',
            'Production logs use captured stderr' => config('logging.default') === 'stack'
                && in_array('stderr', config('logging.channels.stack.channels', []), true),
            'Mollie is selected and demo mode is disabled' => config('payments.default') === 'mollie'
                && config('commerce.demo_mode') === false
                && config('payments.mollie.base_url') === 'https://api.mollie.com/v2',
            'Mollie credential has an expected non-template prefix' => $this->validMollieKey($paymentKey),
            'Payment recovery window is bounded below one hour' => config('payments.creation_retry_minutes') >= 1
                && config('payments.creation_retry_minutes') <= 55
                && config('payments.creation_claim_minutes') >= 1,
            'Transactional mail transport and sender are configured' => $mailer === 'smtp'
                && in_array((string) ($smtp['scheme'] ?? ''), ['smtp', 'smtps'], true)
                && $this->isConfigured((string) ($smtp['host'] ?? ''))
                && $this->isConfigured((string) ($smtp['username'] ?? ''))
                && $this->isConfiguredSecret((string) ($smtp['password'] ?? ''))
                && filter_var($mailFrom, FILTER_VALIDATE_EMAIL) !== false
                && ! preg_match('/(?:\.invalid|\.test|@example\.(?:com|net|org))$/i', $mailFrom),
            'Destructive retention pruning remains disabled at launch' => config('retention.pruning_enabled') === false,
        ];

        if ($this->option('require-live-services')) {
            $checks['Mollie Live credential is selected'] = str_starts_with($paymentKey, 'live_');
        }

        if (! $this->option('skip-database')) {
            try {
                $checks['Selected database is reachable and compatible'] = $this->databaseIsCompatible($driver);
            } catch (Throwable) {
                $checks['Selected database is reachable and compatible'] = false;
            }
        }

        $this->table(['Check', 'Result'], collect($checks)->map(
            fn (bool $passed, string $name): array => [$name, $passed ? 'PASS' : 'FAIL']
        )->values()->all());

        if (in_array(false, $checks, true)) {
            $this->error('Production preflight failed. No secret values were printed.');

            return self::FAILURE;
        }

        $this->info('Production preflight passed. This does not activate or test external services.');

        return self::SUCCESS;
    }

    private function validMollieKey(string $value): bool
    {
        return $this->isConfiguredSecret($value)
            && (str_starts_with($value, 'test_') || str_starts_with($value, 'live_'));
    }

    private function databasePolicyIsHardened(string $driver, array $database): bool
    {
        if ($driver === 'pgsql') {
            return in_array((string) ($database['sslmode'] ?? ''), ['require', 'verify-ca', 'verify-full'], true);
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $host = strtolower((string) ($database['host'] ?? ''));
        $localTransport = in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || $this->isConfigured((string) ($database['unix_socket'] ?? ''));
        $tlsConfigured = array_key_exists(\PDO::MYSQL_ATTR_SSL_CA, (array) ($database['options'] ?? []));

        return ($localTransport || $tlsConfigured)
            && ($database['charset'] ?? null) === 'utf8mb4'
            && in_array((string) ($database['collation'] ?? ''), ['utf8mb4_unicode_ci', 'utf8mb4_unicode_520_ci'], true)
            && ($database['strict'] ?? false) === true
            && ($database['engine'] ?? null) === 'InnoDB'
            && strtoupper((string) ($database['isolation_level'] ?? '')) === 'READ COMMITTED'
            && ($database['timezone'] ?? null) === '+00:00';
    }

    private function databaseIsCompatible(string $driver): bool
    {
        if ($driver === 'pgsql') {
            DB::select('select 1');

            return true;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $identity = DB::selectOne('select version() as version, @@default_storage_engine as engine, @@session.time_zone as timezone, @@tx_isolation as isolation_level');
        if (! str_contains(strtolower((string) ($identity->version ?? '')), 'mariadb')
            || strcasecmp((string) ($identity->engine ?? ''), 'InnoDB') !== 0
            || strtoupper((string) ($identity->isolation_level ?? '')) !== 'READ-COMMITTED'
            || (string) ($identity->timezone ?? '') !== '+00:00') {
            return false;
        }

        preg_match('/^(\d+)\.(\d+)\./', (string) $identity->version, $matches);

        return isset($matches[1], $matches[2])
            && version_compare($matches[1].'.'.$matches[2], '10.3', '>=');
    }

    private function validProxy(string $value): bool
    {
        if ($value === '*' || ! $this->isConfigured($value)) {
            return false;
        }
        [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($prefix === null) {
            return true;
        }
        $maximum = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix <= $maximum;
    }

    private function isConfiguredSecret(string $value): bool
    {
        return $this->isConfigured($value) && ! str_contains(strtoupper($value), 'SECRET');
    }

    private function isConfigured(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        foreach (['OWNER_', 'OPERATOR_', 'PRODUCTION_', 'GIT_SHA_', 'SELECTED_', 'SUPPLIED_'] as $marker) {
            if (str_contains(strtoupper($value), $marker)) {
                return false;
            }
        }

        return true;
    }
}
