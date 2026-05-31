<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Services\Nutrition\AdaptedMenuBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdaptedMenuController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = CustomerProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if ($profile === null || $profile->onboarding_completed_at === null) {
            return response()->json([
                'message' => 'Complete onboarding before viewing your adapted menu.',
            ], 422);
        }

        $menu = AdaptedMenuBuilder::build($profile);

        return response()->json([
            'profile_id' => $profile->id,
            'daily_calorie_target' => $profile->daily_calorie_target,
            'plan' => $menu['plan'],
            'fixed_meals' => $menu['fixed_meals'],
            'scalable_meals' => $menu['scalable_meals'],
        ]);
    }
}
