<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Enums\MealCyclePhaseTag;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Enums\RecipeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMealsForSchedulerRequest;
use App\Http\Requests\StoreMealPlanFromLibraryRequest;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanLibraryController extends Controller
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private MealPlanService $mealPlanService,
        private MealLibraryController $mealLibrary,
    ) {}

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

        $mealPlans = MealPlan::query()
            ->where('schema_type', MealPlanSchemaType::WeeklyStructured)
            ->latest()
            ->get()
            ->map(function (MealPlan $plan): array {
                $dailyMacros = $this->mealPlanService->averageDailyNutritionForOption($plan, false);
                $category = $plan->plan_category;

                $tags = [$category instanceof MealPlanLibraryCategory ? $category->label() : __('Balanced')];
                if ($plan->cycle_phase instanceof MealCyclePhaseTag) {
                    $tags[] = $plan->cycle_phase->label();
                }

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'category' => $category instanceof MealPlanLibraryCategory ? $category->label() : __('Balanced'),
                    'imageUrl' => null,
                    'tags' => $tags,
                    'showUrl' => route('admin.meal-plan-library.show', $plan),
                    'dailyMacros' => [
                        'calories' => (float) ($dailyMacros['calories'] ?? 0),
                        'protein' => (float) ($dailyMacros['protein'] ?? 0),
                        'carbs' => (float) ($dailyMacros['carbs'] ?? 0),
                        'fat' => (float) ($dailyMacros['fat'] ?? 0),
                    ],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/MealPlanLibrary', [
            'dietTypes' => DietType::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'mealSearchUrl' => route('admin.meal-plan-library.meals.search'),
            'mealPlanStoreUrl' => route('admin.meal-plan-library.store'),
            'schedulerMeals' => $schedulerMeals,
            'mealPlans' => $mealPlans,
        ]);
    }

    public function show(MealPlan $mealPlan): Response
    {
        $mealPlan->load([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal.ingredients',
        ]);

        $dayCount = max(1, $mealPlan->structuredPlanningDayCount());
        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

        /** @var array<int, array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>}> $daysByNumber */
        $daysByNumber = [];
        for ($dayNumber = 1; $dayNumber <= $dayCount; $dayNumber++) {
            $daysByNumber[$dayNumber] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'categories' => $emptyCategories,
            ];
        }

        foreach ($mealPlan->dayMeals as $dayMeal) {
            if (! $dayMeal instanceof MealPlanDayMeal || $dayMeal->meal === null) {
                continue;
            }

            $dayNumber = (int) $dayMeal->day_number;
            if (! isset($daysByNumber[$dayNumber])) {
                continue;
            }

            $categoryKey = $this->slotTypeToCategoryKey($dayMeal->slot_type);
            $daysByNumber[$dayNumber]['categories'][$categoryKey][] = $this->mealLibrary->presentMealRowForUi($dayMeal->meal);
        }

        $dailyMacros = $this->mealPlanService->averageDailyNutritionForOption($mealPlan, false);
        $category = $mealPlan->plan_category;
        $tags = [$category instanceof MealPlanLibraryCategory ? $category->label() : __('Balanced')];
        if ($mealPlan->cycle_phase instanceof MealCyclePhaseTag) {
            $tags[] = $mealPlan->cycle_phase->label();
        }

        return Inertia::render('Admin/MealPlanDetail', [
            'mealPlan' => [
                'id' => $mealPlan->id,
                'name' => $mealPlan->name,
                'goal' => $mealPlan->goal,
                'category' => $category instanceof MealPlanLibraryCategory ? $category->label() : __('Balanced'),
                'tags' => $tags,
                'dailyMacros' => [
                    'calories' => (float) ($dailyMacros['calories'] ?? 0),
                    'protein' => (float) ($dailyMacros['protein'] ?? 0),
                    'carbs' => (float) ($dailyMacros['carbs'] ?? 0),
                    'fat' => (float) ($dailyMacros['fat'] ?? 0),
                ],
            ],
            'days' => array_values($daysByNumber),
            'libraryUrl' => route('admin.meal-plan-library'),
            'ingredientProfiles' => $this->mealLibrary->verifiedIngredientProfilesForUi(),
        ]);
    }

    public function store(StoreMealPlanFromLibraryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $planCategory = MealPlanLibraryCategory::from((string) $validated['plan_category']);
        $cyclePhase = $planCategory === MealPlanLibraryCategory::CycleSync && isset($validated['cycle_phase'])
            ? MealCyclePhaseTag::from((string) $validated['cycle_phase'])
            : null;

        $dailyProtein = $validated['target_daily_protein_g'] ?? null;
        $dailyCarbs = $validated['target_daily_carbs_g'] ?? null;
        $dailyFat = $validated['target_daily_fat_g'] ?? null;

        $this->mealPlanService->createWeeklyStructuredPlanFromScheduler(
            (string) $validated['name'],
            (string) $validated['goal'],
            $planCategory,
            $cyclePhase,
            (float) $validated['target_daily_calories'],
            $dailyProtein !== null && $dailyProtein !== '' ? (float) $dailyProtein : null,
            $dailyCarbs !== null && $dailyCarbs !== '' ? (float) $dailyCarbs : null,
            $dailyFat !== null && $dailyFat !== '' ? (float) $dailyFat : null,
            $validated['slots'],
        );

        return redirect()
            ->route('admin.meal-plan-library')
            ->with('success', __('Meal plan saved.'));
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

    private function slotTypeToCategoryKey(MealPlanSlotType $slotType): string
    {
        return match ($slotType) {
            MealPlanSlotType::Breakfast => 'breakfasts',
            MealPlanSlotType::Main => 'meals',
            MealPlanSlotType::Salad => 'sideSalads',
            MealPlanSlotType::Dessert => 'desserts',
            MealPlanSlotType::Soup => 'soup',
        };
    }
}
