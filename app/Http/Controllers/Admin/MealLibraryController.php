<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealCsvLibraryImportService;
use App\Services\RecipeNutritionCalculator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MealLibraryController extends Controller
{
    public function index(): Response
    {
        $meals = Meal::query()
            ->with(['ingredients' => function ($query): void {
                $query->orderBy('ingredients.name');
            }])
            ->latest('updated_at')
            ->get()
            ->map(fn (Meal $meal): array => $this->toMealRow($meal))
            ->values()
            ->all();

        $ingredientProfiles = Ingredient::query()
            ->where('is_verified', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient): array => $this->toIngredientProfile($ingredient))
            ->values()
            ->all();

        $mealCategoryOptions = array_map(
            static fn (RecipeCategory $category): string => $category->value,
            MealCsvLibraryImportService::mealLibraryCsvAllowedCategories(),
        );

        return Inertia::render('Admin/MealLibrary', [
            'meals' => $meals,
            'ingredientProfiles' => $ingredientProfiles,
            'mealCategoryOptions' => $mealCategoryOptions,
            'dietTypes' => DietType::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'csvTemplateUrl' => asset('templates/meal-library-template.csv'),
            'csvExportUrl' => route('meals.library.export-csv'),
            'csvImportUrl' => route('meals.library.import-csv'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toMealRow(Meal $meal): array
    {
        $nutrition = $meal->ingredients->isEmpty()
            ? $this->nutritionFromStoredTotals($meal)
            : RecipeNutritionCalculator::fromMeal($meal);

        return [
            'id' => (string) $meal->id,
            'title' => $meal->name,
            'imageUrl' => $this->mealImageUrl($meal),
            'mealType' => ($meal->category ?? RecipeCategory::Meal)->value,
            'category' => ($meal->category ?? RecipeCategory::Meal)->value,
            'prepMinutes' => 0,
            'macros' => [
                'calories' => (int) round((float) ($nutrition['calories'] ?? 0)),
                'protein' => round((float) ($nutrition['protein'] ?? 0), 1),
                'carbs' => round((float) ($nutrition['carbs'] ?? 0), 1),
                'fat' => round((float) ($nutrition['fat'] ?? 0), 1),
            ],
            'tags' => $this->tagsForMealCard($meal),
            'nutrientHighlights' => $this->nutrientHighlightsForUi($nutrition),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function nutritionFromStoredTotals(Meal $meal): array
    {
        return [
            'calories' => (float) ($meal->total_calories ?? 0),
            'protein' => (float) ($meal->total_protein ?? 0),
            'carbs' => (float) ($meal->total_carbs ?? 0),
            'fat' => (float) ($meal->total_fat ?? 0),
            'b9_folate' => (float) ($meal->total_folate ?? 0),
            'b12' => (float) ($meal->total_b12 ?? 0),
            'iron' => (float) ($meal->total_iron ?? 0),
            'magnesium' => (float) ($meal->total_magnesium ?? 0),
            'zinc' => (float) ($meal->total_zinc ?? 0),
        ];
    }

    /**
     * Smart Kitchen highlight chips (aligned with Meal Library create preview).
     *
     * @param  array<string, float>  $nutrition
     * @return list<string>
     */
    private function nutrientHighlightsForUi(array $nutrition): array
    {
        $badges = [];
        if (($nutrition['b9_folate'] ?? 0) >= 150) {
            $badges[] = 'Folate';
        }
        if (($nutrition['b12'] ?? 0) >= 1.5) {
            $badges[] = 'B12';
        }
        if (($nutrition['iron'] ?? 0) >= 6) {
            $badges[] = 'Iron';
        }
        if (($nutrition['magnesium'] ?? 0) >= 120) {
            $badges[] = 'Magnesium';
        }
        if (($nutrition['zinc'] ?? 0) >= 3) {
            $badges[] = 'Zinc';
        }

        return $badges;
    }

    /**
     * @return list<array{label: string, type: string}>
     */
    private function tagsForMealCard(Meal $meal): array
    {
        $tags = [];
        $category = $meal->category;
        if ($category !== null) {
            $tags[] = ['label' => $category->value, 'type' => 'category'];
        }
        $dietTags = is_array($meal->diet_tags) ? $meal->diet_tags : [];
        foreach ($dietTags as $tag) {
            $label = is_string($tag) ? trim($tag) : '';
            if ($label !== '') {
                $tags[] = ['label' => $label, 'type' => 'dietary'];
            }
        }

        return $tags;
    }

    private function mealImageUrl(Meal $meal): string
    {
        $path = $meal->image_path;
        if ($path === null || $path === '') {
            return '';
        }
        if (str_starts_with((string) $path, 'http://') || str_starts_with((string) $path, 'https://')) {
            return (string) $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function toIngredientProfile(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        return [
            'name' => $ingredient->name,
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'b6' => (float) $ingredient->b6,
            'b9_folate' => (float) $ingredient->b9_folate,
            'b12' => (float) $ingredient->b12,
            'iron' => (float) $ingredient->iron,
            'magnesium' => (float) $ingredient->magnesium,
            'micronutrients' => $micros,
        ];
    }
}
