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

        if ($stepParam === OnboardingStep::Welcome->value) {
            return redirect()->route('onboarding.show', ['step' => OnboardingStep::Gender->value]);
        }

        try {
            $requestedStep = OnboardingStep::normalizeStoredStep(OnboardingStep::from($stepParam));
        } catch (\ValueError) {
            abort(404);
        }

        $profile = $user->customerProfile;
        $currentStep = OnboardingStep::normalizeStoredStep($user->currentOnboardingStep());

        if (! OnboardingStep::shouldShowPeriodTracking($profile)) {
            if ($currentStep === OnboardingStep::PeriodTracking) {
                $profile?->update(['onboarding_step' => OnboardingStep::Birthday]);

                return redirect()->route('onboarding.show', ['step' => OnboardingStep::Birthday->value]);
            }

            if ($requestedStep === OnboardingStep::PeriodTracking) {
                return redirect()->route('onboarding.show', ['step' => $currentStep->value]);
            }
        }

        $allowedSteps = OnboardingStep::orderedFor($profile);
        $allowedValues = array_map(static fn (OnboardingStep $step): string => $step->value, $allowedSteps);

        if (! in_array($requestedStep->value, $allowedValues, true)) {
            abort(404);
        }

        return $next($request);
    }
}
