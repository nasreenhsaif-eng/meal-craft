<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isCustomer() && ! $user->hasCompletedOnboarding()) {
            return redirect()->route(
                'onboarding.show',
                ['step' => $user->currentOnboardingStep()->value],
            );
        }

        return $next($request);
    }
}
