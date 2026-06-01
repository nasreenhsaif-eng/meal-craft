<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function index(): Response
    {
        $customers = CustomerProfile::query()
            ->with(['user:id,name,email,is_active,created_at'])
            ->latest()
            ->get()
            ->map(fn (CustomerProfile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'isActive' => (bool) $profile->user->is_active,
                'onboardingStep' => $profile->onboarding_step?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'joinedAt' => $profile->user->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/CustomerProfiles', [
            'customers' => $customers,
        ]);
    }
}
