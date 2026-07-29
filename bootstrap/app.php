<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
 
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
 
use App\Exceptions\ApiException;
use App\Support\ValidationErrorCodeMapper;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi(limiter: 'api');
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain errors (App\Exceptions\ApiException and descendants) - a single contract point { message, error_code } for the entire API.
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'    => $e->getMessage(),
                    'error_code' => $e->errorCode(),
                ], $e->statusCode());
            }
        });

        // 422 - codes according to validation rules, not English Laravel text.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'    => 'The given data was invalid.',
                    'error_code' => 'validation.failed',
                    'errors'     => ValidationErrorCodeMapper::map($e),
                ], 422);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'    => $e->getMessage() ?: __('auth.invalid_verification_link'),
                    'error_code' => 'auth.invalid_verification_link',
                ], 403);
            }
        });

        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'    => __('auth.invalid_verification_link'),
                    'error_code' => 'auth.invalid_verification_link',
                ], 403);
            }
        });

        // Fallback - everything that is not caught above (500 and other unexpected things),
        // should also come in a single format, and not in the default Laravel HTML/JSON.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() && ! $e instanceof ApiException && ! $e instanceof ValidationException) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'message'    => $status === 500 ? 'Server error.' : $e->getMessage(),
                    'error_code' => $status === 500 ? 'server.internal_error' : 'server.error',
                ], $status);
            }
        });
    })
    
    ->create();
