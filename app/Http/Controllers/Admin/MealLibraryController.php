<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealFromLibraryRequest;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealCsvLibraryImportService;
use App\Services\RecipeNutritionCalculator;
use App\Support\IngredientAllergenCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MealLibraryController extends Controller
{
    public function index(): Response
    {
        if (! $this->mealLibrarySchemaReady()) {
            return Inertia::render('Admin/MealLibrary', $this->mealLibraryIndexPayload([], []));
        }

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

        return Inertia::render('Admin/MealLibrary', $this->mealLibraryIndexPayload($meals, $ingredientProfiles));
    }

    public function store(StoreMealFromLibraryRequest $request): RedirectResponse
    {
        if (! $this->mealLibrarySchemaReady()) {
            return redirect()
                ->route('admin.meal-library')
                ->with('error', __('Run `php artisan migrate` to update the database, then try saving again.'));
        }

        $data = $request->validated();

        $category = RecipeCategory::from($data['category']);
        $mealType = MealType::fromRecipeCategory($category);

        $cyclePhase = null;
        if (! empty($data['cycle_phase'])) {
            $cyclePhase = CyclePhase::from($data['cycle_phase']);
        }

        $dietTags = array_values(array_unique(array_filter($data['diet_tags'] ?? [])));

        $meal = DB::transaction(function () use ($request, $data, $category, $mealType, $cyclePhase, $dietTags): Meal {
            $meal = Meal::query()->create([
                'name' => $data['name'],
                'category' => $category,
                'meal_type' => $mealType,
                'description' => $data['description'] ?? null,
                'highlight' => $data['highlight'] ?? null,
                'meal_plan_tag' => $data['meal_plan_tag'] ?? null,
                'total_calories' => (float) $data['total_calories'],
                'total_protein' => (float) ($data['total_protein'] ?? 0),
                'total_carbs' => (float) ($data['total_carbs'] ?? 0),
                'total_fat' => (float) ($data['total_fat'] ?? 0),
                'diet_tags' => $dietTags,
                'diet_type' => null,
                'cycle_phase' => $cyclePhase,
                'finished_weight_grams' => isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
            ]);

            $byIngredientGrams = [];
            foreach ($data['ingredients'] ?? [] as $row) {
                $grams = (float) ($row['amount_grams'] ?? 0);
                if ($grams <= 0) {
                    continue;
                }

                $ingredient = null;
                $ingredientId = $row['ingredient_id'] ?? null;
                if ($ingredientId !== null && $ingredientId !== '') {
                    $ingredient = Ingredient::query()
                        ->whereKey((int) $ingredientId)
                        ->where('is_verified', true)
                        ->first();
                }

                if ($ingredient === null) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $ingredient = Ingredient::query()
                        ->where('name', $name)
                        ->where('is_verified', true)
                        ->first();
                }

                if ($ingredient === null) {
                    continue;
                }

                $id = (int) $ingredient->getKey();
                $byIngredientGrams[$id] = ($byIngredientGrams[$id] ?? 0) + $grams;
            }

            foreach ($byIngredientGrams as $ingredientId => $grams) {
                $rounded = round($grams, 2);
                $meal->ingredients()->attach($ingredientId, [
                    'amount_grams' => $rounded,
                    'amount' => $rounded,
                    'unit' => 'g',
                ]);
            }

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('meals', 'public');
                $meal->image_path = $path;
            }

            $meal->refresh();
            $meal->load('ingredients');

            $ingredientIdsForSafety = array_map(intval(...), array_keys($byIngredientGrams));
            $meal->safety_alert_tags = $this->safetyAlertTagsForIngredientIds($ingredientIdsForSafety);

            if ($meal->ingredients->isNotEmpty()) {
                $nutrition = RecipeNutritionCalculator::fromMeal($meal);
                $meal->fill(Meal::nutritionSummaryToPersistedAttributes($nutrition));
                $meal->sickle_cell_program_highlight = RecipeNutritionCalculator::sickleCellProgramMealHighlight($nutrition);
                $meal->nutrition_aggregates_synced = true;
            } else {
                $meal->sickle_cell_program_highlight = RecipeNutritionCalculator::sickleCellProgramMealHighlight(
                    $meal->persistedNutritionAsCalculatorShape()
                );
                $meal->nutrition_aggregates_synced = false;
            }

            $meal->save();

            return $meal;
        });

        return redirect()
            ->route('admin.meal-library')
            ->with('success', __('Meal created successfully.'));
    }

    private function mealLibrarySchemaReady(): bool
    {
        try {
            return Schema::hasColumn('meals', 'safety_alert_tags')
                && Schema::hasColumn('meals', 'nutrition_aggregates_synced')
                && Schema::hasColumn('ingredients', 'common_allergens');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $meals
     * @param  list<array<string, mixed>>  $ingredientProfiles
     * @return array<string, mixed>
     */
    private function mealLibraryIndexPayload(array $meals, array $ingredientProfiles): array
    {
        $payload = [
            'meals' => $meals,
            'ingredientProfiles' => $ingredientProfiles,
            'mealCategoryOptions' => array_map(
                static fn (RecipeCategory $category): string => $category->value,
                MealCsvLibraryImportService::mealLibraryCsvAllowedCategories(),
            ),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'mealStoreUrl' => route('admin.meal-library.store'),
            'csvTemplateUrl' => asset('templates/meal-library-template.csv'),
            'csvExportUrl' => route('meals.library.export-csv'),
            'csvImportUrl' => route('meals.library.import-csv'),
        ];

        if (! $this->mealLibrarySchemaReady()) {
            $payload['mealLibrarySchemaNotice'] = __('Database update required: run `php artisan migrate` in the project root, then refresh this page.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function toMealRow(Meal $meal): array
    {
        $meal->loadMissing('ingredients');

        if ($meal->ingredients->isNotEmpty() && $meal->nutrition_aggregates_synced) {
            $nutrition = $meal->persistedNutritionAsCalculatorShape();
        } elseif ($meal->ingredients->isNotEmpty()) {
            $nutrition = RecipeNutritionCalculator::fromMeal($meal);
        } else {
            $nutrition = $this->nutritionFromStoredTotals($meal);
        }

        $nutrientHighlights = $this->nutrientHighlightsForUi($nutrition);
        if (RecipeNutritionCalculator::sickleCellProgramMealHighlight($nutrition)) {
            $nutrientHighlights[] = 'Sickle Cell';
        }
        $nutrientHighlights = array_values(array_unique($nutrientHighlights));

        $storedSafety = is_array($meal->safety_alert_tags) ? $meal->safety_alert_tags : [];
        $safetyAlertTags = $storedSafety !== [] ? $storedSafety : $this->safetyAlertTagsForIngredientIds(
            $meal->ingredients->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

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
            'nutrientHighlights' => $nutrientHighlights,
            'safetyAlertTags' => array_values($safetyAlertTags),
        ];
    }

    /**
     * @param  list<int>  $ingredientIds
     * @return list<string>
     */
    private function safetyAlertTagsForIngredientIds(array $ingredientIds): array
    {
        if ($ingredientIds === []) {
            return [];
        }

        $labels = [];
        $rows = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->get(['id', 'common_allergens']);

        foreach ($rows as $ingredient) {
            foreach (IngredientAllergenCatalog::labelsFromSlugs(
                is_array($ingredient->common_allergens) ? $ingredient->common_allergens : [],
            ) as $label) {
                $labels[$label] = true;
            }
        }

        $out = array_keys($labels);
        sort($out);

        return $out;
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
            'b6' => (float) ($meal->total_b6 ?? 0),
            'b9_folate' => (float) ($meal->total_folate ?? 0),
            'b12' => (float) ($meal->total_b12 ?? 0),
            'iron' => (float) ($meal->total_iron ?? 0),
            'magnesium' => (float) ($meal->total_magnesium ?? 0),
            'fiber' => (float) ($meal->total_fiber ?? 0),
            'sugar' => (float) ($meal->total_sugar ?? 0),
            'calcium' => (float) ($meal->total_calcium ?? 0),
            'potassium' => (float) ($meal->total_potassium ?? 0),
            'sodium' => (float) ($meal->total_sodium ?? 0),
            'zinc' => (float) ($meal->total_zinc ?? 0),
            'vitamin_c' => (float) ($meal->total_vitamin_c ?? 0),
            'vitamin_a' => (float) ($meal->total_vitamin_a ?? 0),
            'vitamin_e' => (float) ($meal->total_vitamin_e ?? 0),
            'vitamin_d' => (float) ($meal->total_vitamin_d ?? 0),
            'vitamin_k' => (float) ($meal->total_vitamin_k ?? 0),
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
        $mealPlanTag = is_string($meal->meal_plan_tag ?? null) ? trim((string) $meal->meal_plan_tag) : '';
        if ($mealPlanTag !== '') {
            $tags[] = ['label' => $mealPlanTag, 'type' => 'dietary'];
        }
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
            'id' => (int) $ingredient->getKey(),
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
            'common_allergens' => array_values(array_filter(
                is_array($ingredient->common_allergens) ? $ingredient->common_allergens : [],
                static fn ($v): bool => is_string($v) && $v !== '',
            )),
        ];
    }
}
