<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureMerchantAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\RequirePassword;
use App\Models\ApiKey;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\MerchantInvitation;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Settlement;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\ClientAddress;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureRateLimiting();

        // Re-run tenant, admin and password-confirmation checks on every Livewire update request.
        Livewire::addPersistentMiddleware([
            EnsureEmailIsVerified::class,
            RequirePassword::class,
            EnsureMerchantAccount::class,
            EnsurePlatformAdmin::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        if (app()->isProduction()) {
            URL::forceScheme('https');
            // Absolute links in email (password reset, verification) always use our own address, never the Host header.
            URL::forceRootUrl((string) config('app.url'));
        }

        if (config('security.trusted_proxies') !== []) {
            TrustProxies::at(config('security.trusted_proxies'));
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Strict models outside production, and stable morph aliases instead of class names in the database.
     */
    protected function configureModels(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'merchant' => Merchant::class,
            'merchant_invitation' => MerchantInvitation::class,
            'merchant_document' => MerchantDocument::class,
            'api_key' => ApiKey::class,
            'webhook_endpoint' => WebhookEndpoint::class,
            'customer' => Customer::class,
            'payment_method' => PaymentMethod::class,
            'payment_link' => PaymentLink::class,
            'checkout_session' => CheckoutSession::class,
            'payment_intent' => PaymentIntent::class,
            'payment' => Payment::class,
            'refund' => Refund::class,
            'settlement' => Settlement::class,
            'plan' => Plan::class,
            'subscription' => Subscription::class,
            'subscription_invoice' => SubscriptionInvoice::class,
        ]);
    }

    /**
     * Rate limits for the gateway API and the public payment pages.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('gateway-api', function (Request $request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute((int) config('gateway.api.rate_limit_per_minute'))
                ->by($key instanceof ApiKey ? 'key:'.$key->id : 'ip:'.$request->ip());
        });

        // Runs before authentication, so guessing or flooding with bad keys is capped per address too.
        RateLimiter::for('gateway-api-ip', fn (Request $request) => Limit::perMinute((int) config('gateway.api.ip_rate_limit_per_minute'))->by('ip:'.$request->ip()));

        RateLimiter::for('checkout-pay', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perHour(20)->by('session:'.self::routeKey($request, 'session')),
        ]);

        RateLimiter::for('checkout-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('payment:'.self::routeKey($request, 'payment')),
        ]);

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(5)->by('user:'.$request->user()?->id));

        RateLimiter::for('checkout-return', fn (Request $request) => [
            Limit::perMinute(30)->by('ip:'.$request->ip()),
            Limit::perMinute(10)->by('attempt:'.self::routeKey($request, 'attempt')),
        ]);

        RateLimiter::for('sandbox-bank', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('ref:'.substr(hash('sha256', (string) $request->input('p')), 0, 32)),
        ]);

        // Each start creates a lasting checkout session and webhook, so a public link is also capped as a whole.
        RateLimiter::for('payment-link', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.ClientAddress::key($request)),
            Limit::perHour(120)->by('link:'.self::routeKey($request, 'link')),
        ]);
    }

    private static function routeKey(Request $request, string $parameter): string
    {
        $value = $request->route($parameter);

        return $value instanceof Model ? (string) $value->getRouteKey() : (string) $value;
    }
}
