<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\AdaptedMenuBuildOptionsFromRequest;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdaptedMenuController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = AdminConsultationPreviewProfile::resolve($user);

        if ($profile === null || $profile->daily_calorie_target === null) {
            return response()->json([
                'message' => 'Set your daily calorie target in onboarding before viewing your adapted menu.',
            ], 422);
        }

        $includeSoup = $request->boolean('include_soup');
        $buildOptions = AdaptedMenuBuildOptionsFromRequest::resolve($request, $user);

        $isAdminPreview = $user->isAdmin() && $user->isCustomer() !== true;

        if (isset($buildOptions['plan_tier']) && $isAdminPreview) {
            $planTier = (int) $buildOptions['plan_tier'];

            if ((int) $profile->daily_calorie_target !== $planTier) {
                $profile->daily_calorie_target = $planTier;
                $profile->save();
                $profile->refresh();
            }
        }

        $menu = AdaptedMenuBuilder::build($profile, $buildOptions);

        return response()->json([
            'profile_id' => $profile->id,
            'daily_calorie_target' => $profile->daily_calorie_target,
            'include_soup' => $includeSoup,
            'plan' => $menu['plan'],
            'fixed_portion_meals' => $menu['fixed_portion_meals'],
            'optional_add_on_meals' => $menu['optional_add_on_meals'],
            'scalable_meals' => $menu['scalable_meals'],
            'fixed_meals' => $menu['fixed_meals'],
            'scheduled_soups_by_weekday' => $menu['scheduled_soups_by_weekday'],
            'scheduled_full_craft_by_weekday' => $menu['scheduled_full_craft_by_weekday'],
            'production_meal_plan_id' => $menu['production_meal_plan_id'],
        ]);
    }
}
