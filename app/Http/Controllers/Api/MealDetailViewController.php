<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\MealLibraryController;
use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\AdaptedMenuBuildOptionsFromRequest;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealDetailViewController extends Controller
{
    public function __invoke(Request $request, Meal $meal, MealLibraryController $mealLibrary): JsonResponse
    {
        $user = $request->user();
        $profile = AdminConsultationPreviewProfile::resolve($user);

        $row = $mealLibrary->presentMealRowForUi($meal);

        if ($profile !== null && $profile->daily_calorie_target !== null) {
            $buildOptions = AdaptedMenuBuildOptionsFromRequest::resolve($request, $user);
            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, $buildOptions);

            if ($adapted !== null) {
                $row = $mealLibrary->applyAdaptedToMealRow($row, $adapted, $meal);
            }
        }

        return response()->json([
            'detailView' => $row['detailView'],
            'editForm' => $row['editForm'],
        ]);
    }
}
