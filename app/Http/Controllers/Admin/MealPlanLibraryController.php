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
use App\Http\Requests\Admin\StoreMealPlanDefaultDaySelectionsRequest;
use App\Http\Requests\SearchMealsForSchedulerRequest;
use App\Http\Requests\StoreMealPlanFromLibraryRequest;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Services\MealPlanDefaultDaySelections;
use App\Services\MealPlanLibraryTierPreview;
use App\Services\MealPlanService;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\ChiaDessertMeals;
use App\Support\NutrientDenseBreakfastOptions;
use App\Support\PrimaryFullCraftMainSlots;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanLibraryController extends Controller
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private MealPlanService $mealPlanService,
        private MealLibraryController $mealLibrary,
        private MealPlanLibraryTierPreview $tierPreview,
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

        $category = $mealPlan->plan_category;
        $isNutrientDensePlan = $category === MealPlanLibraryCategory::NutrientDense
            || str_contains(strtolower((string) ($mealPlan->name ?? '')), 'tbd');
        $storedDefaults = MealPlanDefaultDaySelections::forPlan($mealPlan);
        $hasStoredDefaults = $storedDefaults !== [];

        foreach ($mealPlan->dayMeals as $dayMeal) {
            if (! $dayMeal instanceof MealPlanDayMeal || $dayMeal->meal === null) {
                continue;
            }

            $dayNumber = (int) $dayMeal->day_number;
            if (! isset($daysByNumber[$dayNumber])) {
                continue;
            }

            $categoryKey = $this->slotTypeToCategoryKey($dayMeal->slot_type);
            $row = $this->mealLibrary->presentMealRowForUi($dayMeal->meal);
            $slotIndex = (int) $dayMeal->slot_index;

            // TBD Weekly Protocol: Greek yogurt chia lives on breakfast, not the dessert deck.
            if (
                $isNutrientDensePlan
                && $categoryKey === 'desserts'
                && ($slotIndex === 3 || ChiaDessertMeals::isChiaDessert($dayMeal->meal))
            ) {
                $categoryKey = 'breakfasts';
                $slotIndex = NutrientDenseBreakfastOptions::CHIA_SLOT_INDEX;
            }

            $row['plan_slot_index'] = $slotIndex;

            if ($hasStoredDefaults) {
                $row['isRecommended'] = in_array(
                    (int) $dayMeal->meal_id,
                    $storedDefaults[$dayNumber][$categoryKey] ?? [],
                    true,
                );
            } elseif ($categoryKey === 'meals') {
                $primarySlots = $isNutrientDensePlan
                    ? PrimaryFullCraftMainSlots::NUTRIENT_DENSE
                    : PrimaryFullCraftMainSlots::BALANCED;
                $row['isRecommended'] = in_array($slotIndex, $primarySlots, true);
            } else {
                $row['isRecommended'] = $slotIndex === 1;
            }

            $daysByNumber[$dayNumber]['categories'][$categoryKey][] = $row;
        }

        $dailyMacros = $this->mealPlanService->averageDailyNutritionForOption($mealPlan, false);
        $tags = [$category instanceof MealPlanLibraryCategory ? $category->label() : __('Balanced')];
        if ($mealPlan->cycle_phase instanceof MealCyclePhaseTag) {
            $tags[] = $mealPlan->cycle_phase->label();
        }

        $planTiers = UserPlanCalculator::planTiers();
        $defaultPlanTier = in_array(1500, $planTiers, true) ? 1500 : ($planTiers[2] ?? $planTiers[0] ?? 1500);

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
            'defaultDaySelections' => $storedDefaults,
            'planTiers' => $planTiers,
            'defaultPlanTier' => $defaultPlanTier,
            'tierPreviewUrl' => route('admin.meal-plan-library.tier-preview', $mealPlan),
            'saveDefaultSelectionsUrl' => route('admin.meal-plan-library.default-selections', $mealPlan),
            'libraryUrl' => route('admin.meal-plan-library'),
            'ingredientProfiles' => $this->mealLibrary->verifiedIngredientProfilesForUi(),
        ]);
    }

    public function storeDefaultSelections(
        StoreMealPlanDefaultDaySelectionsRequest $request,
        MealPlan $mealPlan,
    ): RedirectResponse {
        MealPlanDefaultDaySelections::store($mealPlan, $request->normalizedSelections());

        return redirect()
            ->route('admin.meal-plan-library.show', $mealPlan)
            ->with('success', __('Default meal selections saved. Customers will start with these picks and can still change them.'));
    }

    public function tierPreview(Request $request, MealPlan $mealPlan): JsonResponse
    {
        $validated = $request->validate([
            'plan_tier' => ['required', 'integer', Rule::in(UserPlanCalculator::planTiers())],
            'selections' => ['sometimes', 'string'],
        ]);

        $daySelectionsByDay = [];

        if (isset($validated['selections']) && is_string($validated['selections']) && trim($validated['selections']) !== '') {
            $decoded = json_decode($validated['selections'], true);

            if (is_array($decoded)) {
                foreach ($decoded as $dayNumber => $categories) {
                    if (! is_numeric($dayNumber) || ! is_array($categories)) {
                        continue;
                    }

                    $normalizedDay = (int) $dayNumber;

                    if ($normalizedDay < 1) {
                        continue;
                    }

                    /** @var array<string, list<int|string>> $categorySelections */
                    $categorySelections = [];

                    foreach ($categories as $categoryKey => $mealIds) {
                        if (! is_string($categoryKey) || ! is_array($mealIds)) {
                            continue;
                        }

                        $categorySelections[$categoryKey] = array_values(array_filter(
                            array_map(static fn (mixed $id): int => (int) $id, $mealIds),
                            static fn (int $id): bool => $id > 0,
                        ));
                    }

                    $daySelectionsByDay[$normalizedDay] = $categorySelections;
                }
            }
        }

        return response()->json([
            'planTier' => (int) $validated['plan_tier'],
            'days' => $this->tierPreview->daysForTier(
                $mealPlan,
                (int) $validated['plan_tier'],
                $request->user(),
                $daySelectionsByDay,
            ),
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
