<?php

namespace App\Support;

use App\Enums\OnboardingStep;
use App\Models\User;

/**
 * Resolves the first URL a user should visit immediately after authentication.
 */
final class PostAuthenticationRedirect
{
    public static function pathFor(User $user): string
    {
        if ($user->needsSignupVerification()) {
            return route('join.verify', absolute: false);
        }

        if ($user->isAdmin()) {
            return route('login.portal-choice', absolute: false);
        }

        // Welcome back only after profile onboarding + submitted meal choices.
        if ($user->shouldLandOnWelcomeBack()) {
            return route('app.home', absolute: false);
        }

        if ($user->hasCompletedOnboarding()) {
            return route('consultation.crafted-for-you', absolute: false);
        }

        return route('onboarding.show', [
            'step' => OnboardingStep::Gender->value,
        ], absolute: false);
    }
}
