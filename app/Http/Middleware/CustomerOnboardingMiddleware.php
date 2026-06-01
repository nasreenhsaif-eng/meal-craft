<?php

namespace App\Http\Middleware;

use App\Enums\OnboardingStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerOnboardingMiddleware
{
    /**
     * Lock GET onboarding pages to the customer's saved step.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isCustomer() || $user->hasCompletedOnboarding()) {
            return $next($request);
        }

        $stepParam = $request->route('step');

        if (! is_string($stepParam) || $stepParam === '') {
            return $next($request);
        }

        try {
            $requestedStep = OnboardingStep::normalizeStoredStep(OnboardingStep::from($stepParam));
        } catch (\ValueError) {
            abort(404);
        }

        $currentStep = OnboardingStep::normalizeStoredStep($user->currentOnboardingStep());

        if ($requestedStep !== $currentStep) {
            return redirect()->route('onboarding.show', ['step' => $currentStep->value]);
        }

        if ($requestedStep === OnboardingStep::PeriodTracking
            && ! OnboardingStep::shouldShowPeriodTracking($user->customerProfile)) {
            return redirect()->route('onboarding.show', ['step' => $currentStep->value]);
        }

        return $next($request);
    }
}
