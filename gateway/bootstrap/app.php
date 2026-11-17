<?php

use App\Exceptions\GatewayException;
use App\Http\Api\ApiResponse;
use App\Http\Middleware\ConvertArabicDigits;
use App\Http\Middleware\EnsureMerchantAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\RememberPanel;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ConvertArabicDigits::class,
            SetLocale::class,
            SecurityHeaders::class,
            EnsureUserIsNotBanned::class,
        ]);

        // Banks post the cardholder back from their own domain; the callback is authenticated by the bank's signature.
        $middleware->validateCsrfTokens(except: ['pay/return/*']);

        // Password reset and verification links are built from the Host header, so only our own host is
        // accepted (Laravel skips this check locally and in tests).
        $middleware->trustHosts(at: fn (): array => array_filter([parse_url((string) config('app.url'), PHP_URL_HOST)]));

        $middleware->alias([
            'merchant' => EnsureMerchantAccount::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'panel' => RememberPanel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([GatewayException::class]);

        $exceptions->dontFlash(['card_number', 'cvc', 'expiry', 'code', 'password', 'password_confirmation', 'current_password']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (GatewayException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->apiCode, $exception->getMessage(), $exception->httpStatus, $exception->data);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors[] = ['field' => $field, 'message' => $messages[0]];
            }

            return ApiResponse::error(4000, 'Invalid information', 400, ['error' => $errors]);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return $request->is('api/*') ? ApiResponse::error(4010, 'No valid API key provided.', 401) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiResponse::error(4290, 'Too many requests. Retry with exponential backoff.', 429)->withHeaders($exception->getHeaders())
                : null;
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $exception, Request $request) {
            return $request->is('api/*') ? ApiResponse::error(4003, 'object not found', 404) : null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            return $request->is('api/*') ? ApiResponse::error(4050, 'This HTTP method is not supported for this endpoint.', 405)->withHeaders($exception->getHeaders()) : null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

            return ApiResponse::error($status >= 500 ? 5000 : 4000, $status >= 500 ? 'Something went wrong on our side.' : 'Request failed.', $status);
        });
    })->create();
