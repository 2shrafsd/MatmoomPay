<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fails the deploy when the environment is not safe to take real payments.
 */
#[Signature('gateway:check-production')]
#[Description('Check that the configuration is safe for live payments')]
class CheckProductionReadiness extends Command
{
    public function handle(): int
    {
        $checks = [
            ['APP_ENV is production', app()->isProduction(), true],
            ['APP_DEBUG is off', config('app.debug') === false, true],
            ['APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), true],
            ['APP_KEY is set', filled(config('app.key')), true],
            ['Sessions are encrypted', config('session.encrypt') === true, true],
            ['Session cookie is secure', config('session.secure') === true, true],
            ['Queue runs in the background', ! in_array(config('queue.default'), ['sync', 'null'], true), true],
            ['Mail is really sent', ! in_array(config('mail.default'), ['log', 'array'], true), true],
            ['Cache is shared', config('cache.default') !== 'array', true],
            ['Content-Security-Policy is enforced', config('security.csp_report_only') === false, true],
            ['Admins must use two-factor', config('security.admin_requires_two_factor') === true, true],
            ['Webhooks cannot reach private hosts', config('gateway.webhooks.allow_private_hosts') === false, true],
            ['A platform admin with two-factor exists', User::query()->where('is_platform_admin', true)->whereNotNull('two_factor_confirmed_at')->exists(), true],
            ['Logs are not at debug level', ! $this->logsAtDebugLevel(), true],
            ['The database is MySQL or PostgreSQL, not SQLite', config('database.default') !== 'sqlite', false],
            ['Proxies are not trusted blindly', ! in_array('*', (array) config('security.trusted_proxies'), true), true],
            ['Links are built from APP_URL only', filled(parse_url((string) config('app.url'), PHP_URL_HOST)), true],
            ['Mail has a real sender address', ! str_ends_with((string) config('mail.from.address'), '@example.com'), true],
            ['The scheduler ran in the last five minutes', (int) Cache::get(ReconcilePayments::HEARTBEAT_KEY, 0) >= now()->subMinutes(5)->getTimestamp(), true],
            ['The GD extension is installed (QR code images)', extension_loaded('gd'), true],
            ['Identity documents are on shared storage', config('gateway.documents_disk') !== 'local', false],
            ['A live card processor is configured', config('gateway.processors.live') !== 'unconfigured', false],
        ];

        $failed = false;

        $this->table(['Check', 'Result'], array_map(function (array $check) use (&$failed): array {
            [$label, $passed, $required] = $check;
            $failed = $failed || ($required && ! $passed);

            return [$label, $passed ? 'OK' : ($required ? 'FAIL' : 'WARN')];
        }, $checks));

        if ($failed) {
            $this->error('Fix the failed checks before accepting live payments.');

            return self::FAILURE;
        }

        $this->info('The configuration is ready for production.');

        return self::SUCCESS;
    }

    /**
     * Debug logs can contain request data; production should log at info or above.
     */
    private function logsAtDebugLevel(): bool
    {
        $default = (string) config('logging.default');
        $channels = $default === 'stack' ? (array) config('logging.channels.stack.channels') : [$default];

        return collect($channels)->contains(fn (string $channel): bool => config("logging.channels.{$channel}.level", 'debug') === 'debug');
    }
}
