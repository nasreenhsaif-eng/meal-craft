<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMealsForSchedulerRequest;
use App\Models\Meal;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanLibraryController extends Controller
{
    public function index(): Response
    {
        $schedulerCategories = [
            RecipeCategory::Breakfast,
            RecipeCategory::Meal,
            RecipeCategory::SideSalad,
            RecipeCategory::Dessert,
            RecipeCategory::Soup,
        ];

        $schedulerMeals = Meal::queryForMealLibrary()
            ->whereIn('category', array_map(
                static fn (RecipeCategory $category): string => $category->value,
                $schedulerCategories,
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'category'])
            ->map(static function (Meal $meal): array {
                $category = $meal->category;

                return [
                    'id' => $meal->id,
                    'name' => $meal->name,
                    'category' => $category instanceof RecipeCategory ? $category->value : (string) $category,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/MealPlanLibrary', [
            'dietTypes' => DietType::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'mealSearchUrl' => route('admin.meal-plan-library.meals.search'),
            'schedulerMeals' => $schedulerMeals,
        ]);
    }

    public function searchMeals(SearchMealsForSchedulerRequest $request): JsonResponse
    {
        $categories = $request->validated('categories');
        $term = trim((string) $request->validated('q', ''));

        $query = Meal::queryForMealLibrary()
            ->whereIn('category', $categories)
            ->orderBy('name')
            ->limit(12);

        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        $meals = $query->get(['id', 'name', 'category'])->map(static function (Meal $meal): array {
            $category = $meal->category;

            return [
                'id' => $meal->id,
                'name' => $meal->name,
                'category' => $category instanceof RecipeCategory ? $category->value : (string) $category,
            ];
        })->values()->all();

        return response()->json(['meals' => $meals]);
    }
}
