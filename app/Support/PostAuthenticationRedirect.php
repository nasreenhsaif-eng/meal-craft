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
        if ($user->isAdmin()) {
            return route('login.portal-choice', absolute: false);
        }

        if ($user->hasCompletedOnboarding()) {
            return route('app.home', absolute: false);
        }

        return route('onboarding.show', [
            'step' => OnboardingStep::Gender->value,
        ], absolute: false);
    }
}
