<?php

namespace App\Http\Middleware;

use App\Enums\OnboardingStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIncomplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isCustomer() && $user->hasCompletedOnboarding()) {
            $step = $request->route('step');

            if (
                $request->routeIs('onboarding.show')
                && is_string($step)
                && OnboardingStep::tryFrom($step) === OnboardingStep::FoodFilters
            ) {
                return $next($request);
            }

            return redirect()->route('app.home');
        }

        return $next($request);
    }
}
