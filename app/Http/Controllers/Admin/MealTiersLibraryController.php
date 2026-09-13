<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MealLibraryKey;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\CopyMealToTiersLibraryRequest;
use App\Http\Requests\StoreMealTiersLibraryMealRequest;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealTiersLibraryCopyService;
use App\Support\MealImagePath;
use App\Support\MealInstructionsText;
use App\Support\MealTiersAuthoredPlates;
use App\Support\MealTiersCalorieTabs;
use App\Support\MealTiersIngredientStructurer;
use App\Support\MealTiersLibraryBrowseTab;
use App\Support\MealTiersLibraryPresentation;
use App\Support\MealTiersProteinFamily;
use App\Support\RawPrepIngredientPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MealTiersLibraryController extends Controller
{
    public function __construct(private MealTiersLibraryCopyService $copyService) {}

    public function index(): Response
    {
        $this->copyService->purgeExcludedFromTiersLibrary();

        $mealRows = Meal::queryForMealTiersLibrary()
            ->with(['ingredients', 'calorieTiers.ingredients'])
            ->get()
            ->map(fn (Meal $meal): array => $this->toMealRow($meal))
            ->values()
            ->all();

        $classicMeals = Meal::queryForMealLibrary()
            ->get(['id', 'name', 'category'])
            ->map(fn (Meal $meal): array => [
                'id' => $meal->id,
                'title' => $meal->name,
                'category' => ($meal->category ?? RecipeCategory::Meal)->value,
            ])
            ->values()
            ->all();

        $ingredientProfiles = Ingredient::query()
            ->where('is_verified', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/MealTiersLibrary', [
            'meals' => $mealRows,
            'classicMeals' => $classicMeals,
            'ingredientProfiles' => $ingredientProfiles,
            'proteinStructures' => config('meal_tiers_library'),
            'browseTabs' => MealTiersLibraryBrowseTab::tabsForMeals($mealRows),
            'urls' => [
                'index' => route('admin.meal-tiers-library'),
                'store' => route('admin.meal-tiers-library.store'),
                'copy' => route('admin.meal-tiers-library.copy'),
                'copyAll' => route('admin.meal-tiers-library.copy-all'),
            ],
            'categoryOptions' => collect(RecipeCategory::cases())
                ->reject(fn (RecipeCategory $category): bool => $category === RecipeCategory::BaseRecipe)
                ->map(fn (RecipeCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->value,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreMealTiersLibraryMealRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = RecipeCategory::from($data['category']);

        $meal = DB::transaction(function () use ($data, $category): Meal {
            $meal = Meal::query()->create([
                'name' => $data['name'],
                'category' => $category,
                'meal_type' => MealType::fromRecipeCategory($category),
                'library_key' => MealLibraryKey::Tiers,
                'library_sort_order' => Meal::nextTiersLibrarySortOrder(),
                'library_edited_at' => now(),
                'description' => $data['description'] ?? null,
                'nutrition_aggregates_synced' => true,
            ]);

            $this->persistIngredientsAndTiers($meal, $data);

            return $meal;
        });

        return redirect()
            ->route('admin.meal-tiers-library')
            ->with('success', __('Meal added to the Meal Tiers Library.'));
    }

    public function update(StoreMealTiersLibraryMealRequest $request, Meal $meal): RedirectResponse
    {
        abort_unless($meal->library_key === MealLibraryKey::Tiers, 404);

        $data = $request->validated();
        $category = RecipeCategory::from($data['category']);

        DB::transaction(function () use ($meal, $data, $category): void {
            $meal->update([
                'name' => $data['name'],
                'category' => $category,
                'meal_type' => MealType::fromRecipeCategory($category),
                'description' => $data['description'] ?? $meal->description,
                'library_edited_at' => now(),
            ]);

            $this->persistIngredientsAndTiers($meal->fresh(['ingredients']) ?? $meal, $data);
        });

        return redirect()
            ->route('admin.meal-tiers-library')
            ->with('success', __('Meal Tiers Library meal saved.'));
    }

    public function destroy(Meal $meal): RedirectResponse
    {
        abort_unless($meal->library_key === MealLibraryKey::Tiers, 404);

        $meal->delete();

        return redirect()
            ->route('admin.meal-tiers-library')
            ->with('success', __('Meal removed from the Meal Tiers Library.'));
    }

    public function copy(CopyMealToTiersLibraryRequest $request): RedirectResponse
    {
        $classic = Meal::queryForMealLibrary()
            ->with('ingredients')
            ->findOrFail((int) $request->validated('meal_id'));

        $this->copyService->copyFromClassic($classic);

        return redirect()
            ->route('admin.meal-tiers-library')
            ->with('success', __('Copied into the Meal Tiers Library with calorie tabs.'));
    }

    public function copyAll(): RedirectResponse
    {
        $result = $this->copyService->copyAllMissingFromClassic();
        $this->copyService->resyncAllCalorieTiers();

        return redirect()
            ->route('admin.meal-tiers-library')
            ->with(
                'success',
                __('Copied :copied meals into the Meal Tiers Library (:skipped already present).', [
                    'copied' => $result['copied'],
                    'skipped' => $result['skipped'],
                ]),
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistIngredientsAndTiers(Meal $meal, array $data): void
    {
        $baselineIngredients = $data['ingredients'] ?? [];
        $tierPayloads = $data['calorie_tiers'] ?? [];

        $fiveHundredIngredients = null;
        foreach (is_array($tierPayloads) ? $tierPayloads : [] as $payload) {
            if (! is_array($payload) || (int) ($payload['calorie_tier'] ?? 0) !== 500) {
                continue;
            }
            if (is_array($payload['ingredients'] ?? null) && $payload['ingredients'] !== []) {
                $fiveHundredIngredients = $payload['ingredients'];
                break;
            }
        }

        if ($fiveHundredIngredients !== null) {
            $baselineIngredients = $fiveHundredIngredients;
        } elseif ($baselineIngredients === [] && is_array($tierPayloads) && $tierPayloads !== []) {
            $first = $tierPayloads[0]['ingredients'] ?? [];
            $baselineIngredients = is_array($first) ? $first : [];
        }

        $sync = [];
        foreach ($baselineIngredients as $row) {
            if (! is_array($row) || ! isset($row['ingredient_id'])) {
                continue;
            }
            $id = (int) $row['ingredient_id'];
            $grams = (float) ($row['amount_grams'] ?? 0);
            $sync[$id] = [
                'amount_grams' => $grams,
                'amount' => $grams,
                'unit' => 'g',
            ];
        }
        $meal->ingredients()->sync($sync);
        $meal->load('ingredients');

        $tabs = MealTiersCalorieTabs::forMeal($meal);

        if ($tabs === []) {
            $meal->calorieTiers()->delete();
            $nutrition = MealTiersLibraryPresentation::nutritionFromGrams(
                collect($sync)->mapWithKeys(fn (array $pivot, int $id): array => [$id => (float) $pivot['amount_grams']])->all(),
            );
            $meal->update([
                'total_calories' => (float) ($nutrition['calories'] ?? 0),
                'total_protein' => (float) ($nutrition['protein'] ?? 0),
                'total_carbs' => (float) ($nutrition['carbs'] ?? 0),
                'total_fat' => (float) ($nutrition['fat'] ?? 0),
            ]);

            return;
        }

        $submittedByTier = [];
        foreach ($tierPayloads as $payload) {
            if (! is_array($payload) || ! isset($payload['calorie_tier'])) {
                continue;
            }
            $tier = (int) $payload['calorie_tier'];
            $grams = [];
            foreach ($payload['ingredients'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['ingredient_id'])) {
                    continue;
                }
                $grams[(int) $row['ingredient_id']] = (float) ($row['amount_grams'] ?? 0);
            }
            $submittedByTier[$tier] = $grams;
        }

        foreach ($tabs as $tier) {
            if (isset($submittedByTier[$tier]) && $submittedByTier[$tier] !== []) {
                $this->copyService->persistTier($meal, $tier, $submittedByTier[$tier]);

                continue;
            }

            $authoredGrams = MealTiersAuthoredPlates::gramsByIngredientIdForTier($meal, $tier);

            if ($authoredGrams !== null) {
                $this->copyService->persistTier($meal, $tier, $authoredGrams);

                continue;
            }

            $baselineGrams = [];
            foreach ($meal->ingredients as $ingredient) {
                $baselineGrams[$ingredient->id] = (float) ($ingredient->pivot->amount_grams ?? 0);
            }
            $this->copyService->persistTier(
                $meal,
                $tier,
                MealTiersIngredientStructurer::gramsForTier($meal, $baselineGrams, $tier),
            );
        }

        $meal->calorieTiers()->whereNotIn('calorie_tier', $tabs)->delete();

        $displayTier = $meal->calorieTiers()->where('calorie_tier', 500)->first()
            ?? $meal->calorieTiers()->first();

        if ($displayTier !== null) {
            $meal->update([
                'total_calories' => $displayTier->total_calories,
                'total_protein' => $displayTier->total_protein,
                'total_carbs' => $displayTier->total_carbs,
                'total_fat' => $displayTier->total_fat,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toMealRow(Meal $meal): array
    {
        $tabs = MealTiersCalorieTabs::forMeal($meal);
        $calorieTiers = [];

        foreach ($meal->calorieTiers as $tier) {
            $calorieTiers[] = MealTiersLibraryPresentation::calorieTierPayload($meal, $tier);
        }

        $authoredTiers = array_map(
            static fn (array $tier): int => $tier['calorie_tier'],
            array_values(array_filter($calorieTiers, static fn (array $tier): bool => $tier['authored'])),
        );

        $defaultTier = $calorieTiers[0] ?? null;
        foreach ($calorieTiers as $tier) {
            if ($tier['calorie_tier'] === 500) {
                $defaultTier = $tier;
                break;
            }
        }

        $macros = $defaultTier['macros'] ?? [
            'calories' => (int) round((float) ($meal->total_calories ?? 0)),
            'protein' => round((float) ($meal->total_protein ?? 0), 1),
            'carbs' => round((float) ($meal->total_carbs ?? 0), 1),
            'fat' => round((float) ($meal->total_fat ?? 0), 1),
        ];

        $ingredientRows = $meal->ingredients->map(fn ($ingredient): array => [
            'ingredient_id' => $ingredient->id,
            'name' => $ingredient->name,
            'amount_grams' => (float) ($ingredient->pivot->amount_grams ?? 0),
        ])->values()->all();

        $detailIngredientLines = array_map(
            static fn (array $row): string => $row['line'],
            $defaultTier['ingredients'] ?? [],
        );
        if ($detailIngredientLines === []) {
            $detailIngredientLines = array_map(
                static fn (Ingredient $ingredient): string => MealTiersLibraryPresentation::ingredientAmountLine(
                    $ingredient,
                    (float) ($ingredient->pivot->amount_grams ?? 0),
                ),
                MealTiersLibraryPresentation::sortedIngredients($meal->ingredients),
            );
        }

        $nutrition = $defaultTier['nutritionalData'] ?? MealTiersLibraryPresentation::nutritionalData(
            $meal->persistedNutritionAsCalculatorShape(),
        );

        $instructionLines = MealInstructionsText::linesFromRaw(
            trim((string) ($meal->instructions ?: $meal->description ?: '')),
        );

        $ingredientSections = $defaultTier['ingredientSections']
            ?? MealTiersLibraryPresentation::ingredientSectionsFromIngredients($meal->ingredients);

        $ingredientItems = array_map(
            static fn (array $row): array => [
                'line' => (string) ($row['line'] ?? ''),
                'ingredientId' => (int) ($row['ingredient_id'] ?? 0),
                'isBaseRecipe' => (bool) ($row['is_base_recipe'] ?? false),
            ],
            $defaultTier['ingredients'] ?? [],
        );

        if ($ingredientItems === []) {
            $ingredientItems = array_map(
                static fn (Ingredient $ingredient): array => MealTiersLibraryPresentation::structuredIngredientItem(
                    $ingredient,
                    MealTiersLibraryPresentation::ingredientAmountLine(
                        $ingredient,
                        (float) ($ingredient->pivot->amount_grams ?? 0),
                    ),
                ),
                MealTiersLibraryPresentation::sortedIngredients($meal->ingredients),
            );
        }

        return [
            'id' => (string) $meal->id,
            'title' => $meal->name,
            'imageUrl' => MealImagePath::resolveUrl($meal->image_path, $meal->name) ?: null,
            'category' => ($meal->category ?? RecipeCategory::Meal)->value,
            'usesTabs' => $tabs !== [],
            'tabValues' => $tabs,
            'calorieTiers' => $calorieTiers,
            'authoredTiers' => $authoredTiers,
            'proteinFamily' => MealTiersProteinFamily::forMeal($meal),
            'browseTab' => MealTiersLibraryBrowseTab::forMeal($meal),
            'eggMinimums' => $meal->category === RecipeCategory::Breakfast && ! MealTiersCalorieTabs::isChiaPudding($meal)
                ? config('meal_tiers_library.savory_egg_counts')
                : null,
            'macros' => $macros,
            'ingredients' => $ingredientRows,
            'description' => $meal->description,
            'detailView' => [
                'shortDescription' => '',
                'cyclePhases' => [],
                'dietaryTags' => [],
                'hasG6pdTrigger' => false,
                'safetyAlerts' => [],
                'sickleCellHighlights' => [],
                'nutritionalData' => $nutrition,
                'ingredients' => $detailIngredientLines !== [] ? $detailIngredientLines : [__('No ingredients on file.')],
                'ingredientItems' => $ingredientItems,
                'ingredientSections' => $ingredientSections,
                'ingredientsPrepNote' => $defaultTier['ingredientsPrepNote']
                    ?? RawPrepIngredientPresentation::ingredientsPrepNote(),
                'cookingYieldNote' => $defaultTier['cookingYieldNote'] ?? null,
                'instructions' => $instructionLines !== [] ? $instructionLines : [__('No written instructions on file.')],
                'imageUrl' => MealImagePath::resolveUrl($meal->image_path, $meal->name) ?: null,
                'imageAlt' => $meal->name,
            ],
        ];
    }
}
