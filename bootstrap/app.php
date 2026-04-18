<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            // Match by path so CSRF/auth failures still return JSON even if the route name is not resolved yet.
            if (in_array($request->path(), ['ingredient-analysis', 'ingredients/from-analysis'], true)) {
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
