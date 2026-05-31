<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAppController extends Controller
{
    public function home(): Response
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

        return Inertia::render('App/Home', [
            'customerName' => $user?->name ?? '',
            'profile' => $profile ? [
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'macroSplitStyle' => $profile->macro_split_style?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
