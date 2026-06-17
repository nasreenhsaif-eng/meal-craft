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

        $latestCraftPlan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        return Inertia::render('App/Home', [
            'customerName' => $user?->name ?? '',
            'consultationUrl' => route('consultation.crafted-for-you'),
            'profile' => $profile ? [
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'macroSplitStyle' => $profile->macro_split_style?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
            ] : null,
            'craftPlan' => $latestCraftPlan ? [
                'craftKey' => $latestCraftPlan->craft_key,
                'weekDuration' => $latestCraftPlan->week_duration,
                'submittedAt' => $latestCraftPlan->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
