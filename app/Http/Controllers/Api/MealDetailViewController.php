<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\MealLibraryController;
use App\Http\Controllers\Controller;
use App\Models\Meal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealDetailViewController extends Controller
{
    public function __invoke(Request $request, Meal $meal, MealLibraryController $mealLibrary): JsonResponse
    {
        $row = $mealLibrary->presentMealRowForUi($meal);

        return response()->json([
            'detailView' => $row['detailView'],
            'editForm' => $row['editForm'],
        ]);
    }
}
