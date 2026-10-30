<?php

use App\Http\Middleware\EnsureActiveStaff;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Outside the web group: no session, cookies, CSRF or cache-backed throttling, so these
        // still work on a fresh database before the sessions and cache tables exist.
        then: fn () => Route::group([], base_path('routes/ops.php')),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureActiveStaff::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Serverless log viewers (Vercel) truncate long records, which cuts off the message of a
        // full stack trace. Log errors as one short line instead.
        if (env('LOG_CHANNEL') === 'stderr') {
            $exceptions->report(function (Throwable $e) {
                $frames = collect($e->getTrace())->take(6)
                    ->map(fn ($f) => str_replace(base_path().'/', '', ($f['file'] ?? '?').':'.($f['line'] ?? '?')))
                    ->implode(' < ');
                error_log(sprintf('HQ-ERROR %s: %s @ %s:%d | %s', $e::class, mb_substr($e->getMessage(), 0, 600),
                    str_replace(base_path().'/', '', $e->getFile()), $e->getLine(), $frames));
            })->stop();
        }

        // Friendly Inertia error pages instead of the framework's HTML pages.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (! app()->environment('local') && in_array($status, [403, 404, 419, 429, 500, 503], true) && ! $request->expectsJson()) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
