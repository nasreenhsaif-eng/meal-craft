<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\MacroSplitStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOnboardingRequest;
use App\Models\CustomerProfile;
use App\Services\Nutrition\OnboardingCalorieCalculator;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function store(StoreOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $sex = CustomerSex::from($validated['sex']);
        $activity = CustomerActivityLevel::from($validated['activity_level']);
        $macroStyle = MacroSplitStyle::from($validated['macro_split_style']);
        $percentages = $macroStyle->percentages();

        $dailyCalories = isset($validated['daily_calorie_target'])
            ? (int) $validated['daily_calorie_target']
            : OnboardingCalorieCalculator::estimateDailyCalories(
                (float) $validated['weight_kg'],
                (float) $validated['height_cm'],
                (int) $validated['age'],
                $sex,
                $activity,
            );

        $profile = CustomerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'weight_kg' => (float) $validated['weight_kg'],
                'height_cm' => (float) $validated['height_cm'],
                'age' => (int) $validated['age'],
                'sex' => $sex,
                'activity_level' => $activity,
                'macro_split_style' => $macroStyle,
                'daily_calorie_target' => $dailyCalories,
                'protein_percentage' => $percentages['protein_percentage'],
                'carb_percentage' => $percentages['carb_percentage'],
                'fat_percentage' => $percentages['fat_percentage'],
                'onboarding_completed_at' => now(),
            ],
        );

        $plan = UserPlanCalculator::calculateUserPlan($profile);

        return response()->json([
            'message' => 'Onboarding saved.',
            'profile' => [
                'id' => $profile->id,
                'daily_calorie_target' => $profile->daily_calorie_target,
                'macro_split_style' => $profile->macro_split_style->value,
                'protein_percentage' => (float) $profile->protein_percentage,
                'carb_percentage' => (float) $profile->carb_percentage,
                'fat_percentage' => (float) $profile->fat_percentage,
            ],
            'plan' => $plan,
        ], 201);
    }
}
