<?php

use App\Http\Middleware\CustomerOnboardingMiddleware;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureOnboardingIncomplete;
use App\Http\Middleware\EnsurePortalChoiceAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->routeIs('login', 'join', 'register')) {
                return route('sign-out');
            }

            return $request->user()?->homePath() ?? route('login');
        });

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'onboarding.incomplete' => EnsureOnboardingIncomplete::class,
            'onboarding.step' => CustomerOnboardingMiddleware::class,
            'portal.choice' => EnsurePortalChoiceAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request): ?Response {
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with(
                    'error',
                    'Your session expired. Refresh the page (Cmd+Shift+R), then try again.',
                );
            }

            if ($request->is('login') || $request->routeIs('login', 'login.store')) {
                return redirect()
                    ->route('login')
                    ->withInput($request->only('email', 'remember'))
                    ->with('error', 'Your session expired. Please sign in again.');
            }

            return null;
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request): ?Response {
            if ($request->is('login') || $request->routeIs('login', 'login.store')) {
                return redirect()
                    ->route('login')
                    ->withInput($request->only('email', 'remember'))
                    ->with('error', 'Too many login attempts. Please wait a minute, then try again.');
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $exception, Request $request): ?Response {
            $message = 'The upload is too large for the server. Use a smaller photo, or increase PHP post_max_size and upload_max_filesize (Herd: run herd ini).';

            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return redirect()->back()->with('error', $message);
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            // Match by path so CSRF/auth failures still return JSON even if the route name is not resolved yet.
            if (in_array($request->path(), ['ingredient-analysis', 'ingredients/from-analysis', 'meals/library/import-csv'], true)) {
                return true;
            }

            if (str_starts_with($request->path(), 'ingredient-analysis/')) {
                return true;
            }

            if ($request->routeIs('ingredient-analysis', 'ingredient-analysis.status', 'ingredients.from-analysis')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
